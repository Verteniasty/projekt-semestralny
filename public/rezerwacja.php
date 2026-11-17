<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('klient');

if (!metoda_post() && ($auto = usluga_po_slugu(pobierz('auto')))) {
    redirect('rezerwacja.php?usluga=' . (int) $auto['id']);
}

function doby_z_tekstu(string $v): int
{
    if ($v === '') {
        return 1;
    }
    return ctype_digit($v) && strlen($v) <= 3 ? (int) $v : 0;
}

if (metoda_post()) {
    $idUslugi = id_z_tekstu(post('usluga'));
    $idPracownika = id_z_tekstu(post('pracownik'));
    $data = post('data');
    $doby = doby_z_tekstu(post('doby'));
    $wroc = 'rezerwacja.php?' . http_build_query(array_filter(['usluga' => $idUslugi ?: '', 'pracownik' => $idPracownika ?: '', 'data' => $data, 'doby' => $doby > 1 ? $doby : '']));
    if (!csrf_sprawdz()) {
        flash('err', 'Formularz wygasł. Spróbuj jeszcze raz.');
        redirect($wroc);
    }

    $wynik = utworz_rezerwacje($u, $idUslugi, $idPracownika, $data, post('godzina'), post('komentarz'), $doby);
    if (isset($wynik['ok'])) {
        flash('ok', 'Rezerwacja została przyjęta i czeka na potwierdzenie przez pracownika.');
        redirect('moje-rezerwacje.php');
    }
    flash('err', $wynik['blad']);
    redirect($wroc);
}

$oferta = oferta_uslug();
$usluga = usluga_aktywna(id_z_tekstu(pobierz('usluga')));
$wynajem = $usluga && $usluga['typ'] === 'doby';
$pracownicy = $usluga ? pracownicy_uslugi((int) $usluga['id']) : [];
$idPracownika = id_z_tekstu(pobierz('pracownik'));
if (!isset($pracownicy[$idPracownika])) {
    $idPracownika = count($pracownicy) === 1 ? (int) array_key_first($pracownicy) : 0;
}
$data = data_poprawna(pobierz('data')) ? pobierz('data') : '';
$doby = $wynajem ? min(MAKS_DOB, doby_z_tekstu(pobierz('doby'))) : 1;

$sloty = [];
$brakSlotow = '';
if ($usluga && $idPracownika && $data) {
    $sloty = $wynajem
        ? wolne_sloty_wynajmu($idPracownika, (int) $usluga['id'], $data, $doby)
        : wolne_sloty($idPracownika, $data, (int) $usluga['czas_trwania'], (int) $u['id']);
    if (!$sloty) {
        $brakSlotow = $data < date('Y-m-d') ? 'Nie można rezerwować terminów z przeszłości.'
            : (!przedzialy_pracy($idPracownika, $data) ? 'Tego dnia pracownik nie pracuje. Wybierz inny dzień.'
            : ($wynajem ? 'Brak wolnych godzin odbioru na te daty: auto może być zajęte albo w dniu zwrotu pracownik nie pracuje. Zmień dzień lub liczbę dób.'
                        : 'Brak wolnych terminów tego dnia dla tej usługi. Wybierz inny dzień lub pracownika.'));
    }
}
$dataZwrotu = ($wynajem && $data) ? data_plus_dni($data, $doby) : '';
$razem = $usluga ? ((float) $usluga['cena'] * ($wynajem ? $doby : 1)) : 0;

$grupy = [];
foreach ($oferta as $us) {
    $grupy[$us['kategoria']][] = $us;
}

render_naglowek('Rezerwacja – RentLoudCars', 'oferta');
?>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <h1>Zarezerwuj termin</h1>
                <p class="lead">Wybierz auto lub usługę i pracownika, potem dzień<?= $wynajem ? ' i liczbę dób' : '' ?>. Pokażemy tylko godziny, które są naprawdę wolne.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap dwie-kolumny">
                <div>
                    <h2>1. Co i kiedy?</h2>
                    <form method="get" action="rezerwacja.php" id="wybor" class="karta-formularz" novalidate<?= $usluga ? ' data-typ="' . e($usluga['typ']) . '" data-cena="' . e((string) $usluga['cena']) . '"' : '' ?>>
                        <div class="row">
                            <label for="f-usluga">Auto lub usługa</label>
                            <select id="f-usluga" name="usluga" required>
                                <option value="">Wybierz auto lub usługę</option>
<?php foreach ($grupy as $kategoria => $lista): ?>
                                <optgroup label="<?= e($kategoria) ?>">
<?php foreach ($lista as $us): ?>
                                    <option value="<?= (int) $us['id'] ?>"<?= $usluga && (int) $usluga['id'] === (int) $us['id'] ? ' selected' : '' ?>><?= e($us['nazwa'] . ' – ' . strip_tags(cena_uslugi_txt($us))) ?></option>
<?php endforeach; ?>
                                </optgroup>
<?php endforeach; ?>
                            </select>
                        </div>
<?php if ($usluga): ?>
                        <?php pole_lista('pracownik', 'Pracownik (wydanie i zwrot auta)', ['' => 'Wybierz pracownika'] + $pracownicy, $idPracownika ? (string) $idPracownika : '', null, 'required'); ?>
                        <div class="row">
                            <label for="f-data"><?= $wynajem ? 'Dzień odbioru auta' : 'Dzień' ?></label>
                            <input id="f-data" name="data" type="date" value="<?= e($data) ?>" min="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d', strtotime('+' . MAKS_DNI_NAPRZOD . ' days'))) ?>" required>
                        </div>
<?php if ($wynajem): ?>
                        <div class="row">
                            <label for="f-doby">Liczba dób (1–<?= MAKS_DOB ?>)</label>
                            <input id="f-doby" name="doby" type="number" min="1" max="<?= MAKS_DOB ?>" step="1" value="<?= (int) $doby ?>" required>
                        </div>
<?php endif; ?>
<?php if (!$pracownicy): ?>
                        <p class="komunikat komunikat-err" role="alert">Ta usługa nie ma obecnie dostępnych pracowników.</p>
<?php endif; ?>
<?php endif; ?>
                        <button type="submit" class="btn" id="pokaz"><?= $usluga ? 'Pokaż wolne terminy' : 'Dalej' ?></button>
                    </form>
                </div>
                <div>
                    <h2>2. Wybierz godzinę<?= $wynajem ? ' odbioru' : '' ?></h2>
<?php if ($usluga && $idPracownika && $data): ?>
                    <form method="post" action="rezerwacja.php" id="rezerwuj" class="karta-formularz" novalidate>
                        <?= csrf_pole() ?>
                        <input type="hidden" name="usluga" value="<?= (int) $usluga['id'] ?>">
                        <input type="hidden" name="pracownik" value="<?= (int) $idPracownika ?>">
                        <input type="hidden" name="data" value="<?= e($data) ?>">
<?php if ($wynajem): ?>
                        <input type="hidden" name="doby" value="<?= (int) $doby ?>">
<?php endif; ?>
                        <p id="podsumowanie"><strong><?= e($usluga['nazwa']) ?></strong><br>
<?php if ($wynajem): ?>
                           Odbiór: <?= e($data) ?>, zwrot: <?= e($dataZwrotu) ?> o tej samej godzinie (<?= e(doby_txt($doby)) ?>).<br>
                           Razem: <strong><?= cena_txt($razem) ?></strong> (<?= cena_txt($usluga['cena']) ?> za dobę). Obsługuje: <?= e($pracownicy[$idPracownika]) ?>.</p>
<?php else: ?>
                           <?= e($pracownicy[$idPracownika]) ?>, <?= e($data) ?><br>
                           Czas trwania: <?= e(czas_txt((int) $usluga['czas_trwania'])) ?>, cena: <strong><?= cena_txt($usluga['cena']) ?></strong></p>
<?php endif; ?>
                        <fieldset class="sloty" id="sloty">
                            <legend><?= $wynajem ? 'Wolne godziny odbioru' : 'Wolne godziny rozpoczęcia' ?></legend>
<?php foreach ($sloty as $s): ?>
                            <label class="slot"><input type="radio" name="godzina" value="<?= e($s) ?>" required><span><?= e($s) ?></span></label>
<?php endforeach; ?>
                            <p class="muted" id="brak-slotow"<?= $sloty ? ' hidden' : '' ?>><?= e($brakSlotow) ?></p>
                        </fieldset>
                        <div id="reszta"<?= $sloty ? '' : ' hidden' ?>>
                            <?php pole_tekst('komentarz', 'Komentarz (opcjonalnie)', '', null, 'maxlength="500"'); ?>
                            <button type="submit" class="btn">Zarezerwuj</button>
                        </div>
                    </form>
<?php else: ?>
                    <p class="muted" id="podpowiedz">Najpierw wybierz auto lub usługę, pracownika i dzień po lewej stronie.</p>
                    <form method="post" action="rezerwacja.php" id="rezerwuj" class="karta-formularz" hidden novalidate><?= csrf_pole() ?></form>
<?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <script src="<?= e(zasob('rezerwacja.js')) ?>"></script>
<?php render_stopka(); ?>
