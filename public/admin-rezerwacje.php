<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();

if (metoda_post()) {
    $wroc = post('wroc');
    sprawdz_csrf_lub_wroc(adres_powrotu('admin-rezerwacje.php', $wroc));
    if (post('akcja') === 'status') {
        $id = id_z_tekstu(post('id'));
        $nowy = post('nowy');

        if ($blad = zmien_status($id, $nowy, (int) $u['id'])) {
            flash('err', $blad);
        } else {
            loguj_admina('rezerwacja_status', "Rezerwacja #{$id}: zmiana statusu na „" . etykieta_statusu($nowy) . '”');
            flash('ok', 'Status rezerwacji został zmieniony na „' . etykieta_statusu($nowy) . '”.');
        }
    } else {
        flash('err', 'Nieznana operacja.');
    }
    redirect(adres_powrotu('admin-rezerwacje.php', $wroc));
}


$anuluj = id_z_tekstu(pobierz('anuluj'));
if ($anuluj > 0) {
    if ($r = rezerwacja_po_id($anuluj)) {
        strona_potwierdzenia(
            'Anulowanie rezerwacji',
            "Czy na pewno anulować rezerwację #{$r['id']} ({$r['usluga']}, " . termin_txt($r) . ", {$r['k_imie']} {$r['k_nazwisko']})? Klient dostanie powiadomienie.",
            ['akcja' => 'status', 'id' => $anuluj, 'nowy' => 'anulowane', 'wroc' => pobierz('wroc')],
            adres_powrotu('admin-rezerwacje.php', pobierz('wroc')),
            'Tak, anuluj rezerwację'
        );
    }
    flash('err', 'Nie znaleziono takiej rezerwacji.');
    redirect('admin-rezerwacje.php');
}

$f = filtry_rezerwacji_z_get();
$sortowania = ['' => 'Najnowsze terminy', 'stare' => 'Najstarsze terminy', 'cena' => 'Najwyższa cena'];
$sort = isset($sortowania[pobierz('sort')]) ? pobierz('sort') : '';
$p = paginacja(policz_rezerwacje($f));
$rezerwacje = lista_rezerwacji($f, $p['limit'], $p['offset'], false, $sort);
$liczniki = liczniki_statusow($f);
$pracownicy = db()->query('SELECT p.id, u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika ORDER BY u.nazwisko, u.imie')->fetchAll();
$opcjePracownikow = ['' => 'Wszyscy'];
foreach ($pracownicy as $pr) {
    $opcjePracownikow[$pr['id']] = $pr['imie'] . ' ' . $pr['nazwisko'];
}
$opcjeUslug = ['' => 'Wszystkie'] + db()->query('SELECT id, nazwa FROM uslugi ORDER BY nazwa')->fetchAll(PDO::FETCH_KEY_PAIR);
$opcjeStatusow = ['' => 'Wszystkie'] + STATUSY;
$wroc = (string) parse_url(url_z_parametrami(['strona' => $p['strona'] > 1 ? (string) $p['strona'] : null]), PHP_URL_QUERY);
$daneEksportu = array_filter(['q' => $f['q'], 'status' => $f['status'], 'pracownik' => $f['pracownik'] ?: '', 'od' => $f['od'], 'do' => $f['do'], 'usluga' => $f['usluga'] ?: '', 'dzis' => $f['dzis']]);


function adres_zakladki(string $status): string
{
    return url_z_parametrami(['status' => $status, 'strona' => null]);
}

admin_start('Rezerwacje', 'rezerwacje', 'Rezerwacje', 'Wynajmy aut i wizyty: filtruj, sortuj i zmieniaj statusy.');
?>
                <ul class="zakladki" aria-label="Status rezerwacji">
                    <li><a href="<?= e(adres_zakladki('')) ?>"<?= $f['status'] === '' ? ' aria-current="page"' : '' ?>>Wszystkie <span class="licznik"><?= array_sum($liczniki) ?></span></a></li>
<?php foreach (STATUSY as $klucz => $nazwa): ?>
                    <li><a href="<?= e(adres_zakladki($klucz)) ?>"<?= $f['status'] === $klucz ? ' aria-current="page"' : '' ?>><?= e($nazwa) ?> <span class="licznik<?= $klucz === 'oczekujace' && $liczniki[$klucz] ? ' licznik-uwaga' : '' ?>"><?= $liczniki[$klucz] ?></span></a></li>
<?php endforeach; ?>
                    <li><a href="<?= e(url_z_parametrami(['dzis' => $f['dzis'] ? null : '1', 'strona' => null])) ?>"<?= $f['dzis'] ? ' aria-current="page"' : '' ?>>Dziś: wydania i zwroty</a></li>
                </ul>
                <form method="get" action="admin-rezerwacje.php" class="filtry filtry-szerokie" role="search">
                    <input type="hidden" name="status" value="<?= e($f['status']) ?>">
                    <?php if ($f['dzis']): ?><input type="hidden" name="dzis" value="1"><?php endif; ?>
                    <div class="row"><label for="q">Szukaj (klient, e-mail, auto)</label><input id="q" name="q" type="search" value="<?= e($f['q']) ?>" maxlength="60"></div>
                    <?php pole_lista('usluga', 'Auto / usługa', $opcjeUslug, (string) ($f['usluga'] ?: '')); ?>
                    <?php pole_lista('pracownik', 'Pracownik', $opcjePracownikow, (string) ($f['pracownik'] ?: '')); ?>
                    <div class="row"><label for="od">Okres od</label><input id="od" name="od" type="date" value="<?= e($f['od']) ?>"></div>
                    <div class="row"><label for="do">Okres do</label><input id="do" name="do" type="date" value="<?= e($f['do']) ?>"></div>
                    <?php pole_lista('sort', 'Sortuj', $sortowania, $sort); ?>
                    <button type="submit" class="btn small">Filtruj</button>
                    <a class="btn small alt" href="admin-rezerwacje.php">Wyczyść</a>
                    <a class="btn small alt" href="admin-eksport.php<?= $daneEksportu ? '?' . e(http_build_query($daneEksportu)) : '' ?>">Eksport CSV</a>
                </form>
                <p class="muted">Znaleziono: <strong><?= (int) $p['razem'] ?></strong></p>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <caption class="sr-only">Rezerwacje</caption>
                        <thead><tr><th scope="col">#</th><th scope="col">Termin</th><th scope="col">Klient</th><th scope="col">Auto / usługa</th><th scope="col">Pracownik</th><th scope="col">Cena</th><th scope="col">Status</th><th scope="col">Akcje</th></tr></thead>
                        <tbody>
<?php foreach ($rezerwacje as $r): ?>
                            <tr>
                                <td><?= (int) $r['id'] ?></td>
                                <td class="termin"><?= e(termin_txt($r)) ?></td>
                                <td><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?><br><span class="muted"><?= e($r['k_email']) ?></span></td>
                                <td><?= e($r['usluga']) ?><?= $r['typ_uslugi'] === 'doby' ? ' <span class="plakietka-typ">doby</span>' : '' ?></td>
                                <td><?= e($r['p_imie'] . ' ' . $r['p_nazwisko']) ?></td>
                                <td><?= cena_txt($r['cena']) ?></td>
                                <td><?= znaczek_statusu($r['status']) ?></td>
                                <td class="akcje">
                                    <a class="btn small alt" href="admin-rezerwacja.php?id=<?= (int) $r['id'] ?>">Szczegóły</a>
                                    <?= przyciski_zmiany_statusu($r, $wroc) ?>
                                </td>
                            </tr>
<?php endforeach; ?>
<?php if (!$rezerwacje): ?>
                            <tr><td colspan="8">Brak rezerwacji spełniających kryteria.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_paginacje($p); ?>
<?php admin_koniec(); ?>
