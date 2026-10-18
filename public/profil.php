<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_logowania();   // dostępne dla każdej roli, ale każdy edytuje wyłącznie własne dane

$dane    = ['imie' => $u['imie'], 'nazwisko' => $u['nazwisko'], 'telefon' => $u['numer_telefonu']];
$bledyD  = [];   // błędy formularza danych
$bledyH  = [];   // błędy formularza zmiany hasła

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
        $bledyD = array_filter([
            'imie'     => blad_imienia($dane['imie'], 'imię'),
            'nazwisko' => blad_imienia($dane['nazwisko'], 'nazwisko'),
            'telefon'  => blad_telefonu($dane['telefon']),
        ]);
        if (!$bledyD) {
            // Warunek WHERE id = ? z sesji: użytkownik zmienia tylko swoje konto, rolę i e-mail pomijamy.
            db()->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, numer_telefonu = ? WHERE id = ?')
                ->execute([$dane['imie'], $dane['nazwisko'], normalizuj_telefon($dane['telefon']), $u['id']]);
            flash('ok', 'Dane zostały zapisane.');
            redirect('profil.php');
        }
    } elseif ($akcja === 'haslo') {
        $stare = post('stare', false);
        $nowe  = post('nowe', false);
        $nowe2 = post('nowe2', false);

        $st = db()->prepare('SELECT haslo FROM uzytkownicy WHERE id = ?');
        $st->execute([$u['id']]);
        $hash = (string) $st->fetchColumn();

        if (!password_verify($stare, $hash)) {
            $bledyH['stare'] = 'Obecne hasło jest nieprawidłowe.';
        }
        $bledyH['nowe'] = blad_hasla($nowe);
        if ($bledyH['nowe'] === null && $nowe !== $nowe2) {
            $bledyH['nowe2'] = 'Hasła nie są takie same.';
        }
        $bledyH = array_filter($bledyH);
        if (!$bledyH) {
            db()->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?')
                ->execute([password_hash($nowe, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('ok', 'Hasło zostało zmienione.');
            redirect('profil.php');
        }
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
        </div>
    </main>
<?php render_stopka(); ?>
