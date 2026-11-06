<?php
require __DIR__ . "/../src/bootstrap.php";

$u = wymagaj_roli('klient');

$blad  = '';

function zarezerwuj(array $data, array $user): array
{
    $st = db()->prepare(
        'INSERT INTO rezerwacje (id_uzytkownika, id_pracownika, id_uslugi, od_data_rezerwacji, do_data_rezerwacji, komentarz)
         VALUES (?, 1, 1, ?, ?, ?);'
    );
    $st->execute([
        $user['id'],
        $data['from'],
        $data['to'],
        $data['komentarz']

    ]);

    return ['ok' => true];
}

if (metoda_post()) {

    $json = file_get_contents('php://input');

    $_POST = json_decode($json, true);

    $car = post('car');
    $from = post('from');
    $to = post('to');
    $name = post('name');
    $email = post('email');
    $komentarz = post('komentarz');
    $csrf = post('csrf');

    $data = 
    [
        'car' => $car,
        'from' => $from,
        'to' => $to,
        'name' => $name,
        'email' => $email,
        'komentarz' => $komentarz
    ];

    if (!csrf_sprawdz()) {
        $blad = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
    } elseif (!$car || !$from || !$to || !$name || !$email) {
        $blad = 'Podaj' . (!$car && " samochód" || !$from && " date rezerwacji od" || !$to && " date rezerwacji do" || !$name && " imię i nazwisko" || !$email && " email") . '.';
    } else {
        $wynik = zarezerwuj($data, $u);
        if (!empty($wynik['ok'])) {
            flash('ok', 'Pomyślnie złożono rezerwację.');
            // redirect("Strona-glowna.php");
        }
        // $blad = $wynik['blad'];
    }
}