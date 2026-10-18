<?php
require __DIR__ . '/../src/bootstrap.php';

if ($u = aktualny_uzytkownik()) {
    redirect(panel_roli($u['rola']));
}

$email = '';
$blad  = '';

if (metoda_post()) {
    $email = post('email');
    $haslo = post('haslo', false);

    if (!csrf_sprawdz()) {
        $blad = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
    } elseif ($email === '' || $haslo === '') {
        $blad = 'Podaj adres e-mail i hasło.';
    } else {
        $wynik = zaloguj($email, $haslo);
        if (!empty($wynik['ok'])) {
            flash('ok', 'Zalogowano pomyślnie. Witaj, ' . $_SESSION['imie'] . '!');
            redirect(panel_roli($wynik['rola']));   // każda rola trafia do swojego panelu
        }
        $blad = $wynik['blad'];
    }
}

render_naglowek('Logowanie – RentLoudCars', 'logowanie');
?>
    <main id="main" class="block">
        <div class="wrap auth-card">
            <h1>Logowanie</h1>
            <form method="post" action="logowanie.php" novalidate>
                <?= csrf_pole() ?>
<?php if ($blad !== ''): ?>
                <p class="komunikat komunikat-err" role="alert"><?= e($blad) ?></p>
<?php endif; ?>
                <?php pole('email', 'Adres e-mail', 'email', $email, null, 'required autocomplete="username"'); ?>
                <?php pole('haslo', 'Hasło', 'password', '', null, 'required autocomplete="current-password"'); ?>
                <button type="submit" class="btn">Zaloguj się</button>
            </form>
            <p class="auth-alt">Nie masz konta? <a href="rejestracja.php">Zarejestruj się</a></p>
        </div>
    </main>
<?php render_stopka(); ?>
