<?php



const STATUSY = [
    'oczekujace'   => 'Oczekująca',
    'potwierdzone' => 'Potwierdzona',
    'anulowane'    => 'Anulowana',
    'zakonczone'   => 'Zakończona',
];


const PRZEJSCIA_STATUSOW = [
    'oczekujace'   => ['potwierdzone', 'anulowane'],
    'potwierdzone' => ['zakonczone', 'anulowane'],
    'anulowane'    => [],
    'zakonczone'   => [],
];

function etykieta_statusu(string $status): string
{
    return STATUSY[$status] ?? $status;
}

function znaczek_statusu(string $status): string
{
    return '<span class="status status-' . e($status) . '">' . e(etykieta_statusu($status)) . '</span>';
}

function dodaj_historie(int $rezerwacja, ?string $stary, string $nowy, ?int $uzytkownik): void
{
    db()->prepare('INSERT INTO historia_statusow (id_rezerwacji, stary_status, nowy_status, id_uzytkownika, utworzony) VALUES (?, ?, ?, ?, ?)')
        ->execute([$rezerwacja, $stary, $nowy, $uzytkownik, date('Y-m-d H:i:s')]);
}


function poczatek_wizyty(array $r): int
{
    return strtotime($r['data_rezerwacji'] . ' ' . substr($r['godzina_rozpoczecia'], 0, 5) . ':00');
}


function termin_txt(array $r): string
{
    $od = substr($r['godzina_rozpoczecia'], 0, 5);
    if (($r['typ_uslugi'] ?? '') === 'doby' || !empty($r['liczba_dob'])) {
        return $r['data_rezerwacji'] . ' ' . $od . ' → ' . $r['data_zakonczenia'] . ' ' . $od . ' (' . doby_txt((int) $r['liczba_dob']) . ')';
    }
    return $r['data_rezerwacji'] . ', ' . $od . '–' . substr($r['godzina_zakonczenia'], 0, 5);
}


function doby_txt(int $n): string
{
    $m10 = $n % 10;
    $m100 = $n % 100;
    return $n . ' ' . ($n === 1 ? 'doba' : ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14) ? 'doby' : 'dób'));
}








function utworz_rezerwacje(array $klient, int $usluga, int $pracownik, string $data, string $godzina, string $komentarz, int $doby = 1): array
{
    if ($usluga < 1 || $pracownik < 1) {
        return ['blad' => 'Wybierz usługę i pracownika.'];
    }
    if (!data_poprawna($data)) {
        return ['blad' => 'Podaj poprawną datę.'];
    }
    if (!godzina_poprawna($godzina)) {
        return ['blad' => 'Wybierz godzinę z listy wolnych terminów.'];
    }
    if ($e = blad_tekstu($komentarz, 'komentarz', 0, 500)) {
        return ['blad' => $e];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT id FROM pracownicy WHERE id = ? AND aktywne = 1 FOR UPDATE');
        $st->execute([$pracownik]);
        if (!$st->fetch()) {
            $pdo->rollBack();
            return ['blad' => 'Wybrany pracownik jest niedostępny.'];
        }

        $st = $pdo->prepare('SELECT id, typ, czas_trwania, cena FROM uslugi WHERE id = ? AND aktywne = 1 FOR UPDATE');
        $st->execute([$usluga]);
        $u = $st->fetch();
        if (!$u) {
            $pdo->rollBack();
            return ['blad' => 'Wybrana usługa jest niedostępna.'];
        }

        $st = $pdo->prepare('SELECT 1 FROM uslugi_pracownikow WHERE id_pracownika = ? AND id_uslugi = ?');
        $st->execute([$pracownik, $usluga]);
        if (!$st->fetch()) {
            $pdo->rollBack();
            return ['blad' => 'Ten pracownik nie obsługuje wybranej usługi.'];
        }

        $start = minuty($godzina);
        if ($u['typ'] === 'doby') {

            if ($blad = blad_wynajmu($pracownik, $usluga, $data, $start, $doby)) {
                $pdo->rollBack();
                return ['blad' => $blad];
            }
            $godzinaKoniec = godz($start) . ':00';
            $dataKoniec = data_plus_dni($data, $doby);
            $liczbaDob = $doby;
            $cena = number_format((float) $u['cena'] * $doby, 2, '.', '');
        } else {
            $czas = (int) $u['czas_trwania'];
            if ($blad = blad_terminu($pracownik, $data, $start, $czas, (int) $klient['id'])) {
                $pdo->rollBack();
                return ['blad' => $blad];
            }
            $godzinaKoniec = godz($start + $czas) . ':00';
            $dataKoniec = $data;
            $liczbaDob = null;
            $cena = $u['cena'];
        }

        $pdo->prepare(
            'INSERT INTO rezerwacje (id_uzytkownika, id_pracownika, id_uslugi, data_rezerwacji, godzina_rozpoczecia, godzina_zakonczenia, data_zakonczenia, liczba_dob, status, komentarz, cena)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $klient['id'], $pracownik, $usluga, $data,
            godz($start) . ':00', $godzinaKoniec, $dataKoniec, $liczbaDob,
            'oczekujace', $komentarz === '' ? null : $komentarz, $cena,
        ]);
        $id = (int) $pdo->lastInsertId();
        dodaj_historie($id, null, 'oczekujace', (int) $klient['id']);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }

    powiadom_o_rezerwacji($id, 'Rezerwacja przyjęta');
    return ['ok' => $id];
}





function zmien_status(int $id, string $nowy, ?int $kto, ?int $ograniczDoPracownika = null, ?int $ograniczDoKlienta = null): ?string
{
    if (!isset(STATUSY[$nowy])) {
        return 'Nieznany status.';
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id, status, data_rezerwacji, godzina_rozpoczecia FROM rezerwacje WHERE id = ?';
        $par = [$id];
        if ($ograniczDoPracownika !== null) {
            $sql .= ' AND id_pracownika = ?';
            $par[] = $ograniczDoPracownika;
        }
        if ($ograniczDoKlienta !== null) {
            $sql .= ' AND id_uzytkownika = ?';
            $par[] = $ograniczDoKlienta;
        }
        $st = $pdo->prepare($sql);
        $st->execute($par);
        $r = $st->fetch();
        if (!$r) {
            $pdo->rollBack();
            return 'Nie znaleziono takiej rezerwacji.';
        }
        if (!in_array($nowy, PRZEJSCIA_STATUSOW[$r['status']] ?? [], true)) {
            $pdo->rollBack();
            return 'Nie można zmienić statusu z „' . etykieta_statusu($r['status']) . '” na „' . etykieta_statusu($nowy) . '”.';
        }
        if ($nowy === 'zakonczone' && poczatek_wizyty($r) > time()) {
            $pdo->rollBack();
            return 'Wizyty, która jeszcze się nie rozpoczęła, nie można oznaczyć jako zakończonej.';
        }
        if ($nowy === 'anulowane' && $ograniczDoKlienta !== null && poczatek_wizyty($r) <= time()) {
            $pdo->rollBack();
            return 'Można anulować tylko przyszłe rezerwacje.';
        }
        $upd = $pdo->prepare('UPDATE rezerwacje SET status = ? WHERE id = ? AND status = ?');
        $upd->execute([$nowy, $id, $r['status']]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            return 'Status został w międzyczasie zmieniony. Odśwież stronę.';
        }
        dodaj_historie($id, $r['status'], $nowy, $kto);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
    powiadom_o_rezerwacji($id, 'Zmiana statusu rezerwacji: ' . etykieta_statusu($nowy));
    return null;
}


function anuluj_wlasna_rezerwacje(int $id, int $klient): ?string
{
    return zmien_status($id, 'anulowane', $klient, null, $klient);
}


function powiadom_o_rezerwacji(int $id, string $temat): void
{
    try {
        $r = rezerwacja_po_id($id);
        if (!$r) {
            return;
        }
        wyslij_mail(
            $r['k_email'],
            $temat . ' – RentLoudCars',
            "Cześć {$r['k_imie']},\n\n{$temat}.\n\nUsługa: {$r['usluga']}\nTermin: " . termin_txt($r)
            . "\nObsługuje: {$r['p_imie']} {$r['p_nazwisko']}\nCena: " . number_format((float) $r['cena'], 2, ',', ' ')
            . " zł\nStatus: " . etykieta_statusu($r['status']) . "\n\nRentLoudCars"
        );
    } catch (Throwable $ex) {
        error_log('Powiadomienie e-mail nie zostało wysłane: ' . $ex->getMessage());
    }
}

const REZERWACJE_SELECT =
    'SELECT r.id, r.id_uzytkownika, r.id_pracownika, r.id_uslugi, r.data_rezerwacji, r.godzina_rozpoczecia, r.godzina_zakonczenia,
            r.data_zakonczenia, r.liczba_dob, r.status, r.komentarz, r.cena, r.created_at,
            us.nazwa AS usluga, us.typ AS typ_uslugi, us.slug AS slug_uslugi,
            k.imie AS k_imie, k.nazwisko AS k_nazwisko, k.email AS k_email, k.numer_telefonu AS k_tel,
            pu.imie AS p_imie, pu.nazwisko AS p_nazwisko
     FROM rezerwacje r
     JOIN uslugi us ON us.id = r.id_uslugi
     JOIN uzytkownicy k ON k.id = r.id_uzytkownika
     JOIN pracownicy pr ON pr.id = r.id_pracownika
     JOIN uzytkownicy pu ON pu.id = pr.id_uzytkownika';

function rezerwacja_po_id(int $id): ?array
{
    // widok v_rezerwacje (database.sql) łączy rezerwację z usługą, klientem i pracownikiem
    $st = db()->prepare('SELECT * FROM v_rezerwacje WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}


function filtry_rezerwacji_z_get(): array
{
    $status = pobierz('status');
    return [
        'status'    => isset(STATUSY[$status]) ? $status : '',
        'pracownik' => id_z_tekstu(pobierz('pracownik')),
        'od'        => data_poprawna(pobierz('od')) ? pobierz('od') : '',
        'do'        => data_poprawna(pobierz('do')) ? pobierz('do') : '',
        'q'         => mb_substr(pobierz('q'), 0, 60),
        'usluga'    => id_z_tekstu(pobierz('usluga')),
        'dzis'      => pobierz('dzis') === '1' ? '1' : '',
    ];
}


function warunki_rezerwacji(array $f): array
{
    $gdzie = [];
    $par = [];
    if (!empty($f['klient'])) {
        $gdzie[] = 'r.id_uzytkownika = ?';
        $par[] = (int) $f['klient'];
    }
    if (!empty($f['pracownik'])) {
        $gdzie[] = 'r.id_pracownika = ?';
        $par[] = (int) $f['pracownik'];
    }
    if (!empty($f['status'])) {
        $gdzie[] = 'r.status = ?';
        $par[] = $f['status'];
    }

    if (!empty($f['od'])) {
        $gdzie[] = 'r.data_zakonczenia >= ?';
        $par[] = $f['od'];
    }
    if (!empty($f['do'])) {
        $gdzie[] = 'r.data_rezerwacji <= ?';
        $par[] = $f['do'];
    }
    if (!empty($f['zakonczone_przed'])) {
        $gdzie[] = 'r.data_zakonczenia < ?';
        $par[] = $f['zakonczone_przed'];
    }
    if (!empty($f['usluga'])) {
        $gdzie[] = 'r.id_uslugi = ?';
        $par[] = (int) $f['usluga'];
    }
    if (!empty($f['dzis'])) {
        $gdzie[] = "(r.data_rezerwacji = ? OR r.data_zakonczenia = ?) AND r.status <> 'anulowane'";
        array_push($par, date('Y-m-d'), date('Y-m-d'));
    }
    if (!empty($f['q'])) {
        $wzor = '%' . like_escape($f['q']) . '%';
        $gdzie[] = "(k.imie LIKE ? ESCAPE '!' OR k.nazwisko LIKE ? ESCAPE '!' OR k.email LIKE ? ESCAPE '!' OR us.nazwa LIKE ? ESCAPE '!')";
        array_push($par, $wzor, $wzor, $wzor, $wzor);
    }
    return [$gdzie ? ' WHERE ' . implode(' AND ', $gdzie) : '', $par];
}

function policz_rezerwacje(array $f): int
{
    [$where, $par] = warunki_rezerwacji($f);
    return (int) db_wartosc(
        'SELECT COUNT(*) FROM rezerwacje r
         JOIN uslugi us ON us.id = r.id_uslugi
         JOIN uzytkownicy k ON k.id = r.id_uzytkownika' . $where,
        $par
    );
}


function lista_rezerwacji(array $f, int $limit = 0, int $offset = 0, bool $rosnaco = false, string $sort = ''): array
{
    [$where, $par] = warunki_rezerwacji($f);
    $kier = $rosnaco ? 'ASC' : 'DESC';
    $sql = REZERWACJE_SELECT . $where . " ORDER BY r.data_rezerwacji $kier, r.godzina_rozpoczecia $kier, r.id $kier";
    if ($sort === 'cena') {
        $sql = REZERWACJE_SELECT . $where . ' ORDER BY r.cena DESC, r.id DESC';
    } elseif ($sort === 'stare') {
        $sql = REZERWACJE_SELECT . $where . ' ORDER BY r.data_rezerwacji ASC, r.godzina_rozpoczecia ASC, r.id ASC';
    }
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, (int) $offset);
    }
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function historia_rezerwacji(int $id): array
{
    $st = db()->prepare(
        'SELECT h.stary_status, h.nowy_status, h.utworzony, u.imie, u.nazwisko, u.rola
         FROM historia_statusow h LEFT JOIN uzytkownicy u ON u.id = h.id_uzytkownika
         WHERE h.id_rezerwacji = ? ORDER BY h.id'
    );
    $st->execute([$id]);
    return $st->fetchAll();
}


function czas_txt(int $min): string
{
    if ($min >= 60) {
        return intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . ($min % 60) . ' min' : '');
    }
    return $min . ' min';
}

function cena_txt($cena): string
{
    return number_format((float) $cena, 2, ',', "\u{00A0}") . "\u{00A0}zł";
}


function oferta_uslug(): array
{
    // widok v_oferta (database.sql): aktywne usługi razem z nazwą kategorii
    $st = db()->prepare('SELECT * FROM v_oferta ORDER BY kategoria, cena, nazwa');
    $st->execute();
    return $st->fetchAll();
}


function pracownicy_uslugi(int $usluga): array
{
    $st = db()->prepare(
        'SELECT p.id, u.imie, u.nazwisko FROM uslugi_pracownikow up
         JOIN pracownicy p ON p.id = up.id_pracownika AND p.aktywne = 1
         JOIN uzytkownicy u ON u.id = p.id_uzytkownika AND u.aktywne = 1
         WHERE up.id_uslugi = ? ORDER BY u.nazwisko, u.imie'
    );
    $st->execute([$usluga]);
    $wynik = [];
    foreach ($st->fetchAll() as $w) {
        $wynik[(int) $w['id']] = $w['imie'] . ' ' . $w['nazwisko'];
    }
    return $wynik;
}

function usluga_aktywna(int $id): ?array
{
    $st = db()->prepare('SELECT id, nazwa, slug, typ, opis, czas_trwania, cena FROM uslugi WHERE id = ? AND aktywne = 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}


function cena_uslugi_txt(array $us): string
{
    return cena_txt($us['cena']) . (($us['typ'] ?? '') === 'doby' ? ' / doba' : '');
}


function usluga_po_slugu(string $slug): ?array
{
    if ($slug === '' || !preg_match('/^[a-z0-9\-]{1,60}$/', $slug)) {
        return null;
    }
    $st = db()->prepare('SELECT id, nazwa, slug, typ, opis, czas_trwania, cena FROM uslugi WHERE slug = ? AND aktywne = 1');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}


function liczniki_statusow(array $f): array
{
    $f['status'] = '';
    [$where, $par] = warunki_rezerwacji($f);
    $st = db()->prepare(
        'SELECT r.status, COUNT(*) AS ile FROM rezerwacje r
         JOIN uslugi us ON us.id = r.id_uslugi
         JOIN uzytkownicy k ON k.id = r.id_uzytkownika' . $where . ' GROUP BY r.status'
    );
    $st->execute($par);
    $wynik = array_fill_keys(array_keys(STATUSY), 0);
    foreach ($st->fetchAll() as $w) {
        $wynik[$w['status']] = (int) $w['ile'];
    }
    return $wynik;
}

