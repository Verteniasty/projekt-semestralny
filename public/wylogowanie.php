<?php
require __DIR__ . '/../src/bootstrap.php';

if (metoda_post() && csrf_sprawdz()) {
    wyczysc_sesje();
    flash('ok', 'Wylogowano pomyślnie.');
}
redirect('Strona-glowna.php');
