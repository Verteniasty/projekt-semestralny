<?php



function blad_imienia(string $v, string $pole): ?string
{
    if ($v === '') {
        return "Pole „{$pole}” jest wymagane.";
    }
    if (!preg_match('/^\p{L}[\p{L} \'\-]{0,39}$/u', $v)) {
        return "Pole „{$pole}” może zawierać tylko litery, spację i myślnik (maks. 40 znaków).";
    }
    return null;
}

function blad_emaila(string $v): ?string
{
    if ($v === '') {
        return 'Podaj adres e-mail.';
    }
    if (mb_strlen($v) > 60 || !filter_var($v, FILTER_VALIDATE_EMAIL)) {
        return 'Podaj poprawny adres e-mail (maks. 60 znaków).';
    }
    return null;
}

function normalizuj_telefon(string $v): string
{
    return preg_replace('/[ \-]/', '', $v);
}

function blad_telefonu(string $v): ?string
{
    if ($v === '') {
        return 'Podaj numer telefonu.';
    }
    if (!preg_match('/^\+?[0-9]{9,15}$/', normalizuj_telefon($v))) {
        return 'Podaj poprawny numer telefonu (9–15 cyfr, może zaczynać się od +).';
    }
    return null;
}

function blad_hasla(string $h): ?string
{
    if (strlen($h) < 8) {
        return 'Hasło musi mieć co najmniej 8 znaków.';
    }
    if (strlen($h) > 72) {
        return 'Hasło może mieć maksymalnie 72 znaki.';
    }
    if (!preg_match('/\p{L}/u', $h) || !preg_match('/\d/', $h)) {
        return 'Hasło musi zawierać co najmniej jedną literę i jedną cyfrę.';
    }
    return null;
}


function bledy_danych_osobowych(array $dane): array
{
    return array_filter([
        'imie'     => blad_imienia($dane['imie'], 'imię'),
        'nazwisko' => blad_imienia($dane['nazwisko'], 'nazwisko'),
        'telefon'  => blad_telefonu($dane['telefon']),
    ]);
}



function bledy_nowego_hasla(string $haslo, string $powtorzenie, string $pole = 'haslo', string $poleDrugie = 'haslo2'): array
{
    $bledy = [$pole => blad_hasla($haslo)];
    if ($bledy[$pole] === null && $haslo !== $powtorzenie) {
        $bledy[$poleDrugie] = 'Hasła nie są takie same.';
    }
    return array_filter($bledy);
}




function blad_tekstu(string $v, string $pole, int $min, int $max): ?string
{
    $dl = mb_strlen($v);
    if ($dl < $min) {
        return $min === 1 ? "Pole „{$pole}” jest wymagane." : "Pole „{$pole}” musi mieć co najmniej {$min} znaki.";
    }
    if ($dl > $max) {
        return "Pole „{$pole}” może mieć maksymalnie {$max} znaków.";
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v)) {
        return "Pole „{$pole}” zawiera niedozwolone znaki.";
    }
    return null;
}


function blad_ceny(string $v): ?string
{
    if ($v === '') {
        return 'Podaj cenę.';
    }
    if (!preg_match('/^\d{1,9}([.,]\d{1,2})?$/', $v)) {
        return 'Cena musi być liczbą, np. 120 lub 120,50.';
    }
    if ((float) str_replace(',', '.', $v) <= 0) {
        return 'Cena musi być większa od zera.';
    }
    return null;
}

function cena_z_tekstu(string $v): string
{
    return number_format((float) str_replace(',', '.', $v), 2, '.', '');
}


function blad_czasu_trwania(string $v): ?string
{
    if ($v === '' || !ctype_digit($v)) {
        return 'Czas trwania podaj w minutach (liczba całkowita).';
    }
    if ((int) $v < 5 || (int) $v > 1440) {
        return 'Czas trwania musi mieścić się w przedziale 5–1440 minut.';
    }
    return null;
}


function id_z_tekstu(string $v): int
{
    return ctype_digit($v) && strlen($v) <= 9 ? (int) $v : 0;
}


function data_poprawna(string $v): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return false;
    }
    [$r, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $r);
}


function godzina_poprawna(string $v): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v);
}



function like_escape(string $v): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $v);
}
