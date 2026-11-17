<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();

const ROLE = ['klient' => 'Klient', 'pracownik' => 'Pracownik', 'administrator' => 'Administrator'];

if (metoda_post()) {
    $wroc = post('wroc');
    sprawdz_csrf_lub_wroc(adres_powrotu('admin-uzytkownicy.php', $wroc, ['q', 'rola', 'strona']));
    $akcja = post('akcja');
    $id = id_z_tekstu(post('id'));
    $st = db()->prepare('SELECT id, imie, nazwisko, rola, aktywne FROM uzytkownicy WHERE id = ?');
    $st->execute([$id]);
    $cel = $st->fetch();

    if (!$cel) {
        flash('err', 'Nie znaleziono takiego użytkownika.');
    } elseif ((int) $cel['id'] === (int) $u['id']) {

        flash('err', 'Nie możesz zmienić własnej roli ani dezaktywować własnego konta.');
    } elseif ($akcja === 'rola') {
        $nowa = post('rola');
        if (!isset(ROLE[$nowa])) {
            flash('err', 'Nieznana rola.');
        } elseif ($nowa !== $cel['rola']) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE uzytkownicy SET rola = ? WHERE id = ?')->execute([$nowa, $id]);
                if ($nowa === 'pracownik') {

                    $ma = db_wartosc('SELECT COUNT(*) FROM pracownicy WHERE id_uzytkownika = ?', [$id]);
                    if ($ma) {
                        $pdo->prepare('UPDATE pracownicy SET aktywne = 1 WHERE id_uzytkownika = ?')->execute([$id]);
                    } else {
                        $pdo->prepare('INSERT INTO pracownicy (id_uzytkownika, opis, aktywne) VALUES (?, ?, 1)')->execute([$id, 'Pracownik']);
                    }
                } elseif ($cel['rola'] === 'pracownik') {

                    $pdo->prepare('UPDATE pracownicy SET aktywne = 0 WHERE id_uzytkownika = ?')->execute([$id]);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            uniewaznij_sesje($id);
            loguj_admina('uzytkownik_rola', "Zmieniono rolę użytkownika #{$id} ({$cel['imie']} {$cel['nazwisko']}): {$cel['rola']} → {$nowa}");
            flash('ok', 'Rola została zmieniona.' . ($nowa === 'pracownik' ? ' Uzupełnij usługi i godziny pracy w zakładce „Pracownicy”.' : ''));
        }
    } elseif ($akcja === 'przelacz') {
        $nowa = $cel['aktywne'] ? 0 : 1;
        db()->prepare('UPDATE uzytkownicy SET aktywne = ? WHERE id = ?')->execute([$nowa, $id]);
        loguj_admina('uzytkownik_aktywnosc', ($nowa ? 'Aktywowano' : 'Dezaktywowano') . " konto #{$id} ({$cel['imie']} {$cel['nazwisko']})");
        flash('ok', $nowa ? 'Konto jest aktywne.' : 'Konto zostało dezaktywowane: użytkownik nie może się zalogować.');
    } else {
        flash('err', 'Nieznana operacja.');
    }
    redirect(adres_powrotu('admin-uzytkownicy.php', $wroc, ['q', 'rola', 'strona']));
}

$q = mb_substr(pobierz('q'), 0, 50);
$fRola = isset(ROLE[pobierz('rola')]) ? pobierz('rola') : '';
$gdzie = [];
$par = [];
if ($q !== '') {
    $gdzie[] = "(imie LIKE ? ESCAPE '!' OR nazwisko LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
    $w = '%' . like_escape($q) . '%';
    array_push($par, $w, $w, $w);
}
if ($fRola !== '') {
    $gdzie[] = 'rola = ?';
    $par[] = $fRola;
}
$where = $gdzie ? ' WHERE ' . implode(' AND ', $gdzie) : '';
$p = paginacja((int) db_wartosc('SELECT COUNT(*) FROM uzytkownicy' . $where, $par));
$st = db()->prepare('SELECT id, imie, nazwisko, email, rola, aktywne FROM uzytkownicy' . $where . ' ORDER BY nazwisko, imie LIMIT ' . (int) $p['limit'] . ' OFFSET ' . (int) $p['offset']);
$st->execute($par);
$uzytkownicy = $st->fetchAll();
$wroc = (string) parse_url(url_z_parametrami([]), PHP_URL_QUERY);

admin_start('Użytkownicy', 'uzytkownicy', 'Użytkownicy', 'Konta, role i dostęp do systemu.');
?>
                <form method="get" action="admin-uzytkownicy.php" class="filtry" role="search">
                    <div class="row"><label for="q">Szukaj (imię, nazwisko, e-mail)</label><input id="q" name="q" type="search" value="<?= e($q) ?>" maxlength="50"></div>
                    <?php pole_lista('rola', 'Rola', ['' => 'Wszystkie'] + ROLE, $fRola); ?>
                    <button type="submit" class="btn small">Filtruj</button>
                    <a class="btn small alt" href="admin-uzytkownicy.php">Wyczyść</a>
                </form>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <caption class="sr-only">Użytkownicy</caption>
                        <thead><tr><th scope="col">Użytkownik</th><th scope="col">Rola</th><th scope="col">Status</th><th scope="col">Akcje</th></tr></thead>
                        <tbody>
<?php foreach ($uzytkownicy as $x): $ja = (int) $x['id'] === (int) $u['id']; ?>
                            <tr>
                                <td><strong><?= e($x['imie'] . ' ' . $x['nazwisko']) ?></strong><?= $ja ? ' <span class="muted">(Ty)</span>' : '' ?><br><span class="muted"><?= e($x['email']) ?></span></td>
                                <td>
<?php if ($ja): ?>
                                    <?= e(ROLE[$x['rola']] ?? $x['rola']) ?>
<?php else: ?>
                                    <form method="post" action="admin-uzytkownicy.php" class="inline">
                                        <?= csrf_pole() ?>
                                        <input type="hidden" name="akcja" value="rola">
                                        <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                                        <input type="hidden" name="wroc" value="<?= e($wroc) ?>">
                                        <label class="sr-only" for="rola-<?= (int) $x['id'] ?>">Rola: <?= e($x['imie'] . ' ' . $x['nazwisko']) ?></label>
                                        <select id="rola-<?= (int) $x['id'] ?>" name="rola" class="mala">
<?php foreach (ROLE as $klucz => $nazwa): ?>
                                            <option value="<?= e($klucz) ?>"<?= $klucz === $x['rola'] ? ' selected' : '' ?>><?= e($nazwa) ?></option>
<?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn small alt">Zmień</button>
                                    </form>
<?php endif; ?>
                                </td>
                                <td><?= $x['aktywne'] ? '<span class="status status-potwierdzone">Aktywne</span>' : '<span class="status status-anulowane">Nieaktywne</span>' ?></td>
                                <td class="akcje"><?= $ja ? '' : przycisk_post('przelacz', ['id' => $x['id'], 'wroc' => $wroc], $x['aktywne'] ? 'Dezaktywuj' : 'Aktywuj') ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$uzytkownicy): ?>
                            <tr><td colspan="4">Brak użytkowników spełniających kryteria.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_paginacje($p); ?>
<?php admin_koniec(); ?>
