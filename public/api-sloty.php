<?php
require __DIR__ . '/../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function odpowiedz_json(array $dane, int $kod = 200): never
{
    http_response_code($kod);
    echo json_encode($dane, JSON_UNESCAPED_UNICODE);
    exit;
}


$u = aktualny_uzytkownik();
if (!$u) {
    odpowiedz_json(['blad' => 'Zaloguj się, aby zobaczyć wolne terminy.'], 401);
}
if ($u['rola'] !== 'klient') {
    odpowiedz_json(['blad' => 'Rezerwacje mogą składać tylko klienci.'], 403);
}

$usluga = usluga_aktywna(id_z_tekstu(pobierz('usluga')));
$pracownik = id_z_tekstu(pobierz('pracownik'));
$data = pobierz('data');
if (!$usluga || !isset(pracownicy_uslugi((int) $usluga['id'])[$pracownik]) || !data_poprawna($data)) {
    odpowiedz_json(['blad' => 'Wybierz usługę, pracownika i poprawną datę.'], 400);
}

$wynajem = $usluga['typ'] === 'doby';
$doby = 1;
if ($wynajem) {
    $d = pobierz('doby');
    $doby = ctype_digit($d) && strlen($d) <= 3 ? (int) $d : 0;
    if ($doby < 1 || $doby > MAKS_DOB) {
        odpowiedz_json(['blad' => 'Liczba dób musi mieścić się w przedziale 1–' . MAKS_DOB . '.'], 400);
    }
    $sloty = wolne_sloty_wynajmu($pracownik, (int) $usluga['id'], $data, $doby);
} else {
    $sloty = wolne_sloty($pracownik, $data, (int) $usluga['czas_trwania'], (int) $u['id']);
}

$komunikat = '';
if (!$sloty) {
    if ($data < date('Y-m-d')) {
        $komunikat = 'Nie można rezerwować terminów z przeszłości.';
    } elseif (!przedzialy_pracy($pracownik, $data)) {
        $komunikat = 'Tego dnia pracownik nie pracuje. Wybierz inny dzień.';
    } elseif ($wynajem) {
        $komunikat = 'Brak wolnych godzin odbioru na te daty: auto może być zajęte albo w dniu zwrotu pracownik nie pracuje. Zmień dzień lub liczbę dób.';
    } else {
        $komunikat = 'Brak wolnych terminów tego dnia dla tej usługi. Wybierz inny dzień lub pracownika.';
    }
}
odpowiedz_json([
    'sloty'      => $sloty,
    'koniec'     => $wynajem ? $sloty : array_map(fn($s) => godz(minuty($s) + (int) $usluga['czas_trwania']), $sloty),
    'typ'        => $usluga['typ'],
    'doby'       => $doby,
    'zwrot_data' => $wynajem ? data_plus_dni($data, $doby) : null,
    'razem'      => strip_tags(cena_txt((float) $usluga['cena'] * ($wynajem ? $doby : 1))),
    'doba_txt'   => $wynajem ? doby_txt($doby) : null,
    'komunikat'  => $komunikat,
]);
