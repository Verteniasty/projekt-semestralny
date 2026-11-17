<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();

$bledy = [];
$dane = ['id' => 0, 'nazwa' => '', 'opis' => ''];


if (metoda_post()) {
    sprawdz_csrf_lub_wroc('admin-kategorie.php');
    $akcja = post('akcja');
    $id = id_z_tekstu(post('id'));

    if ($akcja === 'zapisz') {
        $dane = ['id' => $id, 'nazwa' => post('nazwa'), 'opis' => post('opis')];
        $bledy = array_filter([
            'nazwa' => blad_tekstu($dane['nazwa'], 'nazwa', 2, 50),
            'opis'  => blad_tekstu($dane['opis'], 'opis', 0, 512),
        ]);
        if (!$bledy) {

            $zajeta = db_wartosc('SELECT COUNT(*) FROM kategorie_uslug WHERE LOWER(nazwa) = LOWER(?) AND id <> ?', [$dane['nazwa'], $id]);
            if ($zajeta) {
                $bledy['nazwa'] = 'Kategoria o takiej nazwie już istnieje.';
            }
        }
        if (!$bledy && $id > 0 && !db_wartosc('SELECT COUNT(*) FROM kategorie_uslug WHERE id = ?', [$id])) {
            flash('err', 'Nie znaleziono takiej kategorii.');
            redirect('admin-kategorie.php');
        }
        if (!$bledy) {
            if ($id > 0) {
                db()->prepare('UPDATE kategorie_uslug SET nazwa = ?, opis = ? WHERE id = ?')->execute([$dane['nazwa'], $dane['opis'] ?: null, $id]);
                loguj_admina('kategoria_edycja', "Zmieniono kategorię #{$id}: {$dane['nazwa']}");
                flash('ok', 'Kategoria została zapisana.');
            } else {
                db()->prepare('INSERT INTO kategorie_uslug (nazwa, opis) VALUES (?, ?)')->execute([$dane['nazwa'], $dane['opis'] ?: null]);
                loguj_admina('kategoria_dodanie', 'Dodano kategorię: ' . $dane['nazwa']);
                flash('ok', 'Kategoria została dodana.');
            }
            redirect('admin-kategorie.php');
        }
    } elseif ($akcja === 'usun') {
        $st = db()->prepare('SELECT nazwa FROM kategorie_uslug WHERE id = ?');
        $st->execute([$id]);
        $kat = $st->fetch();
        if (!$kat) {
            flash('err', 'Nie znaleziono takiej kategorii.');
        } elseif (db_wartosc('SELECT COUNT(*) FROM uslugi WHERE id_kategorii = ?', [$id]) > 0) {

            flash('err', 'Nie można usunąć kategorii, która zawiera usługi. Przenieś albo usuń najpierw jej usługi.');
        } else {
            try {
                db()->prepare('DELETE FROM kategorie_uslug WHERE id = ?')->execute([$id]);
                loguj_admina('kategoria_usuniecie', "Usunięto kategorię #{$id}: {$kat['nazwa']}");
                flash('ok', 'Kategoria została usunięta.');
            } catch (PDOException $ex) {
                flash('err', 'Nie można usunąć tej kategorii, bo jest używana.');
            }
        }
        redirect('admin-kategorie.php');
    } else {
        flash('err', 'Nieznana operacja.');
        redirect('admin-kategorie.php');
    }
}


$edytuj = id_z_tekstu(pobierz('edytuj'));
if ($edytuj > 0 && !metoda_post()) {
    $st = db()->prepare('SELECT id, nazwa, opis FROM kategorie_uslug WHERE id = ?');
    $st->execute([$edytuj]);
    if ($k = $st->fetch()) {
        $dane = ['id' => (int) $k['id'], 'nazwa' => $k['nazwa'], 'opis' => (string) $k['opis']];
    } else {
        flash('err', 'Nie znaleziono takiej kategorii.');
        redirect('admin-kategorie.php');
    }
}
$usun = id_z_tekstu(pobierz('usun'));
if ($usun > 0 && !metoda_post()) {
    $st = db()->prepare('SELECT nazwa FROM kategorie_uslug WHERE id = ?');
    $st->execute([$usun]);
    if ($k = $st->fetch()) {
        strona_potwierdzenia('Usuwanie kategorii', "Czy na pewno usunąć kategorię „{$k['nazwa']}”? Tej operacji nie można cofnąć.", ['akcja' => 'usun', 'id' => $usun], 'admin-kategorie.php');
    }
    flash('err', 'Nie znaleziono takiej kategorii.');
    redirect('admin-kategorie.php');
}


$q = mb_substr(pobierz('q'), 0, 50);
$gdzie = '';
$par = [];
if ($q !== '') {
    $gdzie = " WHERE k.nazwa LIKE ? ESCAPE '!'";
    $par[] = '%' . like_escape($q) . '%';
}
$p = paginacja((int) db_wartosc('SELECT COUNT(*) FROM kategorie_uslug k' . $gdzie, $par));
$st = db()->prepare(
    'SELECT k.id, k.nazwa, k.opis, (SELECT COUNT(*) FROM uslugi us WHERE us.id_kategorii = k.id) AS ile_uslug
     FROM kategorie_uslug k' . $gdzie . ' ORDER BY k.nazwa LIMIT ' . (int) $p['limit'] . ' OFFSET ' . (int) $p['offset']
);
$st->execute($par);
$kategorie = $st->fetchAll();

admin_start('Kategorie', 'kategorie', 'Kategorie usług', 'Grupy, do których należą usługi w ofercie.');
?>
                <div class="dwie-kolumny">
                    <div>
                        <h2><?= $dane['id'] ? 'Edycja kategorii' : 'Nowa kategoria' ?></h2>
                        <form method="post" action="admin-kategorie.php" novalidate class="karta-formularz">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akcja" value="zapisz">
                            <input type="hidden" name="id" value="<?= (int) $dane['id'] ?>">
                            <?php pole('nazwa', 'Nazwa', 'text', $dane['nazwa'], $bledy['nazwa'] ?? null, 'required maxlength="50"'); ?>
                            <?php pole_tekst('opis', 'Opis (opcjonalnie)', $dane['opis'], $bledy['opis'] ?? null, 'maxlength="512"'); ?>
                            <button type="submit" class="btn"><?= $dane['id'] ? 'Zapisz zmiany' : 'Dodaj kategorię' ?></button>
<?php if ($dane['id']): ?>
                            <a class="btn alt" href="admin-kategorie.php">Anuluj edycję</a>
<?php endif; ?>
                        </form>
                    </div>
                    <div>
                        <h2>Lista kategorii</h2>
                        <form method="get" action="admin-kategorie.php" class="filtry" role="search">
                            <div class="row"><label for="q">Szukaj po nazwie</label><input id="q" name="q" type="search" value="<?= e($q) ?>" maxlength="50"></div>
                            <button type="submit" class="btn small">Szukaj</button>
<?php if ($q !== ''): ?>
                            <a class="btn small alt" href="admin-kategorie.php">Wyczyść</a>
<?php endif; ?>
                        </form>
                        <div class="tabela-wrap">
                            <table class="tabela">
                                <caption class="sr-only">Kategorie usług</caption>
                                <thead><tr><th scope="col">Nazwa</th><th scope="col">Usług</th><th scope="col">Akcje</th></tr></thead>
                                <tbody>
<?php foreach ($kategorie as $k): ?>
                                    <tr>
                                        <td><strong><?= e($k['nazwa']) ?></strong><br><span class="muted"><?= e((string) $k['opis']) ?></span></td>
                                        <td><?= (int) $k['ile_uslug'] ?></td>
                                        <td class="akcje">
                                            <a class="btn small alt" href="admin-kategorie.php?edytuj=<?= (int) $k['id'] ?>">Edytuj</a>
                                            <a class="btn small alt" href="admin-kategorie.php?usun=<?= (int) $k['id'] ?>">Usuń</a>
                                        </td>
                                    </tr>
<?php endforeach; ?>
<?php if (!$kategorie): ?>
                                    <tr><td colspan="3">Brak kategorii spełniających kryteria.</td></tr>
<?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php render_paginacje($p); ?>
                    </div>
                </div>
<?php admin_koniec(); ?>
