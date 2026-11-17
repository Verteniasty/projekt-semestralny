<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();


if (metoda_post()) {
    sprawdz_csrf_lub_wroc('panel-admina.php');
    if (post('akcja') === 'status') {
        $id = id_z_tekstu(post('id'));
        $nowy = post('nowy');
        if ($blad = zmien_status($id, $nowy, (int) $u['id'])) {
            flash('err', $blad);
        } else {
            loguj_admina('rezerwacja_status', "Rezerwacja #{$id}: zmiana statusu na „" . etykieta_statusu($nowy) . '” (z pulpitu)');
            flash('ok', 'Status rezerwacji #' . $id . ' został zmieniony na „' . etykieta_statusu($nowy) . '”.');
        }
    } else {
        flash('err', 'Nieznana operacja.');
    }
    redirect('panel-admina.php');
}
if ($anuluj = id_z_tekstu(pobierz('anuluj'))) {
    if ($r = rezerwacja_po_id($anuluj)) {
        strona_potwierdzenia('Anulowanie rezerwacji', "Czy na pewno anulować rezerwację #{$r['id']} ({$r['usluga']}, " . termin_txt($r) . ", {$r['k_imie']} {$r['k_nazwisko']})? Klient dostanie powiadomienie.",
            ['akcja' => 'status', 'id' => $anuluj, 'nowy' => 'anulowane'], 'panel-admina.php', 'Tak, anuluj rezerwację');
    }
    flash('err', 'Nie znaleziono takiej rezerwacji.');
    redirect('panel-admina.php');
}

$dzis = date('Y-m-d');
$teraz = date('Y-m-d H:i:s');


$aktywneAuta = (int) db_wartosc("SELECT COUNT(*) FROM uslugi WHERE typ = 'doby' AND aktywne = 1");
$naWynajmie = (int) db_wartosc(
    "SELECT COUNT(DISTINCT r.id_uslugi) FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi
     WHERE us.typ = 'doby' AND r.status IN ('oczekujace','potwierdzone')
       AND (r.data_rezerwacji || ' ' || r.godzina_rozpoczecia) <= ? AND (r.data_zakonczenia || ' ' || r.godzina_zakonczenia) > ?",
    [$teraz, $teraz]
);
$wydaniaDzis = (int) db_wartosc("SELECT COUNT(*) FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi WHERE us.typ = 'doby' AND r.data_rezerwacji = ? AND r.status IN ('oczekujace','potwierdzone')", [$dzis]);
$zwrotyDzis = (int) db_wartosc("SELECT COUNT(*) FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi WHERE us.typ = 'doby' AND r.data_zakonczenia = ? AND r.status IN ('oczekujace','potwierdzone')", [$dzis]);
$doPotwierdzenia = (int) db_wartosc("SELECT COUNT(*) FROM rezerwacje WHERE status = 'oczekujace' AND data_zakonczenia >= ?", [$dzis]);
$przychodMiesiac = (float) db_wartosc("SELECT COALESCE(SUM(cena), 0) FROM rezerwacje WHERE status IN ('potwierdzone','zakonczone') AND data_rezerwacji >= ?", [date('Y-m-01')]);
$przychod = (float) db_wartosc("SELECT COALESCE(SUM(cena), 0) FROM rezerwacje WHERE status IN ('potwierdzone','zakonczone')");
$klienci = (int) db_wartosc('SELECT COUNT(*) FROM uzytkownicy WHERE rola = ? AND aktywne = 1', ['klient']);
$pracownicyAkt = (int) db_wartosc('SELECT COUNT(*) FROM pracownicy WHERE aktywne = 1');
$wszystkie = (int) db_wartosc('SELECT COUNT(*) FROM rezerwacje');


$odOkna = date('Y-m-d', strtotime('-29 days'));
$st = db()->prepare(
    "SELECT r.data_rezerwacji, r.data_zakonczenia FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi
     WHERE us.typ = 'doby' AND r.status IN ('potwierdzone','zakonczone') AND r.data_zakonczenia >= ? AND r.data_rezerwacji <= ?"
);
$st->execute([$odOkna, $dzis]);
$wynajeteDni = 0;
foreach ($st->fetchAll() as $w) {
    $od = max($w['data_rezerwacji'], $odOkna);
    $do = min($w['data_zakonczenia'], $dzis);
    $wynajeteDni += max(0, (int) round((strtotime($do . ' 12:00:00 UTC') - strtotime($od . ' 12:00:00 UTC')) / 86400));
}
$oblozenie = $aktywneAuta > 0 ? min(100, (int) round($wynajeteDni / ($aktywneAuta * 30) * 100)) : 0;


$poStatusach = array_fill_keys(array_values(STATUSY), 0);
$st = db()->prepare('SELECT status, COUNT(*) AS ile FROM rezerwacje GROUP BY status');
$st->execute();
foreach ($st->fetchAll() as $w) {
    $poStatusach[etykieta_statusu($w['status'])] = (int) $w['ile'];
}


$miesiace = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime(date('Y-m-01') . " -{$i} months");
    $miesiace[date('Y-m', $ts)] = MIESIACE_SKROT[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
$liczbaMies = array_fill_keys(array_values($miesiace), 0);
$przychodMies = array_fill_keys(array_values($miesiace), 0);
$st = db()->prepare("SELECT data_rezerwacji, status, cena FROM rezerwacje WHERE data_rezerwacji >= ? AND status <> 'anulowane'");
$st->execute([array_key_first($miesiace) . '-01']);
foreach ($st->fetchAll() as $w) {
    $nazwa = $miesiace[substr($w['data_rezerwacji'], 0, 7)] ?? null;
    if ($nazwa !== null) {
        $liczbaMies[$nazwa]++;
        if (in_array($w['status'], ['potwierdzone', 'zakonczone'], true)) {
            $przychodMies[$nazwa] += (int) round((float) $w['cena']);
        }
    }
}


$st = db()->prepare(
    "SELECT us.nazwa, COUNT(*) AS ile FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi
     WHERE r.status <> 'anulowane' GROUP BY us.id, us.nazwa ORDER BY ile DESC, us.nazwa LIMIT 5"
);
$st->execute();
$popularne = $st->fetchAll(PDO::FETCH_KEY_PAIR);


$doZrobienia = lista_rezerwacji(['status' => 'oczekujace', 'od' => $dzis], 6, 0, true);


$koniecOkna = date('Y-m-d', strtotime('+7 days'));
$zdarzenia = [];
foreach (lista_rezerwacji(['od' => $dzis, 'do' => $koniecOkna], 60, 0, true) as $r) {
    if (!in_array($r['status'], ['oczekujace', 'potwierdzone'], true)) {
        continue;
    }
    $g = substr($r['godzina_rozpoczecia'], 0, 5);
    if ($r['typ_uslugi'] === 'doby') {
        if ($r['data_rezerwacji'] >= $dzis && $r['data_rezerwacji'] <= $koniecOkna) {
            $zdarzenia[] = ['kiedy' => $r['data_rezerwacji'] . ' ' . $g, 'rodzaj' => 'Wydanie', 'r' => $r];
        }
        if ($r['data_zakonczenia'] >= $dzis && $r['data_zakonczenia'] <= $koniecOkna) {
            $zdarzenia[] = ['kiedy' => $r['data_zakonczenia'] . ' ' . $g, 'rodzaj' => 'Zwrot', 'r' => $r];
        }
    } else {
        $zdarzenia[] = ['kiedy' => $r['data_rezerwacji'] . ' ' . $g, 'rodzaj' => 'Wizyta', 'r' => $r];
    }
}
usort($zdarzenia, fn($a, $b) => strcmp($a['kiedy'], $b['kiedy']));
$zdarzenia = array_slice($zdarzenia, 0, 10);

admin_start('Pulpit', 'panel', 'Pulpit', 'Witaj, ' . $u['imie'] . '. Co dzieje się we flocie i co wymaga Twojej decyzji.');
?>
                <div class="panel-grid kpi">
                    <article class="stat"><strong><?= $naWynajmie ?><small> / <?= $aktywneAuta ?></small></strong><span>aut na wynajmie teraz<br><a href="admin-kalendarz.php">Kalendarz floty</a></span></article>
                    <article class="stat"><strong><?= $wydaniaDzis ?></strong><span>wydań aut dzisiaj</span></article>
                    <article class="stat"><strong><?= $zwrotyDzis ?></strong><span>zwrotów aut dzisiaj</span></article>
                    <article class="stat<?= $doPotwierdzenia ? ' stat-uwaga' : '' ?>"><strong><?= $doPotwierdzenia ?></strong><span>czeka na potwierdzenie<?= $doPotwierdzenia ? '<br><a href="admin-rezerwacje.php?status=oczekujace">Zobacz wszystkie</a>' : '' ?></span></article>
                </div>
                <div class="panel-grid kpi odstep-maly">
                    <article class="stat"><strong><?= $oblozenie ?>%</strong><span>obłożenie floty (ostatnie 30 dni)</span></article>
                    <article class="stat"><strong><?= e(cena_txt($przychodMiesiac)) ?></strong><span>przychód w tym miesiącu</span></article>
                    <article class="stat"><strong><?= e(cena_txt($przychod)) ?></strong><span>przychód łącznie (potwierdzone i zakończone)</span></article>
                    <article class="stat"><strong><?= $wszystkie ?></strong><span>rezerwacji łącznie<br><span class="muted"><?= $klienci ?> klientów · <?= $pracownicyAkt ?> pracowników · <?= $aktywneAuta ?> aut w ofercie</span></span></article>
                </div>

                <h2 class="odstep">Wymaga Twojej decyzji</h2>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <caption class="sr-only">Oczekujące rezerwacje</caption>
                        <thead><tr><th scope="col">Termin</th><th scope="col">Klient</th><th scope="col">Auto / usługa</th><th scope="col">Cena</th><th scope="col">Akcje</th></tr></thead>
                        <tbody>
<?php foreach ($doZrobienia as $r): ?>
                            <tr>
                                <td class="termin"><?= e(termin_txt($r)) ?></td>
                                <td><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?><br><span class="muted"><?= e($r['k_tel']) ?></span></td>
                                <td><?= e($r['usluga']) ?></td>
                                <td><?= cena_txt($r['cena']) ?></td>
                                <td class="akcje"><a class="btn small alt" href="admin-rezerwacja.php?id=<?= (int) $r['id'] ?>">Szczegóły</a> <?= przyciski_zmiany_statusu($r) ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$doZrobienia): ?>
                            <tr><td colspan="5">Wszystko potwierdzone. Brak oczekujących rezerwacji.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h2 class="odstep">Najbliższe 7 dni: wydania, zwroty i wizyty</h2>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <caption class="sr-only">Najbliższe zdarzenia</caption>
                        <thead><tr><th scope="col">Kiedy</th><th scope="col">Co</th><th scope="col">Auto / usługa</th><th scope="col">Klient</th><th scope="col">Pracownik</th><th scope="col">Status</th></tr></thead>
                        <tbody>
<?php foreach ($zdarzenia as $z): $r = $z['r']; ?>
                            <tr>
                                <td class="termin"><?= e($z['kiedy']) ?></td>
                                <td><span class="plakietka-typ plakietka-<?= e(strtolower($z['rodzaj'])) ?>"><?= e($z['rodzaj']) ?></span></td>
                                <td><a href="admin-rezerwacja.php?id=<?= (int) $r['id'] ?>"><?= e($r['usluga']) ?></a></td>
                                <td><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?></td>
                                <td><?= e($r['p_imie'] . ' ' . $r['p_nazwisko']) ?></td>
                                <td><?= znaczek_statusu($r['status']) ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$zdarzenia): ?>
                            <tr><td colspan="6">Brak zaplanowanych wydań, zwrotów i wizyt w najbliższych 7 dniach.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h2 class="odstep">Statystyki</h2>
                <div class="dwie-kolumny">
                    <div class="karta-formularz">
                        <h3>Rezerwacje wg statusu</h3>
                        <?= wykres_slupkowy($poStatusach, 'Liczba rezerwacji według statusu') ?>
                    </div>
                    <div class="karta-formularz">
                        <h3>Najczęściej wynajmowane</h3>
                        <?= wykres_slupkowy($popularne, 'Najczęściej rezerwowane auta i usługi') ?>
                    </div>
                    <div class="karta-formularz">
                        <h3>Rezerwacje, ostatnie 6 miesięcy</h3>
                        <?= wykres_slupkowy($liczbaMies, 'Liczba rezerwacji w kolejnych miesiącach') ?>
                    </div>
                    <div class="karta-formularz">
                        <h3>Przychód, ostatnie 6 miesięcy</h3>
                        <?= wykres_slupkowy($przychodMies, 'Przychód w kolejnych miesiącach', ' zł') ?>
                    </div>
                </div>
<?php admin_koniec(); ?>
