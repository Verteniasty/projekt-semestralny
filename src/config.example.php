<?php
// Skopiuj ten plik jako src/config.php i wpisz własne dane.
// src/config.php jest w .gitignore, więc hasła nie trafiają do repozytorium.
return [
    'db_host'  => 'localhost',
    'db_name'  => 'Wypozyczalnia',
    'db_user'  => 'root',
    'db_pass'  => '',        // XAMPP: puste hasło
    'debug'    => false,     // true tylko lokalnie: pokazuje błędy PHP

    // Reset hasła: adres aplikacji w linku z maila (puste = wykryj automatycznie)
    'base_url' => '',        // np. 'http://localhost/projekt-semestralny/public'
    // 'plik' = maile zapisują się w storage/maile.log (lokalnie), 'mail' = prawdziwa funkcja mail()
    'mail_tryb' => 'plik',
    'mail_od'   => 'no-reply@rentloudcars.pl',
];
