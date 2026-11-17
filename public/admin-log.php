<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();

$q = mb_substr(pobierz('q'), 0, 60);
$gdzie = '';
$par = [];
if ($q !== '') {
    $gdzie = " WHERE (l.opis LIKE ? ESCAPE '!' OR l.akcja LIKE ? ESCAPE '!')";
    $w = '%' . like_escape($q) . '%';
    $par = [$w, $w];
}
$p = paginacja((int) db_wartosc('SELECT COUNT(*) FROM log_admina l' . $gdzie, $par), 20);
$st = db()->prepare(
    'SELECT l.utworzony, l.akcja, l.opis, a.imie, a.nazwisko FROM log_admina l LEFT JOIN uzytkownicy a ON a.id = l.id_admina'
    . $gdzie . ' ORDER BY l.id DESC LIMIT ' . (int) $p['limit'] . ' OFFSET ' . (int) $p['offset']
);
$st->execute($par);
$wpisy = $st->fetchAll();

admin_start('Dziennik', 'dziennik', 'Dziennik działań', 'Kto i kiedy zmienił dane w panelu administratora.');
?>
                <form method="get" action="admin-log.php" class="filtry" role="search">
                    <div class="row"><label for="q">Szukaj w opisie</label><input id="q" name="q" type="search" value="<?= e($q) ?>" maxlength="60"></div>
                    <button type="submit" class="btn small">Szukaj</button>
<?php if ($q !== ''): ?>
                    <a class="btn small alt" href="admin-log.php">Wyczyść</a>
<?php endif; ?>
                </form>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <caption class="sr-only">Dziennik działań administratorów</caption>
                        <thead><tr><th scope="col">Kiedy</th><th scope="col">Administrator</th><th scope="col">Akcja</th><th scope="col">Opis</th></tr></thead>
                        <tbody>
<?php foreach ($wpisy as $w): ?>
                            <tr>
                                <td><?= e($w['utworzony']) ?></td>
                                <td><?= $w['imie'] ? e($w['imie'] . ' ' . $w['nazwisko']) : '<span class="muted">konto usunięte</span>' ?></td>
                                <td><code><?= e($w['akcja']) ?></code></td>
                                <td><?= e($w['opis']) ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$wpisy): ?>
                            <tr><td colspan="4">Brak wpisów.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_paginacje($p); ?>
<?php admin_koniec(); ?>
