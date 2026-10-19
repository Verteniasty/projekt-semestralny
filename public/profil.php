<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_logowania();  

$dane   = ['imie' => $u['imie'], 'nazwisko' => $u['nazwisko'], 'telefon' => $u['numer_telefonu']];
$bledyD = [];   
$bledyH = [];   

if (metoda_post()) {
    if (!csrf_sprawdz()) {
        flash('err', 'Formularz wygasł. Spróbuj ponownie.');
        redirect('profil.php');
    }
    $akcja = post('akcja');

    if ($akcja === 'dane') {
        foreach ($dane as $klucz => $_) {
            $dane[$klucz] = post($klucz);
        }
        $bledyD = bledy_danych_osobowych($dane);
        if (!$bledyD) {
            db()->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, numer_telefonu = ? WHERE id = ?')
                ->execute([$dane['imie'], $dane['nazwisko'], normalizuj_telefon($dane['telefon']), $u['id']]);
            flash('ok', 'Dane zostały zapisane.');
            redirect('profil.php');
        }
    } elseif ($akcja === 'haslo') {
        $stare = post('stare', false);
        $nowe  = post('nowe', false);
        $nowe2 = post('nowe2', false);

        $hash = (string) db_wartosc('SELECT haslo FROM uzytkownicy WHERE id = ?', [$u['id']]);
        if (!password_verify($stare, $hash)) {
            $bledyH['stare'] = 'Obecne hasło jest nieprawidłowe.';
        }
        $bledyH += bledy_nowego_hasla($nowe, $nowe2, 'nowe', 'nowe2');
        if (!$bledyH) {
            $_SESSION['wersja'] = zmien_haslo((int) $u['id'], $nowe);
            session_regenerate_id(true);
            flash('ok', 'Hasło zostało zmienione. Inne urządzenia zostały wylogowane.');
            redirect('profil.php');
        }
    } elseif ($akcja === 'wyloguj_wszedzie') {
        $_SESSION['wersja'] = uniewaznij_sesje((int) $u['id']);
        session_regenerate_id(true);
        flash('ok', 'Wylogowano ze wszystkich innych urządzeń.');
        redirect('profil.php');
    }
}

render_naglowek('Profil – RentLoudCars', 'profil');
?>
    <main id="main" class="block">
        <div class="wrap narrow">
            <h1 class="stronicowy">Mój profil</h1>

            <h2>Dane osobowe</h2>
            <form method="post" action="profil.php" novalidate>
                <?= csrf_pole() ?>
                <input type="hidden" name="akcja" value="dane">
                <p class="hint">Adres e-mail: <strong><?= e($u['email']) ?></strong> (nie można go zmienić).</p>
                <div class="grid2">
                    <?php pole('imie', 'Imię', 'text', $dane['imie'], $bledyD['imie'] ?? null, 'required maxlength="40"'); ?>
                    <?php pole('nazwisko', 'Nazwisko', 'text', $dane['nazwisko'], $bledyD['nazwisko'] ?? null, 'required maxlength="40"'); ?>
                </div>
                <?php pole('telefon', 'Telefon', 'tel', $dane['telefon'], $bledyD['telefon'] ?? null, 'required maxlength="20"'); ?>
                <button type="submit" class="btn">Zapisz zmiany</button>
            </form>

            <h2 class="odstep">Zmiana hasła</h2>
            <form method="post" action="profil.php" novalidate>
                <?= csrf_pole() ?>
                <input type="hidden" name="akcja" value="haslo">
                <?php pole('stare', 'Obecne hasło', 'password', '', $bledyH['stare'] ?? null, 'required autocomplete="current-password"'); ?>
                <div class="grid2">
                    <?php pole('nowe', 'Nowe hasło', 'password', '', $bledyH['nowe'] ?? null, 'required minlength="8" autocomplete="new-password"'); ?>
                    <?php pole('nowe2', 'Powtórz nowe hasło', 'password', '', $bledyH['nowe2'] ?? null, 'required autocomplete="new-password"'); ?>
                </div>
                <button type="submit" class="btn">Zmień hasło</button>
            </form>

            <h2 class="odstep">Bezpieczeństwo konta</h2>
            <form method="post" action="profil.php">
                <?= csrf_pole() ?>
                <input type="hidden" name="akcja" value="wyloguj_wszedzie">
                <p class="hint">Jeśli logowałeś się na cudzym komputerze, możesz wylogować wszystkie inne urządzenia. Ta sesja zostanie aktywna.</p>
                <button type="submit" class="btn alt">Wyloguj z innych urządzeń</button>
            </form>
        </div>
    </main>
<?php render_stopka(); ?>
