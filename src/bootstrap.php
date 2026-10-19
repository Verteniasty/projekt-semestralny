<?php

date_default_timezone_set('Europe/Warsaw');  
$plikConfig = __DIR__ . '/config.php';
if (!is_file($plikConfig)) {
    http_response_code(500);
    exit('Brak pliku src/config.php. Skopiuj src/config.example.php jako src/config.php i uzupełnij dane bazy.');
}
$GLOBALS['config'] = require $plikConfig;

error_reporting(E_ALL);
ini_set('display_errors', !empty($GLOBALS['config']['debug']) ? '1' : '0');
ini_set('zend.exception_ignore_args', '1'); 

require __DIR__ . '/pomocnicze.php';
require __DIR__ . '/db.php';
require __DIR__ . '/walidacja.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/reset.php';
require __DIR__ . '/layout.php';

set_exception_handler(function (Throwable $ex) {
    error_log((string) $ex);
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>Błąd – RentLoudCars</title><link rel="stylesheet" href="style.css"></head>'
       . '<body><main class="block"><div class="wrap auth-card"><h1>Coś poszło nie tak</h1>'
       . '<p>Spróbuj ponownie za chwilę.</p>'
       . '<p><a class="btn" href="Strona-glowna.php">Wróć na stronę główną</a></p>';
    if (!empty($GLOBALS['config']['debug'])) {
        echo '<pre>' . e((string) $ex) . '</pre>';
    }
    echo '</div></main></body></html>';
});


header('Cache-Control: no-store, max-age=0');  
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
     . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
     . "img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");


$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('rentloudcars');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

if (isset($_SESSION['uid'])) {
    if (time() - ($_SESSION['ostatnia'] ?? 0) > SESJA_CZAS) {
        wyczysc_sesje();
        flash('err', 'Sesja wygasła z powodu bezczynności. Zaloguj się ponownie.');
    } else {
        $_SESSION['ostatnia'] = time();
    }
}
