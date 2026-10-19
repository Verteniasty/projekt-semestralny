<?php
require __DIR__ . '/../src/bootstrap.php';
tylko_dla_gosci();   

$dane  = ['imie' => '', 'nazwisko' => '', 'email' => '', 'telefon' => ''];
$bledy = [];

if (metoda_post()) {
    if (!csrf_sprawdz()) {
        $bledy['ogolny'] = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
    } else {
        foreach ($dane as $klucz => $_) {
            $dane[$klucz] = post($klucz);
        }
        $haslo  = post('haslo', false);
        $haslo2 = post('haslo2', false);

        $bledy = bledy_danych_osobowych($dane) + bledy_nowego_hasla($haslo, $haslo2);
        if ($e = blad_emaila($dane['email'])) {
            $bledy['email'] = $e;
        }

        if (!$bledy && db_wartosc('SELECT 1 FROM uzytkownicy WHERE email = ?', [$dane['email']])) {
            $bledy['email'] = 'Konto z tym adresem e-mail już istnieje.';
        }

        if (!$bledy) {
            try {
                db()->prepare(
                    'INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, numer_telefonu, rola)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $dane['imie'],
                    $dane['nazwisko'],
                    strtolower($dane['email']),
                    password_hash($haslo, PASSWORD_DEFAULT),
                    normalizuj_telefon($dane['telefon']),
                    'klient',
                ]);
                flash('ok', 'Konto zostało utworzone. Możesz się teraz zalogować.');
                redirect('logowanie.php');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $bledy['email'] = 'Konto z tym adresem e-mail już istnieje.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}

render_naglowek('Rejestracja – RentLoudCars', 'rejestracja', [], 'Załóż konto w RentLoudCars i rezerwuj auta online.');
?>
    <main id="main" class="block">
        <div class="wrap auth-card">
            <h1>Rejestracja</h1>
            <form method="post" action="rejestracja.php" novalidate>
                <?= csrf_pole() ?>
<?php if (!empty($bledy['ogolny'])): ?>
                <p class="komunikat komunikat-err" role="alert"><?= e($bledy['ogolny']) ?></p>
<?php endif; ?>
                <div class="grid2">
                    <?php pole('imie', 'Imię', 'text', $dane['imie'], $bledy['imie'] ?? null, 'required maxlength="40" autocomplete="given-name"'); ?>
                    <?php pole('nazwisko', 'Nazwisko', 'text', $dane['nazwisko'], $bledy['nazwisko'] ?? null, 'required maxlength="40" autocomplete="family-name"'); ?>
                </div>
                <?php pole('email', 'Adres e-mail', 'email', $dane['email'], $bledy['email'] ?? null, 'required maxlength="60" autocomplete="email"'); ?>
                <?php pole('telefon', 'Telefon', 'tel', $dane['telefon'], $bledy['telefon'] ?? null, 'required maxlength="20" autocomplete="tel"'); ?>
                <div class="grid2">
                    <?php pole('haslo', 'Hasło', 'password', '', $bledy['haslo'] ?? null, 'required minlength="8" autocomplete="new-password"'); ?>
                    <?php pole('haslo2', 'Powtórz hasło', 'password', '', $bledy['haslo2'] ?? null, 'required autocomplete="new-password"'); ?>
                </div>
                <p class="hint">Hasło: min. 8 znaków, co najmniej jedna litera i jedna cyfra.</p>
                <button type="submit" class="btn">Załóż konto</button>
            </form>
            <p class="auth-alt">Masz już konto? <a href="logowanie.php">Zaloguj się</a></p>
        </div>
    </main>
<?php render_stopka(); ?>
