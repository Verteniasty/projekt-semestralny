<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();

$id = id_z_tekstu(metoda_post() ? post('id') : pobierz('id')) ?: id_z_tekstu(pobierz('anuluj'));
$r = rezerwacja_po_id($id);
if (!$r) {
    flash('err', 'Nie znaleziono takiej rezerwacji.');
    redirect('admin-rezerwacje.php');
}
$powrot = 'admin-rezerwacja.php?id=' . $id;

if (metoda_post()) {
    sprawdz_csrf_lub_wroc($powrot);
    if (post('akcja') === 'status') {
        $nowy = post('nowy');
        if ($blad = zmien_status($id, $nowy, (int) $u['id'])) {
            flash('err', $blad);
        } else {
            loguj_admina('rezerwacja_status', "Rezerwacja #{$id}: zmiana statusu na „" . etykieta_statusu($nowy) . '”');
            flash('ok', 'Status rezerwacji został zmieniony na „' . etykieta_statusu($nowy) . '”.');
        }
    }
    redirect($powrot);
}

if ($anuluj = id_z_tekstu(pobierz('anuluj'))) {
    strona_potwierdzenia('Anulowanie rezerwacji', "Czy na pewno anulować rezerwację #{$id}? Klient dostanie powiadomienie.",
        ['akcja' => 'status', 'id' => $id, 'nowy' => 'anulowane'], $powrot, 'Tak, anuluj rezerwację');
}

$r = rezerwacja_po_id($id);
$historia = historia_rezerwacji($id);

admin_start('Rezerwacja #' . $id, 'rezerwacje', 'Rezerwacja #' . $id);
?>
                <p><a href="admin-rezerwacje.php">‹ Wróć do listy rezerwacji</a></p>
                <div class="dwie-kolumny">
                    <div class="karta-formularz">
                        <h2>Dane rezerwacji</h2>
                        <dl class="szczegoly">
                            <dt>Status</dt><dd><?= znaczek_statusu($r['status']) ?></dd>
                            <dt>Usługa</dt><dd><?= e($r['usluga']) ?></dd>
                            <dt><?= $r['typ_uslugi'] === 'doby' ? 'Okres wynajmu' : 'Termin' ?></dt><dd><?= e(termin_txt($r)) ?></dd>
                            <dt>Cena</dt><dd><?= cena_txt($r['cena']) ?><?= $r['typ_uslugi'] === 'doby' ? ' <span class="muted">(' . e(doby_txt((int) $r['liczba_dob'])) . ')</span>' : '' ?></dd>
                            <dt><?= $r['typ_uslugi'] === 'doby' ? 'Wydanie i zwrot' : 'Pracownik' ?></dt><dd><?= e($r['p_imie'] . ' ' . $r['p_nazwisko']) ?></dd>
                            <dt>Klient</dt><dd><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?><br><?= e($r['k_email']) ?><br><?= e($r['k_tel']) ?></dd>
                            <dt>Komentarz klienta</dt><dd><?= e((string) $r['komentarz']) ?: '<span class="muted">brak</span>' ?></dd>
                            <dt>Utworzono</dt><dd><?= e((string) $r['created_at']) ?></dd>
                        </dl>
                        <p class="akcje"><?= przyciski_zmiany_statusu($r, 'id=' . $id) ?: '<span class="muted">Status końcowy, nie można go już zmienić.</span>' ?></p>
                    </div>
                    <div>
                        <h2>Historia statusów</h2>
                        <div class="tabela-wrap">
                            <table class="tabela">
                                <caption class="sr-only">Historia zmian statusu</caption>
                                <thead><tr><th scope="col">Kiedy</th><th scope="col">Zmiana</th><th scope="col">Kto</th></tr></thead>
                                <tbody>
<?php foreach ($historia as $h): ?>
                                    <tr>
                                        <td><?= e($h['utworzony']) ?></td>
                                        <td><?= $h['stary_status'] ? e(etykieta_statusu($h['stary_status'])) . ' → ' : 'Utworzono: ' ?><?= e(etykieta_statusu($h['nowy_status'])) ?></td>
                                        <td><?= $h['imie'] ? e($h['imie'] . ' ' . $h['nazwisko'] . ' (' . $h['rola'] . ')') : '<span class="muted">konto usunięte</span>' ?></td>
                                    </tr>
<?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
<?php admin_koniec(); ?>
