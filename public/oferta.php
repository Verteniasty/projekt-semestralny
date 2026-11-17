<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/flota.php';


$techniczne = [];
foreach (flota() as $a) {
    $techniczne[$a['slug']] = $a;
}

$uslugi = oferta_uslug();
$kategorie = [];
foreach ($uslugi as $us) {
    $kategorie[$us['kategoria']][] = $us + ['pracownicy' => array_values(pracownicy_uslugi((int) $us['id']))];
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
<?php if ($us['pracownicy']): ?>
                        <a class="btn small" href="rezerwacja.php?usluga=<?= (int) $us['id'] ?>"><?= $us['typ'] === 'doby' ? 'Wynajmij na doby' : 'Umów termin' ?></a>
<?php endif; ?>
                    </article>
<?php endforeach; ?>
                </div>
<?php endforeach; ?>
<?php if (!$kategorie): ?>
                <p>Oferta jest chwilowo pusta. Zajrzyj tu później.</p>
<?php endif; ?>
            </div>
        </section>
    </main>
<?php render_stopka(); ?>
