<?php
require __DIR__ . '/../src/bootstrap.php';
tylko_dla_gosci();

$email   = '';
$blad    = '';
$wyslano = false;
$link    = null;

if (metoda_post()) {
    $email = post('email');
    if (!csrf_sprawdz()) {
        $blad = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
    } elseif ($e = blad_emaila($email)) {
        $blad = $e;
    } else {
        $link    = wyslij_link_resetu($email);
        $wyslano = true;
    }
}

render_naglowek('Reset hasła – RentLoudCars', 'logowanie');
?>
    <main id="main" class="block">
        <div class="wrap auth-card">
            <h1>Reset hasła</h1>
<?php if ($wyslano): ?>
            <p class="komunikat komunikat-ok" role="status">Jeśli konto z takim adresem istnieje, wysłaliśmy na nie wiadomość z linkiem do ustawienia nowego hasła. Link działa przez <?= TOKEN_WAZNOSC_MIN ?> minut.</p>
<?php if ($link !== null && !empty($GLOBALS['config']['debug'])): ?>
            <p class="komunikat">Tryb deweloperski: <a href="<?= e($link) ?>">link do resetu</a> (wiadomość jest też w storage/maile.log).</p>
<?php endif; ?>
            <p class="auth-alt"><a href="logowanie.php">Wróć do logowania</a></p>
<?php else: ?>
            <p>Podaj adres e-mail konta. Wyślemy link do ustawienia nowego hasła.</p>
            <form method="post" action="reset-hasla.php" novalidate>
                <?= csrf_pole() ?>
<?php if ($blad !== ''): ?>
                <p class="komunikat komunikat-err" role="alert"><?= e($blad) ?></p>
<?php endif; ?>
                <?php pole('email', 'Adres e-mail', 'email', $email, null, 'required maxlength="60" autocomplete="email"'); ?>
                <button type="submit" class="btn">Wyślij link</button>
            </form>
            <p class="auth-alt"><a href="logowanie.php">Wróć do logowania</a></p>
<?php endif; ?>
        </div>
    </main>
<?php render_stopka(); ?>
