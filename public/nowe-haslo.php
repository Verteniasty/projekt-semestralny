<?php
require __DIR__ . '/../src/bootstrap.php';
header('Referrer-Policy: no-referrer');  


$token = metoda_post() ? post('token') : pobierz('token');
$wpis  = znajdz_wazny_token($token);
$bledy = [];

if ($wpis && metoda_post()) {
    if (!csrf_sprawdz()) {
        $bledy['ogolny'] = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
    } else {
        $nowe  = post('nowe', false);
        $nowe2 = post('nowe2', false);
        $bledy = bledy_nowego_hasla($nowe, $nowe2, 'nowe', 'nowe2');
        if (!$bledy) {
            if (zuzyj_token_i_ustaw_haslo($token, $nowe)) {
                flash('ok', 'Hasło zostało zmienione. Zaloguj się nowym hasłem.');
                redirect('logowanie.php');
            }
            $wpis = null;   
        }
    }
}

if (!$wpis) {
    http_response_code(400);
}
render_naglowek('Nowe hasło – RentLoudCars', 'logowanie');
?>
    <main id="main" class="block">
        <div class="wrap auth-card">
            <h1>Nowe hasło</h1>
<?php if (!$wpis): ?>
            <p class="komunikat komunikat-err" role="alert">Ten link jest nieprawidłowy, został już użyty albo wygasł.</p>
            <p><a class="btn" href="reset-hasla.php">Poproś o nowy link</a></p>
<?php else: ?>
            <form method="post" action="nowe-haslo.php" novalidate>
                <?= csrf_pole() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
<?php if (!empty($bledy['ogolny'])): ?>
                <p class="komunikat komunikat-err" role="alert"><?= e($bledy['ogolny']) ?></p>
<?php endif; ?>
                <?php pole('nowe', 'Nowe hasło', 'password', '', $bledy['nowe'] ?? null, 'required minlength="8" autocomplete="new-password"'); ?>
                <?php pole('nowe2', 'Powtórz nowe hasło', 'password', '', $bledy['nowe2'] ?? null, 'required autocomplete="new-password"'); ?>
                <p class="hint">Hasło: min. 8 znaków, co najmniej jedna litera i jedna cyfra.</p>
                <button type="submit" class="btn">Ustaw nowe hasło</button>
            </form>
<?php endif; ?>
        </div>
    </main>
<?php render_stopka(); ?>
