<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();

$kategorie = db()->query('SELECT id, nazwa FROM kategorie_uslug ORDER BY nazwa')->fetchAll(PDO::FETCH_KEY_PAIR);
$bledy = [];
$typy = ['doby' => 'Wynajem auta na doby (cena za dobę)', 'godziny' => 'Wizyta na godziny (stały czas trwania)'];
$dane = ['id' => 0, 'id_kategorii' => '', 'typ' => 'doby', 'nazwa' => '', 'opis' => '', 'czas_trwania' => '60', 'cena' => '', 'aktywne' => true];


function slug_z_nazwy(string $nazwa): string
{
    $mapa = ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z'];
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower($nazwa), $mapa)), '-');
    $s = mb_substr($s !== '' ? $s : 'auto', 0, 50);
    $baza = $s;
    for ($i = 2; db_wartosc('SELECT COUNT(*) FROM uslugi WHERE slug = ?', [$s]) > 0; $i++) {
        $s = $baza . '-' . $i;
    }
    return $s;
}

if (metoda_post()) {
    sprawdz_csrf_lub_wroc('admin-uslugi.php');
    $akcja = post('akcja');
    $id = id_z_tekstu(post('id'));

    if ($akcja === 'zapisz') {
        $dane = [
            'id' => $id, 'id_kategorii' => post('id_kategorii'), 'typ' => post('typ'),
            'nazwa' => post('nazwa'), 'opis' => post('opis'),
            'czas_trwania' => post('czas_trwania'), 'cena' => post('cena'), 'aktywne' => post('aktywne') === '1',
        ];
        if ($dane['typ'] === 'doby') {
            $dane['czas_trwania'] = '1440';
        }
        $bledy = array_filter([
            'typ'          => isset($typy[$dane['typ']]) ? null : 'Wybierz rodzaj z listy.',
            'id_kategorii' => isset($kategorie[id_z_tekstu($dane['id_kategorii'])]) ? null : 'Wybierz kategorię z listy.',
            'nazwa'        => blad_tekstu($dane['nazwa'], 'nazwa', 2, 80),
            'opis'         => blad_tekstu($dane['opis'], 'opis', 0, 512),
            'czas_trwania' => blad_czasu_trwania($dane['czas_trwania']),
            'cena'         => blad_ceny($dane['cena']),
        ]);
        if (!$bledy && $id > 0 && !db_wartosc('SELECT COUNT(*) FROM uslugi WHERE id = ?', [$id])) {
            flash('err', 'Nie znaleziono takiej usługi.');
            redirect('admin-uslugi.php');
        }
        if (!$bledy) {
            $par = [id_z_tekstu($dane['id_kategorii']), $dane['nazwa'], $dane['typ'], $dane['opis'] ?: null, (int) $dane['czas_trwania'], cena_z_tekstu($dane['cena']), $dane['aktywne'] ? 1 : 0];
            if ($id > 0) {
                db()->prepare('UPDATE uslugi SET id_kategorii = ?, nazwa = ?, typ = ?, opis = ?, czas_trwania = ?, cena = ?, aktywne = ? WHERE id = ?')->execute([...$par, $id]);
                loguj_admina('usluga_edycja', "Zmieniono usługę #{$id}: {$dane['nazwa']}");
                flash('ok', 'Usługa została zapisana. Zmiana ceny i czasu nie wpływa na istniejące rezerwacje.');
            } else {
                db()->prepare('INSERT INTO uslugi (id_kategorii, nazwa, typ, opis, czas_trwania, cena, aktywne, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$par, slug_z_nazwy($dane['nazwa'])]);
                loguj_admina('usluga_dodanie', 'Dodano usługę: ' . $dane['nazwa']);
                flash('ok', 'Usługa została dodana.');
            }
            redirect('admin-uslugi.php');
        }
    } elseif ($akcja === 'przelacz') {
        $st = db()->prepare('SELECT nazwa, aktywne FROM uslugi WHERE id = ?');
        $st->execute([$id]);
        if ($us = $st->fetch()) {
            $nowa = $us['aktywne'] ? 0 : 1;
            db()->prepare('UPDATE uslugi SET aktywne = ? WHERE id = ?')->execute([$nowa, $id]);
            loguj_admina('usluga_aktywnosc', ($nowa ? 'Aktywowano' : 'Dezaktywowano') . " usługę #{$id}: {$us['nazwa']}");
            flash('ok', $nowa ? 'Usługa jest aktywna.' : 'Usługa została dezaktywowana i nie jest już dostępna dla klientów.');
        } else {
            flash('err', 'Nie znaleziono takiej usługi.');
        }
        redirect(url_z_parametrami([]));
    } elseif ($akcja === 'usun') {
        $st = db()->prepare('SELECT nazwa FROM uslugi WHERE id = ?');
        $st->execute([$id]);
        $us = $st->fetch();
        if (!$us) {
            flash('err', 'Nie znaleziono takiej usługi.');
        } elseif (db_wartosc('SELECT COUNT(*) FROM rezerwacje WHERE id_uslugi = ?', [$id]) > 0) {
            flash('err', 'Nie można usunąć usługi, która ma rezerwacje. Dezaktywuj ją, a znikną z oferty, ale historia zostanie.');
        } else {
            try {
                db()->prepare('DELETE FROM uslugi WHERE id = ?')->execute([$id]);
                loguj_admina('usluga_usuniecie', "Usunięto usługę #{$id}: {$us['nazwa']}");
                flash('ok', 'Usługa została usunięta.');
            } catch (PDOException $ex) {
                flash('err', 'Nie można usunąć tej usługi, bo jest używana.');
            }
        }
        redirect('admin-uslugi.php');
    } else {
        flash('err', 'Nieznana operacja.');
        redirect('admin-uslugi.php');
    }
}

$edytuj = id_z_tekstu(pobierz('edytuj'));
if ($edytuj > 0 && !metoda_post()) {
    $st = db()->prepare('SELECT id, id_kategorii, typ, nazwa, opis, czas_trwania, cena, aktywne FROM uslugi WHERE id = ?');
    $st->execute([$edytuj]);
    if ($us = $st->fetch()) {
        $dane = ['id' => (int) $us['id'], 'id_kategorii' => (string) $us['id_kategorii'], 'typ' => $us['typ'], 'nazwa' => $us['nazwa'], 'opis' => (string) $us['opis'],
                 'czas_trwania' => (string) $us['czas_trwania'], 'cena' => number_format((float) $us['cena'], 2, ',', ''), 'aktywne' => (bool) $us['aktywne']];
    } else {
        flash('err', 'Nie znaleziono takiej usługi.');
        redirect('admin-uslugi.php');
    }
}
$usun = id_z_tekstu(pobierz('usun'));
if ($usun > 0 && !metoda_post()) {
    $st = db()->prepare('SELECT nazwa FROM uslugi WHERE id = ?');
    $st->execute([$usun]);
    if ($us = $st->fetch()) {
        strona_potwierdzenia('Usuwanie usługi', "Czy na pewno usunąć usługę „{$us['nazwa']}”? Tej operacji nie można cofnąć.", ['akcja' => 'usun', 'id' => $usun], 'admin-uslugi.php');
    }
    flash('err', 'Nie znaleziono takiej usługi.');
    redirect('admin-uslugi.php');
}


$q = mb_substr(pobierz('q'), 0, 50);
$fKat = id_z_tekstu(pobierz('kategoria'));
$fStatus = in_array(pobierz('aktywna'), ['1', '0'], true) ? pobierz('aktywna') : '';
$fTyp = isset($typy[pobierz('rodzaj')]) ? pobierz('rodzaj') : '';
$gdzie = [];
$par = [];
if ($q !== '') {
    $gdzie[] = "us.nazwa LIKE ? ESCAPE '!'";
    $par[] = '%' . like_escape($q) . '%';
}
if ($fKat > 0) {
    $gdzie[] = 'us.id_kategorii = ?';
    $par[] = $fKat;
}
if ($fStatus !== '') {
    $gdzie[] = 'us.aktywne = ?';
    $par[] = (int) $fStatus;
}
if ($fTyp !== '') {
    $gdzie[] = 'us.typ = ?';
    $par[] = $fTyp;
}
$where = $gdzie ? ' WHERE ' . implode(' AND ', $gdzie) : '';
$p = paginacja((int) db_wartosc('SELECT COUNT(*) FROM uslugi us' . $where, $par));
$st = db()->prepare(
    'SELECT us.id, us.nazwa, us.typ, us.czas_trwania, us.cena, us.aktywne, k.nazwa AS kategoria,
            (SELECT COUNT(*) FROM rezerwacje r WHERE r.id_uslugi = us.id) AS ile_rezerwacji
     FROM uslugi us JOIN kategorie_uslug k ON k.id = us.id_kategorii' . $where
    . ' ORDER BY k.nazwa, us.nazwa LIMIT ' . (int) $p['limit'] . ' OFFSET ' . (int) $p['offset']
);
$st->execute($par);
$uslugi = $st->fetchAll();

admin_start('Auta i usługi', 'uslugi', 'Auta i usługi', 'Flota do wynajmu na doby oraz usługi na godziny: cena, czas i dostępność.');
?>
                <div class="dwie-kolumny">
                    <div>
                        <h2><?= $dane['id'] ? 'Edycja' : 'Nowe auto lub usługa' ?></h2>
                        <form method="post" action="admin-uslugi.php" novalidate class="karta-formularz">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akcja" value="zapisz">
                            <input type="hidden" name="id" value="<?= (int) $dane['id'] ?>">
                            <?php pole_lista('id_kategorii', 'Kategoria', ['' => 'Wybierz kategorię'] + $kategorie, (string) $dane['id_kategorii'], $bledy['id_kategorii'] ?? null, 'required'); ?>
                            <?php pole_lista('typ', 'Rodzaj', $typy, $dane['typ']); ?>
                            <?php pole('nazwa', 'Nazwa', 'text', $dane['nazwa'], $bledy['nazwa'] ?? null, 'required maxlength="80"'); ?>
                            <?php pole_tekst('opis', 'Opis (opcjonalnie)', $dane['opis'], $bledy['opis'] ?? null, 'maxlength="512"'); ?>
                            <div class="grid2">
                                <div id="czas-wiersz"><?php pole('czas_trwania', 'Czas trwania (minuty, tylko wizyty)', 'number', $dane['czas_trwania'], $bledy['czas_trwania'] ?? null, 'required min="5" max="1440" step="1" inputmode="numeric"'); ?></div>
                                <?php pole('cena', 'Cena (zł, za dobę lub za wizytę)', 'text', $dane['cena'], $bledy['cena'] ?? null, 'required inputmode="decimal" placeholder="np. 120,00"'); ?>
                            </div>
                            <?php pole_checkbox('aktywne', 'Usługa aktywna (widoczna dla klientów)', (bool) $dane['aktywne']); ?>
                            <button type="submit" class="btn"><?= $dane['id'] ? 'Zapisz zmiany' : 'Dodaj' ?></button>
<?php if ($dane['id']): ?>
                            <a class="btn alt" href="admin-uslugi.php">Anuluj edycję</a>
<?php endif; ?>
                        </form>
                    </div>
                    <div>
                        <h2>Lista aut i usług</h2>
                        <form method="get" action="admin-uslugi.php" class="filtry" role="search">
                            <div class="row"><label for="q">Szukaj po nazwie</label><input id="q" name="q" type="search" value="<?= e($q) ?>" maxlength="50"></div>
                            <?php pole_lista('kategoria', 'Kategoria', ['' => 'Wszystkie'] + $kategorie, (string) ($fKat ?: '')); ?>
                            <?php pole_lista('rodzaj', 'Rodzaj', ['' => 'Wszystkie'] + $typy, $fTyp); ?>
                            <?php pole_lista('aktywna', 'Status', ['' => 'Wszystkie', '1' => 'Aktywne', '0' => 'Nieaktywne'], $fStatus); ?>
                            <button type="submit" class="btn small">Filtruj</button>
                            <a class="btn small alt" href="admin-uslugi.php">Wyczyść</a>
                        </form>
                        <div class="tabela-wrap">
                            <table class="tabela">
                                <caption class="sr-only">Usługi</caption>
                                <thead><tr><th scope="col">Auto / usługa</th><th scope="col">Czas</th><th scope="col">Cena</th><th scope="col">Rez.</th><th scope="col">Status</th><th scope="col">Akcje</th></tr></thead>
                                <tbody>
<?php foreach ($uslugi as $us): ?>
                                    <tr>
                                        <td><strong><?= e($us['nazwa']) ?></strong><br><span class="muted"><?= e($us['kategoria']) ?></span></td>
                                        <td><?= $us['typ'] === 'doby' ? '<span class="plakietka-typ">doby</span>' : e(czas_txt((int) $us['czas_trwania'])) ?></td>
                                        <td><?= e(cena_uslugi_txt($us)) ?></td>
                                        <td><?= (int) $us['ile_rezerwacji'] ?></td>
                                        <td><?= $us['aktywne'] ? '<span class="status status-potwierdzone">Aktywna</span>' : '<span class="status status-anulowane">Nieaktywna</span>' ?></td>
                                        <td class="akcje">
                                            <a class="btn small alt" href="admin-uslugi.php?edytuj=<?= (int) $us['id'] ?>">Edytuj</a>
                                            <?= przycisk_post('przelacz', ['id' => $us['id']], $us['aktywne'] ? 'Dezaktywuj' : 'Aktywuj') ?>
                                            <a class="btn small alt" href="admin-uslugi.php?usun=<?= (int) $us['id'] ?>">Usuń</a>
                                        </td>
                                    </tr>
<?php endforeach; ?>
<?php if (!$uslugi): ?>
                                    <tr><td colspan="6">Brak usług spełniających kryteria.</td></tr>
<?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php render_paginacje($p); ?>
                    </div>
                </div>
<?php admin_koniec(); ?>
