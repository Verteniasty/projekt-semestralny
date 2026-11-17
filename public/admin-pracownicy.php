<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();

$uslugiWszystkie = db()->query('SELECT id, nazwa FROM uslugi ORDER BY nazwa')->fetchAll(PDO::FETCH_KEY_PAIR);
$bledy = [];
$dane = ['id' => 0, 'imie' => '', 'nazwisko' => '', 'email' => '', 'telefon' => '', 'haslo' => '', 'opis' => '', 'aktywne' => true, 'uslugi' => []];


function wybrane_uslugi(array $dozwolone): array
{
    $wybrane = [];
    $surowe = $_POST['uslugi'] ?? [];
    if (is_array($surowe)) {
        foreach ($surowe as $v) {
            $id = is_string($v) ? id_z_tekstu($v) : 0;
            if (isset($dozwolone[$id])) {
                $wybrane[$id] = $id;
            }
        }
    }
    return array_values($wybrane);
}

function zapisz_uslugi_pracownika(int $pracownik, array $uslugi): void
{
    db()->prepare('DELETE FROM uslugi_pracownikow WHERE id_pracownika = ?')->execute([$pracownik]);
    $ins = db()->prepare('INSERT INTO uslugi_pracownikow (id_pracownika, id_uslugi) VALUES (?, ?)');
    foreach ($uslugi as $idUslugi) {
        $ins->execute([$pracownik, $idUslugi]);
    }
}

if (metoda_post()) {
    sprawdz_csrf_lub_wroc('admin-pracownicy.php');
    $akcja = post('akcja');
    $id = id_z_tekstu(post('id'));

    if ($akcja === 'zapisz') {
        $dane = [
            'id' => $id, 'imie' => post('imie'), 'nazwisko' => post('nazwisko'), 'email' => post('email'), 'telefon' => post('telefon'),
            'haslo' => post('haslo', false), 'opis' => post('opis'), 'aktywne' => post('aktywne') === '1', 'uslugi' => wybrane_uslugi($uslugiWszystkie),
        ];
        $bledy = ['opis' => blad_tekstu($dane['opis'], 'opis stanowiska', 0, 255)];
        if ($id === 0) {

            $bledy += bledy_danych_osobowych(['imie' => $dane['imie'], 'nazwisko' => $dane['nazwisko'], 'telefon' => $dane['telefon']]);
            $bledy['email'] = blad_emaila($dane['email']);
            $bledy['haslo'] = blad_hasla($dane['haslo']);
            if (!$bledy['email'] && db_wartosc('SELECT COUNT(*) FROM uzytkownicy WHERE email = ?', [$dane['email']])) {
                $bledy['email'] = 'Konto z takim adresem e-mail już istnieje.';
            }
        } elseif (!db_wartosc('SELECT COUNT(*) FROM pracownicy WHERE id = ?', [$id])) {
            flash('err', 'Nie znaleziono takiego pracownika.');
            redirect('admin-pracownicy.php');
        }
        $bledy = array_filter($bledy);

        if (!$bledy) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($id === 0) {
                    $pdo->prepare('INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, numer_telefonu, rola) VALUES (?, ?, ?, ?, ?, ?)')
                        ->execute([$dane['imie'], $dane['nazwisko'], $dane['email'], password_hash($dane['haslo'], PASSWORD_DEFAULT), normalizuj_telefon($dane['telefon']), 'pracownik']);
                    $idUzytkownika = (int) $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO pracownicy (id_uzytkownika, opis, aktywne) VALUES (?, ?, ?)')
                        ->execute([$idUzytkownika, $dane['opis'] ?: null, $dane['aktywne'] ? 1 : 0]);
                    $id = (int) $pdo->lastInsertId();
                    zapisz_uslugi_pracownika($id, $dane['uslugi']);
                    loguj_admina('pracownik_dodanie', "Dodano pracownika #{$id}: {$dane['imie']} {$dane['nazwisko']}");
                    flash('ok', 'Pracownik został dodany. Ustaw jego godziny pracy w „Dostępności”.');
                } else {
                    $pdo->prepare('UPDATE pracownicy SET opis = ?, aktywne = ? WHERE id = ?')->execute([$dane['opis'] ?: null, $dane['aktywne'] ? 1 : 0, $id]);
                    zapisz_uslugi_pracownika($id, $dane['uslugi']);
                    loguj_admina('pracownik_edycja', "Zmieniono pracownika #{$id} (usługi: " . count($dane['uslugi']) . ')');
                    flash('ok', 'Dane pracownika zostały zapisane.');
                }
                $pdo->commit();
            } catch (PDOException $ex) {
                $pdo->rollBack();
                if ($ex->getCode() === '23000') {
                    $bledy['email'] = 'Konto z takim adresem e-mail już istnieje.';
                } else {
                    throw $ex;
                }
            }
            if (!$bledy) {
                redirect('admin-pracownicy.php');
            }
        }
    } elseif ($akcja === 'przelacz') {
        $st = db()->prepare('SELECT p.aktywne, u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika WHERE p.id = ?');
        $st->execute([$id]);
        if ($pr = $st->fetch()) {
            $nowa = $pr['aktywne'] ? 0 : 1;
            db()->prepare('UPDATE pracownicy SET aktywne = ? WHERE id = ?')->execute([$nowa, $id]);
            loguj_admina('pracownik_aktywnosc', ($nowa ? 'Aktywowano' : 'Dezaktywowano') . " pracownika #{$id}: {$pr['imie']} {$pr['nazwisko']}");
            flash('ok', $nowa ? 'Pracownik jest aktywny.' : 'Pracownik został dezaktywowany: klienci nie zarezerwują już u niego terminów.');
        } else {
            flash('err', 'Nie znaleziono takiego pracownika.');
        }
        redirect('admin-pracownicy.php');
    } elseif ($akcja === 'usun') {
        $st = db()->prepare('SELECT p.id_uzytkownika, u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika WHERE p.id = ?');
        $st->execute([$id]);
        $pr = $st->fetch();
        if (!$pr) {
            flash('err', 'Nie znaleziono takiego pracownika.');
        } elseif (db_wartosc('SELECT COUNT(*) FROM rezerwacje WHERE id_pracownika = ?', [$id]) > 0) {
            flash('err', 'Nie można usunąć pracownika, który ma rezerwacje. Dezaktywuj go: historia zostanie zachowana.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM pracownicy WHERE id = ?')->execute([$id]);

                $pdo->prepare('UPDATE uzytkownicy SET rola = ? WHERE id = ? AND rola = ?')->execute(['klient', $pr['id_uzytkownika'], 'pracownik']);
                $pdo->commit();
                loguj_admina('pracownik_usuniecie', "Usunięto pracownika #{$id}: {$pr['imie']} {$pr['nazwisko']} (konto zostaje jako klient)");
                flash('ok', 'Pracownik został usunięty. Jego konto zostało zachowane jako konto klienta.');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                flash('err', 'Nie można usunąć tego pracownika, bo jest używany.');
            }
        }
        redirect('admin-pracownicy.php');
    } else {
        flash('err', 'Nieznana operacja.');
        redirect('admin-pracownicy.php');
    }
}

$edytuj = id_z_tekstu(pobierz('edytuj'));
if ($edytuj > 0 && !metoda_post()) {
    $st = db()->prepare('SELECT p.id, p.opis, p.aktywne, u.imie, u.nazwisko, u.email FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika WHERE p.id = ?');
    $st->execute([$edytuj]);
    if ($pr = $st->fetch()) {
        $st = db()->prepare('SELECT id_uslugi FROM uslugi_pracownikow WHERE id_pracownika = ?');
        $st->execute([$edytuj]);
        $dane = ['id' => (int) $pr['id'], 'imie' => $pr['imie'], 'nazwisko' => $pr['nazwisko'], 'email' => $pr['email'], 'telefon' => '', 'haslo' => '',
                 'opis' => (string) $pr['opis'], 'aktywne' => (bool) $pr['aktywne'], 'uslugi' => array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))];
    } else {
        flash('err', 'Nie znaleziono takiego pracownika.');
        redirect('admin-pracownicy.php');
    }
}
$usun = id_z_tekstu(pobierz('usun'));
if ($usun > 0 && !metoda_post()) {
    $st = db()->prepare('SELECT u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika WHERE p.id = ?');
    $st->execute([$usun]);
    if ($pr = $st->fetch()) {
        strona_potwierdzenia('Usuwanie pracownika', "Czy na pewno usunąć pracownika {$pr['imie']} {$pr['nazwisko']}? Jego konto zostanie zachowane jako konto klienta.", ['akcja' => 'usun', 'id' => $usun], 'admin-pracownicy.php');
    }
    flash('err', 'Nie znaleziono takiego pracownika.');
    redirect('admin-pracownicy.php');
}


$q = mb_substr(pobierz('q'), 0, 50);
$gdzie = '';
$par = [];
if ($q !== '') {
    $gdzie = " WHERE (u.imie LIKE ? ESCAPE '!' OR u.nazwisko LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
    $w = '%' . like_escape($q) . '%';
    $par = [$w, $w, $w];
}
$p = paginacja((int) db_wartosc('SELECT COUNT(*) FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika' . $gdzie, $par));
$st = db()->prepare(
    'SELECT p.id, p.opis, p.aktywne, u.imie, u.nazwisko, u.email,
            (SELECT COUNT(*) FROM rezerwacje r WHERE r.id_pracownika = p.id) AS ile_rezerwacji
     FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika' . $gdzie
    . ' ORDER BY u.nazwisko, u.imie LIMIT ' . (int) $p['limit'] . ' OFFSET ' . (int) $p['offset']
);
$st->execute($par);
$pracownicy = $st->fetchAll();
$nazwyUslug = [];
if ($pracownicy) {
    $ids = array_map('intval', array_column($pracownicy, 'id'));
    $st = db()->prepare(
        'SELECT up.id_pracownika, us.nazwa FROM uslugi_pracownikow up JOIN uslugi us ON us.id = up.id_uslugi
         WHERE up.id_pracownika IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY us.nazwa'
    );
    $st->execute($ids);
    foreach ($st->fetchAll() as $w) {
        $nazwyUslug[$w['id_pracownika']][] = $w['nazwa'];
    }
}

admin_start('Pracownicy', 'pracownicy', 'Pracownicy', 'Osoby obsługujące klientów: ich usługi i dostępność.');
?>
                <div class="dwie-kolumny">
                    <div>
                        <h2><?= $dane['id'] ? 'Edycja pracownika' : 'Nowy pracownik' ?></h2>
                        <form method="post" action="admin-pracownicy.php" novalidate class="karta-formularz">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akcja" value="zapisz">
                            <input type="hidden" name="id" value="<?= (int) $dane['id'] ?>">
<?php if (!$dane['id']): ?>
                            <p class="hint">Dodanie pracownika zakłada dla niego konto z rolą „pracownik”.</p>
                            <div class="grid2">
                                <?php pole('imie', 'Imię', 'text', $dane['imie'], $bledy['imie'] ?? null, 'required maxlength="40" autocomplete="off"'); ?>
                                <?php pole('nazwisko', 'Nazwisko', 'text', $dane['nazwisko'], $bledy['nazwisko'] ?? null, 'required maxlength="40" autocomplete="off"'); ?>
                            </div>
                            <?php pole('email', 'E-mail (login)', 'email', $dane['email'], $bledy['email'] ?? null, 'required maxlength="60" autocomplete="off"'); ?>
                            <?php pole('telefon', 'Telefon', 'tel', $dane['telefon'], $bledy['telefon'] ?? null, 'required autocomplete="off"'); ?>
                            <?php pole('haslo', 'Hasło początkowe', 'password', '', $bledy['haslo'] ?? null, 'required minlength="8" autocomplete="new-password"'); ?>
<?php else: ?>
                            <p><strong><?= e($dane['imie'] . ' ' . $dane['nazwisko']) ?></strong><br><span class="muted"><?= e($dane['email']) ?></span></p>
<?php endif; ?>
                            <?php pole('opis', 'Opis stanowiska', 'text', $dane['opis'], $bledy['opis'] ?? null, 'maxlength="255"'); ?>
                            <fieldset class="lista-checkbox">
                                <legend>Obsługiwane usługi</legend>
<?php foreach ($uslugiWszystkie as $idUslugi => $nazwaUslugi): ?>
                                <label><input type="checkbox" name="uslugi[]" value="<?= (int) $idUslugi ?>"<?= in_array($idUslugi, $dane['uslugi'], true) ? ' checked' : '' ?>> <?= e($nazwaUslugi) ?></label>
<?php endforeach; ?>
<?php if (!$uslugiWszystkie): ?>
                                <p class="muted">Najpierw dodaj usługi.</p>
<?php endif; ?>
                            </fieldset>
                            <?php pole_checkbox('aktywne', 'Pracownik aktywny (przyjmuje rezerwacje)', (bool) $dane['aktywne']); ?>
                            <button type="submit" class="btn"><?= $dane['id'] ? 'Zapisz zmiany' : 'Dodaj pracownika' ?></button>
<?php if ($dane['id']): ?>
                            <a class="btn alt" href="admin-pracownicy.php">Anuluj edycję</a>
<?php endif; ?>
                        </form>
                    </div>
                    <div>
                        <h2>Lista pracowników</h2>
                        <form method="get" action="admin-pracownicy.php" class="filtry" role="search">
                            <div class="row"><label for="q">Szukaj (imię, nazwisko, e-mail)</label><input id="q" name="q" type="search" value="<?= e($q) ?>" maxlength="50"></div>
                            <button type="submit" class="btn small">Szukaj</button>
<?php if ($q !== ''): ?>
                            <a class="btn small alt" href="admin-pracownicy.php">Wyczyść</a>
<?php endif; ?>
                        </form>
                        <div class="tabela-wrap">
                            <table class="tabela">
                                <caption class="sr-only">Pracownicy</caption>
                                <thead><tr><th scope="col">Pracownik</th><th scope="col">Usługi</th><th scope="col">Status</th><th scope="col">Akcje</th></tr></thead>
                                <tbody>
<?php foreach ($pracownicy as $pr): ?>
                                    <tr>
                                        <td><strong><?= e($pr['imie'] . ' ' . $pr['nazwisko']) ?></strong><br><span class="muted"><?= e($pr['email']) ?></span><br><span class="muted"><?= e((string) $pr['opis']) ?></span></td>
                                        <td><?= e(implode(', ', $nazwyUslug[$pr['id']] ?? ['brak'])) ?></td>
                                        <td><?= $pr['aktywne'] ? '<span class="status status-potwierdzone">Aktywny</span>' : '<span class="status status-anulowane">Nieaktywny</span>' ?></td>
                                        <td class="akcje">
                                            <a class="btn small alt" href="admin-pracownicy.php?edytuj=<?= (int) $pr['id'] ?>">Edytuj</a>
                                            <a class="btn small alt" href="admin-dostepnosc.php?id=<?= (int) $pr['id'] ?>">Dostępność</a>
                                            <?= przycisk_post('przelacz', ['id' => $pr['id']], $pr['aktywne'] ? 'Dezaktywuj' : 'Aktywuj') ?>
                                            <a class="btn small alt" href="admin-pracownicy.php?usun=<?= (int) $pr['id'] ?>">Usuń</a>
                                        </td>
                                    </tr>
<?php endforeach; ?>
<?php if (!$pracownicy): ?>
                                    <tr><td colspan="4">Brak pracowników spełniających kryteria.</td></tr>
<?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php render_paginacje($p); ?>
                    </div>
                </div>
<?php admin_koniec(); ?>
