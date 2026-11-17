<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('pracownik');


$st = db()->prepare('SELECT id, opis FROM pracownicy WHERE id_uzytkownika = ? AND aktywne = 1');
$st->execute([$u['id']]);
$pracownik = $st->fetch();

$widoki = ['nadchodzace' => 'Nadchodzące', 'historia' => 'Historia', 'wszystkie' => 'Wszystkie'];
$widok = isset($widoki[pobierz('widok')]) ? pobierz('widok') : 'nadchodzace';
$status = isset(STATUSY[pobierz('status')]) ? pobierz('status') : '';
$klucze = ['widok', 'status', 'strona'];

if (metoda_post()) {
    $wroc = post('wroc');
    sprawdz_csrf_lub_wroc(adres_powrotu('panel-pracownika.php', $wroc, $klucze));
    if (!$pracownik) {
        flash('err', 'Brak aktywnego profilu pracownika.');
    } elseif (post('akcja') === 'status') {
        $id = id_z_tekstu(post('id'));
        $nowy = post('nowy');

        if ($blad = zmien_status($id, $nowy, (int) $u['id'], (int) $pracownik['id'])) {
            flash('err', $blad);
        } else {
            flash('ok', 'Status wizyty został zmieniony na „' . etykieta_statusu($nowy) . '”.');
        }
    } else {
        flash('err', 'Nieznana operacja.');
    }
    redirect(adres_powrotu('panel-pracownika.php', $wroc, $klucze));
}

$anuluj = id_z_tekstu(pobierz('anuluj'));
if ($anuluj > 0 && $pracownik) {
    $r = rezerwacja_po_id($anuluj);
    if ($r && (int) $r['id_pracownika'] === (int) $pracownik['id']) {
        strona_potwierdzenia('Anulowanie wizyty', "Czy na pewno anulować: {$r['usluga']}, " . termin_txt($r) . ", {$r['k_imie']} {$r['k_nazwisko']}? Klient dostanie powiadomienie.",
            ['akcja' => 'status', 'id' => $anuluj, 'nowy' => 'anulowane', 'wroc' => pobierz('wroc')], adres_powrotu('panel-pracownika.php', pobierz('wroc'), $klucze), 'Tak, anuluj wizytę');
    }
    flash('err', 'Nie znaleziono takiej wizyty.');
    redirect('panel-pracownika.php');
}

$uslugi = [];
$wizyty = [];
$p = paginacja(0);
$dzis = date('Y-m-d');
if ($pracownik) {
    $st = db()->prepare('SELECT us.nazwa FROM uslugi_pracownikow up JOIN uslugi us ON us.id = up.id_uslugi WHERE up.id_pracownika = ? ORDER BY us.nazwa');
    $st->execute([$pracownik['id']]);
    $uslugi = $st->fetchAll(PDO::FETCH_COLUMN);

    $f = ['pracownik' => (int) $pracownik['id'], 'status' => $status];
    if ($widok === 'nadchodzace') {
        $f['od'] = $dzis;
    } elseif ($widok === 'historia') {
        $f['zakonczone_przed'] = $dzis;
    }
    $p = paginacja(policz_rezerwacje($f));
    $wizyty = lista_rezerwacji($f, $p['limit'], $p['offset'], $widok === 'nadchodzace');
}
$wroc = (string) parse_url(url_z_parametrami(['strona' => $p['strona'] > 1 ? (string) $p['strona'] : null]), PHP_URL_QUERY);

render_naglowek('Panel pracownika – RentLoudCars', 'panel');
?>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <h1>Panel pracownika</h1>
                <p class="lead">Witaj, <?= e($u['imie']) ?>. Tu widzisz swoje wizyty, wydania i zwroty aut oraz zmieniasz ich statusy.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
<?php if (!$pracownik): ?>
                <p class="komunikat komunikat-err">Brak aktywnego profilu pracownika. Skontaktuj się z administratorem.</p>
<?php else: ?>
                <div class="panel-grid">
                    <article class="stat">
                        <h2>Twoje stanowisko</h2>
                        <p><?= e((string) $pracownik['opis']) ?></p>
                        <a class="btn small" href="profil.php">Edytuj profil</a>
                    </article>
                    <article class="stat">
                        <h2>Obsługiwane usługi</h2>
<?php if ($uslugi): ?>
                        <ul>
<?php foreach ($uslugi as $nazwa): ?>
                            <li><?= e($nazwa) ?></li>
<?php endforeach; ?>
                        </ul>
<?php else: ?>
                        <p>Nie przypisano jeszcze żadnych usług.</p>
<?php endif; ?>
                    </article>
                </div>

                <h2 class="odstep">Moje wizyty</h2>
                <ul class="zakladki" aria-label="Widok wizyt">
<?php foreach ($widoki as $klucz => $nazwa): ?>
                    <li><a href="panel-pracownika.php?widok=<?= e($klucz) ?>"<?= $klucz === $widok ? ' aria-current="page"' : '' ?>><?= e($nazwa) ?></a></li>
<?php endforeach; ?>
                </ul>
                <form method="get" action="panel-pracownika.php" class="filtry">
                    <input type="hidden" name="widok" value="<?= e($widok) ?>">
                    <?php pole_lista('status', 'Status', ['' => 'Wszystkie'] + STATUSY, $status); ?>
                    <button type="submit" class="btn small">Filtruj</button>
                </form>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <caption class="sr-only">Moje wizyty</caption>
                        <thead><tr><th scope="col">Termin</th><th scope="col">Klient</th><th scope="col">Usługa</th><th scope="col">Status</th><th scope="col">Akcje</th></tr></thead>
                        <tbody>
<?php foreach ($wizyty as $r): ?>
                            <tr>
                                <td class="termin"><?= e(termin_txt($r)) ?></td>
                                <td><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?><br><span class="muted"><?= e($r['k_tel']) ?></span><?= $r['komentarz'] ? '<br><span class="muted">„' . e($r['komentarz']) . '”</span>' : '' ?></td>
                                <td><?= e($r['usluga']) ?><br><span class="muted"><?= cena_txt($r['cena']) ?></span></td>
                                <td><?= znaczek_statusu($r['status']) ?></td>
                                <td class="akcje"><?= przyciski_zmiany_statusu($r, $wroc) ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$wizyty): ?>
                            <tr><td colspan="5">Brak wizyt w tym widoku.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_paginacje($p); ?>
<?php endif; ?>
            </div>
        </section>
    </main>
<?php render_stopka(); ?>
