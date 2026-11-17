<?php












const KROK_SLOTOW_MIN = 15;
const MAKS_DNI_NAPRZOD = 365;
const MAKS_DOB = 30;
const PRZEKAZANIE_MIN = 30;

function minuty(string $godzina): int
{
    return (int) substr($godzina, 0, 2) * 60 + (int) substr($godzina, 3, 2);
}

function godz(int $minuty): string
{
    return sprintf('%02d:%02d', intdiv($minuty, 60), $minuty % 60);
}

function dzien_tygodnia_iso(string $data): int
{
    return (int) date('N', strtotime($data . ' 12:00:00'));
}


function przedzialy_pracy(int $pracownik, string $data): array
{
    $st = db()->prepare(
        'SELECT godzina_rozpoczecia, godzina_zakonczenia FROM godziny_pracy_pracownikow
         WHERE id_pracownika = ? AND data = ?'
    );
    $st->execute([$pracownik, $data]);
    $wiersze = $st->fetchAll();

    if (!$wiersze) {
        $st = db()->prepare(
            'SELECT godzina_rozpoczecia, godzina_zakonczenia FROM godziny_pracy_pracownikow
             WHERE id_pracownika = ? AND data IS NULL AND dzien_tygodnia = ?'
        );
        $st->execute([$pracownik, dzien_tygodnia_iso($data)]);
        $wiersze = $st->fetchAll();
    }

    $przedzialy = [];
    foreach ($wiersze as $w) {
        $od = minuty($w['godzina_rozpoczecia']);
        $do = minuty($w['godzina_zakonczenia']);
        if ($do > $od) {
            $przedzialy[] = [$od, $do];
        }
    }
    sort($przedzialy);
    return $przedzialy;
}



function zajete_pracownika(int $pracownik, string $data): array
{
    $st = db()->prepare(
        "SELECT r.data_rezerwacji, r.data_zakonczenia, r.godzina_rozpoczecia, r.godzina_zakonczenia, us.typ
         FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi
         WHERE r.id_pracownika = ? AND r.status <> 'anulowane' AND r.data_rezerwacji <= ? AND r.data_zakonczenia >= ?"
    );
    $st->execute([$pracownik, $data, $data]);
    $zajete = [];
    foreach ($st->fetchAll() as $r) {
        $od = minuty($r['godzina_rozpoczecia']);
        if ($r['typ'] === 'doby') {
            if ($r['data_rezerwacji'] === $data) {
                $zajete[] = [$od, $od + PRZEKAZANIE_MIN];
            }
            if ($r['data_zakonczenia'] === $data) {
                $zajete[] = [$od, $od + PRZEKAZANIE_MIN];
            }
        } else {
            $zajete[] = [$od, minuty($r['godzina_zakonczenia'])];
        }
    }
    return $zajete;
}



function zajete_klienta(int $klient, string $data): array
{
    $st = db()->prepare(
        "SELECT r.godzina_rozpoczecia, r.godzina_zakonczenia FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi
         WHERE r.id_uzytkownika = ? AND r.data_rezerwacji = ? AND r.status <> 'anulowane' AND us.typ = 'godziny'"
    );
    $st->execute([$klient, $data]);
    return array_map(fn($r) => [minuty($r['godzina_rozpoczecia']), minuty($r['godzina_zakonczenia'])], $st->fetchAll());
}


function nakladaja_sie(int $a1, int $a2, int $b1, int $b2): bool
{
    return $a1 < $b2 && $a2 > $b1;
}

function nachodzi_na_liste(int $start, int $koniec, array $lista): bool
{
    foreach ($lista as [$od, $do]) {
        if (nakladaja_sie($start, $koniec, $od, $do)) {
            return true;
        }
    }
    return false;
}


function w_godzinach_pracy(int $start, int $koniec, array $przedzialy): bool
{
    foreach ($przedzialy as [$od, $do]) {
        if ($start >= $od && $koniec <= $do) {
            return true;
        }
    }
    return false;
}



function blad_terminu(int $pracownik, string $data, int $start, int $czas, ?int $klient = null): ?string
{
    if (!data_poprawna($data)) {
        return 'Podaj poprawną datę.';
    }
    if ($czas < 1 || $start < 0 || $start + $czas > 1440) {
        return 'Ten termin wykracza poza jedną dobę.';
    }
    $teraz = time();
    if (strtotime($data . ' ' . godz($start) . ':00') <= $teraz) {
        return 'Nie można rezerwować terminu z przeszłości.';
    }
    if (strtotime($data . ' 12:00:00') > $teraz + MAKS_DNI_NAPRZOD * 86400) {
        return 'Rezerwacje przyjmujemy najwyżej ' . MAKS_DNI_NAPRZOD . ' dni naprzód.';
    }
    $koniec = $start + $czas;
    if (!w_godzinach_pracy($start, $koniec, przedzialy_pracy($pracownik, $data))) {
        return 'Pracownik nie pracuje w tych godzinach. Wybierz jeden z wolnych terminów.';
    }
    if (nachodzi_na_liste($start, $koniec, zajete_pracownika($pracownik, $data))) {
        return 'Ten termin jest już zajęty (całkowicie lub częściowo). Wybierz inny.';
    }
    if ($klient !== null && nachodzi_na_liste($start, $koniec, zajete_klienta($klient, $data))) {
        return 'Masz już rezerwację w tym czasie.';
    }
    return null;
}


function wolne_sloty(int $pracownik, string $data, int $czas, ?int $klient = null): array
{
    if (!data_poprawna($data) || $czas < 1) {
        return [];
    }
    $teraz = time();
    if (strtotime($data . ' 23:59:59') <= $teraz || strtotime($data . ' 12:00:00') > $teraz + MAKS_DNI_NAPRZOD * 86400) {
        return [];
    }
    $zajetePracownika = zajete_pracownika($pracownik, $data);
    $zajeteKlienta = $klient !== null ? zajete_klienta($klient, $data) : [];
    $sloty = [];
    foreach (przedzialy_pracy($pracownik, $data) as [$od, $do]) {

        $s = (int) (ceil($od / KROK_SLOTOW_MIN) * KROK_SLOTOW_MIN);
        for (; $s + $czas <= $do; $s += KROK_SLOTOW_MIN) {
            if (strtotime($data . ' ' . godz($s) . ':00') <= $teraz) {
                continue;
            }
            if (nachodzi_na_liste($s, $s + $czas, $zajetePracownika) || nachodzi_na_liste($s, $s + $czas, $zajeteKlienta)) {
                continue;
            }
            $sloty[] = godz($s);
        }
    }
    return $sloty;
}






function minuta_bezwzgledna(string $data, int $minuty): int
{
    return intdiv((int) strtotime($data . ' 00:00:00 UTC'), 60) + $minuty;
}

function data_plus_dni(string $data, int $dni): string
{
    return date('Y-m-d', (int) strtotime($data . ' 12:00:00 UTC') + $dni * 86400);
}


function zajete_auta(int $usluga): array
{
    $st = db()->prepare(
        "SELECT data_rezerwacji, data_zakonczenia, godzina_rozpoczecia, godzina_zakonczenia FROM rezerwacje
         WHERE id_uslugi = ? AND status <> 'anulowane'"
    );
    $st->execute([$usluga]);
    return array_map(
        fn($r) => [
            minuta_bezwzgledna($r['data_rezerwacji'], minuty($r['godzina_rozpoczecia'])),
            minuta_bezwzgledna($r['data_zakonczenia'], minuty($r['godzina_zakonczenia'])),
        ],
        $st->fetchAll()
    );
}



function blad_wynajmu(int $pracownik, int $usluga, string $data, int $start, int $doby): ?string
{
    if (!data_poprawna($data)) {
        return 'Podaj poprawną datę odbioru.';
    }
    if ($doby < 1 || $doby > MAKS_DOB) {
        return 'Liczba dób musi mieścić się w przedziale 1–' . MAKS_DOB . '.';
    }
    if ($start < 0 || $start + PRZEKAZANIE_MIN > 1440) {
        return 'Ta godzina odbioru jest niedozwolona.';
    }
    $teraz = time();
    if (strtotime($data . ' ' . godz($start) . ':00') <= $teraz) {
        return 'Nie można rezerwować terminu z przeszłości.';
    }
    if (strtotime($data . ' 12:00:00') > $teraz + MAKS_DNI_NAPRZOD * 86400) {
        return 'Rezerwacje przyjmujemy najwyżej ' . MAKS_DNI_NAPRZOD . ' dni naprzód.';
    }
    $koniecPrzekazania = $start + PRZEKAZANIE_MIN;
    if (!w_godzinach_pracy($start, $koniecPrzekazania, przedzialy_pracy($pracownik, $data))) {
        return 'Pracownik nie wydaje aut o tej godzinie. Wybierz jeden z wolnych terminów.';
    }
    $dataZwrotu = data_plus_dni($data, $doby);
    if (!w_godzinach_pracy($start, $koniecPrzekazania, przedzialy_pracy($pracownik, $dataZwrotu))) {
        return "W dniu zwrotu ({$dataZwrotu}) pracownik nie przyjmuje aut o godzinie " . godz($start) . '. Zmień godzinę odbioru, liczbę dób albo pracownika.';
    }
    if (nachodzi_na_liste($start, $koniecPrzekazania, zajete_pracownika($pracownik, $data))) {
        return 'Pracownik jest w tym czasie zajęty. Wybierz inną godzinę odbioru.';
    }
    if (nachodzi_na_liste($start, $koniecPrzekazania, zajete_pracownika($pracownik, $dataZwrotu))) {
        return "W dniu zwrotu ({$dataZwrotu}) pracownik jest o tej godzinie zajęty. Zmień godzinę odbioru lub liczbę dób.";
    }
    $od = minuta_bezwzgledna($data, $start);
    $do = minuta_bezwzgledna($dataZwrotu, $start);
    if (nachodzi_na_liste($od, $do, zajete_auta($usluga))) {
        return 'To auto jest już zarezerwowane w całości lub części tego okresu. Wybierz inne daty albo inne auto.';
    }
    return null;
}


function wolne_sloty_wynajmu(int $pracownik, int $usluga, string $data, int $doby): array
{
    if (!data_poprawna($data) || $doby < 1 || $doby > MAKS_DOB || $data < date('Y-m-d')) {
        return [];
    }
    $sloty = [];
    foreach (przedzialy_pracy($pracownik, $data) as [$od, $do]) {
        $s = (int) (ceil($od / KROK_SLOTOW_MIN) * KROK_SLOTOW_MIN);
        for (; $s + PRZEKAZANIE_MIN <= $do; $s += KROK_SLOTOW_MIN) {
            if (blad_wynajmu($pracownik, $usluga, $data, $s, $doby) === null) {
                $sloty[] = godz($s);
            }
        }
    }
    return $sloty;
}

