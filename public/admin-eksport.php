<?php

require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();


function komorka_csv($v): string
{
    $v = (string) $v;
    return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
}

$f = filtry_rezerwacji_z_get();
$wiersze = lista_rezerwacji($f, 0, 0, true);
loguj_admina('eksport_csv', 'Wyeksportowano rezerwacje do CSV (' . count($wiersze) . ' wierszy)');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="rezerwacje-' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID', 'Data odbioru', 'Godzina', 'Do', 'Data zwrotu', 'Doby', 'Klient', 'E-mail klienta', 'Telefon klienta', 'Usługa', 'Pracownik', 'Cena', 'Status', 'Komentarz'], ';', '"', '');
foreach ($wiersze as $r) {
    fputcsv($out, array_map('komorka_csv', [
        $r['id'], $r['data_rezerwacji'], substr($r['godzina_rozpoczecia'], 0, 5), substr($r['godzina_zakonczenia'], 0, 5), $r['data_zakonczenia'], (string) $r['liczba_dob'],
        $r['k_imie'] . ' ' . $r['k_nazwisko'], $r['k_email'], $r['k_tel'], $r['usluga'], $r['p_imie'] . ' ' . $r['p_nazwisko'],
        number_format((float) $r['cena'], 2, ',', ''), etykieta_statusu($r['status']), (string) $r['komentarz'],
    ]), ';', '"', '');
}
fclose($out);
