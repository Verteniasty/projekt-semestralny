<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('klient');

if (metoda_post()) {
    sprawdz_csrf_lub_wroc('moje-rezerwacje.php');
    if (post('akcja') === 'anuluj') {

        if ($blad = anuluj_wlasna_rezerwacje(id_z_tekstu(post('id')), (int) $u['id'])) {
            flash('err', $blad);
        } else {
            flash('ok', 'Rezerwacja została anulowana.');
        }
    } else {
        flash('err', 'Nieznana operacja.');
    }
    redirect('moje-rezerwacje.php');
}

$anuluj = id_z_tekstu(pobierz('anuluj'));
if ($anuluj > 0) {
    $r = rezerwacja_po_id($anuluj);
    if ($r && (int) $r['id_uzytkownika'] === (int) $u['id']) {
        strona_potwierdzenia(
            'Anulowanie rezerwacji',
            "Czy na pewno anulować rezerwację: {$r['usluga']}, " . termin_txt($r) . '?',
            ['akcja' => 'anuluj', 'id' => $anuluj], 'moje-rezerwacje.php', 'Tak, anuluj rezerwację'
        );
    }
    flash('err', 'Nie znaleziono takiej rezerwacji.');
    redirect('moje-rezerwacje.php');
}

$f = ['klient' => (int) $u['id'], 'status' => isset(STATUSY[pobierz('status')]) ? pobierz('status') : ''];
$p = paginacja(policz_rezerwacje($f));
$rezerwacje = lista_rezerwacji($f, $p['limit'], $p['offset']);

render_naglowek('Moje rezerwacje – RentLoudCars', 'panel');
?>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <h1>Moje rezerwacje</h1>
                <p class="lead">Twoje wynajmy i wizyty, ich statusy oraz możliwość anulowania przyszłych rezerwacji.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <form method="get" action="moje-rezerwacje.php" class="filtry" role="search">
                    <?php pole_lista('status', 'Status', ['' => 'Wszystkie'] + STATUSY, $f['status']); ?>
                    <button type="submit" class="btn small">Filtruj</button>
                    <a class="btn small" href="rezerwacja.php">Nowa rezerwacja</a>
                </form>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <caption class="sr-only">Moje rezerwacje</caption>
                        <thead><tr><th scope="col">Termin</th><th scope="col">Usługa</th><th scope="col">Pracownik</th><th scope="col">Cena</th><th scope="col">Status</th><th scope="col">Akcje</th></tr></thead>
                        <tbody>
<?php foreach ($rezerwacje as $r):
    $mozeAnulowac = in_array($r['status'], ['oczekujace', 'potwierdzone'], true) && poczatek_wizyty($r) > time(); ?>
                            <tr>
                                <td class="termin"><?= e(termin_txt($r)) ?></td>
                                <td><?= e($r['usluga']) ?><?= $r['komentarz'] ? '<br><span class="muted">' . e($r['komentarz']) . '</span>' : '' ?></td>
                                <td><?= e($r['p_imie'] . ' ' . $r['p_nazwisko']) ?></td>
                                <td><?= cena_txt($r['cena']) ?></td>
                                <td><?= znaczek_statusu($r['status']) ?></td>
                                <td class="akcje"><?= $mozeAnulowac ? '<a class="btn small alt" href="moje-rezerwacje.php?anuluj=' . (int) $r['id'] . '">Anuluj</a>' : '' ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$rezerwacje): ?>
                            <tr><td colspan="6">Nie masz jeszcze rezerwacji. <a href="oferta.php">Zobacz ofertę</a>.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_paginacje($p); ?>
            </div>
        </section>
    </main>
<?php render_stopka(); ?>
