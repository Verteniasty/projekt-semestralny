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
