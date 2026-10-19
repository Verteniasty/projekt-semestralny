<?php

const MAX_PROB    = 5;    
const BLOKADA_MIN = 15;    
const SESJA_CZAS  = 1800;  

const DUMMY_HASH = '$2y$10$gpKoVlT3.th9OqILInd6KuewTmI/2jIBj52IEWqE55s6ITjiZ/tGm';

function panel_roli(string $rola): string
{
    return match ($rola) {
        'administrator' => 'panel-admina.php',
        'pracownik'     => 'panel-pracownika.php',
        default         => 'panel-klienta.php',
    };
}

function wyczysc_sesje(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function aktualny_uzytkownik(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare(
            'SELECT id, imie, nazwisko, email, numer_telefonu, rola, wersja_sesji
             FROM uzytkownicy WHERE id = ? AND aktywne = 1'
        );
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if ($u === null) {
            wyczysc_sesje();
            flash('err', 'Twoje konto jest nieaktywne lub nie istnieje.');
        } elseif ((int) $u['wersja_sesji'] !== (int) ($_SESSION['wersja'] ?? -1)) {
            $u = null;
            wyczysc_sesje();
            flash('err', 'Zostałeś wylogowany, bo zmieniono hasło lub wylogowano Cię ze wszystkich urządzeń. Zaloguj się ponownie.');
        } else {
            $_SESSION['rola']     = $u['rola'];
            $_SESSION['imie']     = $u['imie'];
            $_SESSION['nazwisko'] = $u['nazwisko'];
        }
    }
    return $u;
}

function wymagaj_logowania(): array
{
    $u = aktualny_uzytkownik();
    if ($u === null) {
        flash('err', 'Zaloguj się, aby zobaczyć tę stronę.');
        redirect('logowanie.php');
    }
    return $u;
}
function tylko_dla_gosci(): void
{
    if ($u = aktualny_uzytkownik()) {
        redirect(panel_roli($u['rola']));
    }
}

function wymagaj_roli(string ...$role): array
{
    $u = wymagaj_logowania();
    if (!in_array($u['rola'], $role, true)) {
        http_response_code(403);
        render_naglowek('Brak dostępu – RentLoudCars');
        echo '<main id="main" class="block"><div class="wrap auth-card">'
           . '<h1>Brak dostępu</h1><p>Nie masz uprawnień do tej strony.</p>'
           . '<p><a class="btn" href="' . e(panel_roli($u['rola'])) . '">Wróć do mojego konta</a></p>'
           . '</div></main>';
        render_stopka();
        exit;
    }
    return $u;
}

function zaloguj(string $email, string $haslo): array
{
    $blad = 'Nieprawidłowy e-mail lub hasło.';   

    $st = db()->prepare(
        'SELECT id, imie, nazwisko, haslo, rola, aktywne, nieudane_proby, zablokowany_do, wersja_sesji
         FROM uzytkownicy WHERE email = ?'
    );
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u) {
        password_verify($haslo, DUMMY_HASH);
        return ['blad' => $blad];
    }

   
    $blokadaDo = $u['zablokowany_do'] ? (int) strtotime($u['zablokowany_do']) : 0;
    if ($blokadaDo > time()) {
        $min = max(1, (int) ceil(($blokadaDo - time()) / 60));
        return ['blad' => "Konto jest tymczasowo zablokowane po zbyt wielu błędnych próbach. Spróbuj za około {$min} min."];
    }

    if (!password_verify($haslo, $u['haslo'])) {
        if ($u['nieudane_proby'] + 1 >= MAX_PROB) {
            db()->prepare('UPDATE uzytkownicy SET nieudane_proby = 0, zablokowany_do = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s', time() + BLOKADA_MIN * 60), $u['id']]);
        } else {
            db()->prepare('UPDATE uzytkownicy SET nieudane_proby = nieudane_proby + 1 WHERE id = ?')
                ->execute([$u['id']]);
        }
        return ['blad' => $blad];
    }

    if (!$u['aktywne']) {
        return ['blad' => 'To konto jest nieaktywne. Skontaktuj się z obsługą.'];
    }

    
    db()->prepare('UPDATE uzytkownicy SET nieudane_proby = 0, zablokowany_do = NULL WHERE id = ?')
        ->execute([$u['id']]);
    if (password_needs_rehash($u['haslo'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?')
            ->execute([password_hash($haslo, PASSWORD_DEFAULT), $u['id']]);
    }

    session_regenerate_id(true);  
    $_SESSION = [
        'uid'      => (int) $u['id'],
        'wersja'   => (int) $u['wersja_sesji'],
        'rola'     => $u['rola'],
        'imie'     => $u['imie'],
        'nazwisko' => $u['nazwisko'],
        'ostatnia' => time(),
    ];
    return ['ok' => true, 'rola' => $u['rola'], 'imie' => $u['imie']];
}
function uniewaznij_sesje(int $idUzytkownika): int
{
    db()->prepare('UPDATE uzytkownicy SET wersja_sesji = wersja_sesji + 1 WHERE id = ?')
        ->execute([$idUzytkownika]);
    $st = db()->prepare('SELECT wersja_sesji FROM uzytkownicy WHERE id = ?');
    $st->execute([$idUzytkownika]);
    return (int) $st->fetchColumn();
}

function zmien_haslo(int $idUzytkownika, string $noweHaslo): int
{
    db()->prepare('UPDATE uzytkownicy SET haslo = ?, nieudane_proby = 0, zablokowany_do = NULL WHERE id = ?')
        ->execute([password_hash($noweHaslo, PASSWORD_DEFAULT), $idUzytkownika]);
    return uniewaznij_sesje($idUzytkownika);
}
