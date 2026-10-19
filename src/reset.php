<?php

const TOKEN_WAZNOSC_MIN = 60;   
const TOKEN_ODSTEP_SEK  = 60;   

function adres_aplikacji(): string
{
    $baza = $GLOBALS['config']['base_url'] ?? '';
    if ($baza !== '') {
        return rtrim($baza, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    $schemat = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $katalog = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $schemat . '://' . $host . $katalog;
}

function wyslij_link_resetu(string $email): ?string
{
    $st = db()->prepare('SELECT id, imie FROM uzytkownicy WHERE email = ? AND aktywne = 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u) {
        return null;
    }

    $teraz = time();
    db()->prepare('DELETE FROM tokeny_hasla WHERE wazny_do < ?')->execute([date('Y-m-d H:i:s', $teraz)]);

    $st = db()->prepare('SELECT MAX(utworzony) FROM tokeny_hasla WHERE id_uzytkownika = ?');
    $st->execute([$u['id']]);
    $ostatni = $st->fetchColumn();
    if ($ostatni && strtotime($ostatni) > $teraz - TOKEN_ODSTEP_SEK) {
        return null;
    }

    db()->prepare('DELETE FROM tokeny_hasla WHERE id_uzytkownika = ?')->execute([$u['id']]);

    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO tokeny_hasla (id_uzytkownika, token_hash, wazny_do, utworzony) VALUES (?, ?, ?, ?)')
        ->execute([
            $u['id'],
            hash('sha256', $token),
            date('Y-m-d H:i:s', $teraz + TOKEN_WAZNOSC_MIN * 60),
            date('Y-m-d H:i:s', $teraz),
        ]);

    $link = adres_aplikacji() . '/nowe-haslo.php?token=' . $token;
    wyslij_mail(
        $email,
        'Reset hasła – RentLoudCars',
        "Cześć {$u['imie']},\n\nAby ustawić nowe hasło, otwórz ten link (ważny " . TOKEN_WAZNOSC_MIN . " minut, działa jeden raz):\n\n"
        . $link . "\n\nJeśli to nie Ty prosiłeś o reset, zignoruj tę wiadomość. Twoje hasło się nie zmieni."
    );
    return $link;
}

function znajdz_wazny_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $st = db()->prepare('SELECT id, id_uzytkownika FROM tokeny_hasla WHERE token_hash = ? AND wazny_do > ?');
    $st->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
    return $st->fetch() ?: null;
}

function zuzyj_token_i_ustaw_haslo(string $token, string $noweHaslo): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $wpis = znajdz_wazny_token($token);
        if (!$wpis) {
            $pdo->rollBack();
            return false;
        }
        $del = $pdo->prepare('DELETE FROM tokeny_hasla WHERE id = ?');
        $del->execute([$wpis['id']]);
        if ($del->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        zmien_haslo((int) $wpis['id_uzytkownika'], $noweHaslo);
        $pdo->prepare('DELETE FROM tokeny_hasla WHERE id_uzytkownika = ?')->execute([$wpis['id_uzytkownika']]);
        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
