<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/flota.php';


$techniczne = [];
foreach (flota() as $a) {
    $techniczne[$a['slug']] = $a;
}

$uslugi = oferta_uslug();

// Filtry z adresu URL: kategoria, rodzaj, data z kalendarza i "tylko dostępne"
$wszystkieKategorie = [];
foreach ($uslugi as $us) {
    $wszystkieKategorie[(int) $us['id_kategorii']] = $us['kategoria'];
}
$fKategoria = id_z_tekstu(pobierz('kategoria'));
if (!isset($wszystkieKategorie[$fKategoria])) {
    $fKategoria = 0;
}
$fTyp = in_array(pobierz('typ'), ['doby', 'godziny'], true) ? pobierz('typ') : '';
$fData = pobierz('data');
if (!data_poprawna($fData) || $fData < date('Y-m-d') || $fData > data_plus_dni(date('Y-m-d'), MAKS_DNI_NAPRZOD)) {
    $fData = '';
}
$fTylkoDostepne = $fData !== '' && pobierz('dostepne') === '1';

$kategorie = [];
$dostepnosc = [];
foreach ($uslugi as $us) {
    if (($fKategoria && (int) $us['id_kategorii'] !== $fKategoria) || ($fTyp && $us['typ'] !== $fTyp)) {
        continue;
    }
    $pracownicy = pracownicy_uslugi((int) $us['id']);
    $wolna = null;
    if ($fData !== '') {
        $wolna = false;
        foreach (array_keys($pracownicy) as $idPrac) {
            $sloty = $us['typ'] === 'doby'
                ? wolne_sloty_wynajmu((int) $idPrac, (int) $us['id'], $fData, 1)
                : wolne_sloty((int) $idPrac, $fData, (int) $us['czas_trwania']);
            if ($sloty) {
                $wolna = true;
                break;
            }
        }
        if ($fTylkoDostepne && !$wolna) {
            continue;
        }
    }
    $dostepnosc[(int) $us['id']] = $wolna;
    $kategorie[$us['kategoria']][] = $us + ['pracownicy' => array_values($pracownicy)];
}

render_naglowek('Oferta – RentLoudCars', 'oferta', [], 'Oferta RentLoudCars: głośne i tuningowane auta na doby oraz jazdy próbne i sesje. Zarezerwuj termin online.');
?>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <h1>Oferta</h1>
                <p class="lead">Auta wynajmujemy na doby, jazdy próbne i sesje zdjęciowe na godziny. Wybierz auto, pracownika i termin. Wolne godziny zobaczysz od razu.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <form class="filtry" method="get" action="oferta.php" aria-label="Filtry oferty">
                    <div class="row">
                        <label for="f-kategoria">Kategoria</label>
                        <select id="f-kategoria" name="kategoria">
                            <option value="">Wszystkie</option>
<?php foreach ($wszystkieKategorie as $idK => $nazwaK): ?>
                            <option value="<?= (int) $idK ?>"<?= $fKategoria === $idK ? ' selected' : '' ?>><?= e($nazwaK) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <label for="f-rodzaj">Rodzaj</label>
                        <select id="f-rodzaj" name="typ">
                            <option value="">Wszystkie</option>
                            <option value="doby"<?= $fTyp === 'doby' ? ' selected' : '' ?>>Wynajem na doby</option>
                            <option value="godziny"<?= $fTyp === 'godziny' ? ' selected' : '' ?>>Usługi na godziny</option>
                        </select>
                    </div>
                    <div class="row">
                        <label for="f-data">Data (kalendarz)</label>
                        <input type="date" id="f-data" name="data" value="<?= e($fData) ?>" min="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="row">
                        <label class="check"><input type="checkbox" name="dostepne" value="1"<?= $fTylkoDostepne ? ' checked' : '' ?>> Tylko dostępne w tym dniu</label>
                    </div>
                    <div class="row">
                        <button class="btn small" type="submit">Filtruj</button>
                        <a class="btn small alt" href="oferta.php">Wyczyść</a>
                    </div>
                </form>
<?php if (count($kategorie) > 1): ?>
                <ul class="zakladki" aria-label="Kategorie oferty">
<?php foreach ($kategorie as $nazwa => $lista): ?>
                    <li><a href="#kat-<?= (int) $lista[0]['id_kategorii'] ?>"><?= e($nazwa) ?></a></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
<?php foreach ($kategorie as $nazwa => $lista): ?>
                <h2 class="odstep-maly" id="kat-<?= (int) $lista[0]['id_kategorii'] ?>"><?= e($nazwa) ?></h2>
                <div class="cards">
<?php foreach ($lista as $us): $t = $techniczne[$us['slug'] ?? ''] ?? null; ?>
                    <article class="card oferta-karta">
                        <h3><?= e($us['nazwa']) ?></h3>
                        <p><?= e((string) $us['opis']) ?></p>
<?php if ($t): ?>
                        <p class="meta"><?= (int) $t['rok'] ?> · <span class="moc"><?= e(moc_txt($t)) ?> KM</span> · 0–100 w <?= e(str_replace('.', ',', (string) $t['przysp'])) ?> s<br><?= e($t['silnik']) ?></p>
<?php elseif ($us['typ'] === 'godziny'): ?>
                        <p class="meta">Czas: <?= e(czas_txt((int) $us['czas_trwania'])) ?></p>
<?php endif; ?>
                        <p class="meta">Wydanie i zwrot: <?= e($us['pracownicy'] ? implode(', ', $us['pracownicy']) : 'brak dostępnych pracowników') ?></p>
                        <p class="price"><strong><?= e(cena_uslugi_txt($us)) ?></strong></p>
<?php if ($dostepnosc[(int) $us['id']] !== null): ?>
                        <p class="meta"><?= $dostepnosc[(int) $us['id']] ? 'Wolne ' . e(date('d.m.Y', strtotime($fData))) : 'Zajęte ' . e(date('d.m.Y', strtotime($fData))) ?></p>
<?php endif; ?>
<?php if ($us['pracownicy']): ?>
                        <a class="btn small" href="rezerwacja.php?usluga=<?= (int) $us['id'] ?><?= $fData !== '' ? '&amp;data=' . e($fData) : '' ?>"><?= $us['typ'] === 'doby' ? 'Wynajmij na doby' : 'Umów termin' ?></a>
<?php endif; ?>
                    </article>
<?php endforeach; ?>
                </div>
<?php endforeach; ?>
<?php if (!$kategorie): ?>
                <p>Brak ofert spełniających wybrane filtry. Zmień kryteria lub <a href="oferta.php">wyczyść filtry</a>.</p>
<?php endif; ?>
            </div>
        </section>
    </main>
<?php render_stopka(); ?>
