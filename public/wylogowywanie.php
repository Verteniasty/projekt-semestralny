<?php
require __DIR__ . '/../src/bootstrap.php';

// Wylogowanie tylko metodą POST z tokenem CSRF, żeby obca strona nie wylogowała użytkownika sama z siebie.
if (metoda_post() && csrf_sprawdz()) {
    wyczysc_sesje();
    flash('ok', 'Wylogowano pomyślnie.');
}
redirect('Strona-glowna.php');
