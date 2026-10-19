<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/flota.php';

$etykiety = ['suv' => 'SUV', 'sedan' => 'Sedan', 'coupe' => 'Coupe', 'tuned' => 'Tuned'];

$auta = array_map(function (array $a): array {
    $plik = 'auta/' . $a['slug'] . '.jpg';
    $a['zdjecie'] = is_file(__DIR__ . '/' . $plik) ? $plik : null;
    return $a;
}, flota());

$mocMin = (int) (floor(min(array_column($auta, 'moc')) / 50) * 50);
$mocMax = (int) (ceil(max(array_column($auta, 'moc')) / 50) * 50);

render_naglowek('Garaż – RentLoudCars', 'garaz', ['garaz-style.css'], 'Samochody sportowe i tuningowane do wynajęcia: SUV, sedan, coupe i tuned. Filtruj po mocy i zarezerwuj online.');
?>
    <main id="main">

        <section class="garage-hero">
<?php if (is_file(__DIR__ . '/garaz-hero.jpg')): ?>
            <img src="garaz-hero.jpg" alt="" loading="eager">
<?php endif; ?>
            <div class="wrap">
                <h1>Garaż</h1>
                <p class="lead">Samochody sportowe i tuningowane do wynajęcia. Ceny od 500 do 10 000 zł za dobę.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <div class="filters" role="group" aria-label="Filtruj auta">
                    <button type="button" class="chip active" data-kat="all">Wszystkie</button>
<?php foreach ($etykiety as $klucz => $nazwa): ?>
                    <button type="button" class="chip" data-kat="<?= e($klucz) ?>"><?= e($nazwa) ?></button>
<?php endforeach; ?>
                    <label class="sort">Sortuj
                        <select id="sort">
                            <option value="cena-asc">Cena rosnąco</option>
                            <option value="cena-desc">Cena malejąco</option>
                            <option value="moc-desc">Moc malejąco</option>
                        </select>
                    </label>
                </div>

                <div class="suwak">
                    <label for="moc-min">Moc od: <strong><span id="moc-wart"><?= $mocMin ?></span> KM</strong></label>
                    <input type="range" id="moc-min" min="<?= $mocMin ?>" max="<?= $mocMax ?>" step="50" value="<?= $mocMin ?>">
                </div>
                <p class="licznik" id="licznik" aria-live="polite"></p>

                <div class="cards" id="flota">
<?php foreach ($auta as $a): ?>
                    <article class="card auto<?= $a['kat'] === 'tuned' ? ' tuned' : '' ?>" data-slug="<?= e($a['slug']) ?>" data-kat="<?= e($a['kat']) ?>" data-moc="<?= (int) $a['moc'] ?>" data-cena="<?= (int) $a['cena'] ?>">
                        <div class="thumb<?= $a['zdjecie'] ? '' : ' brak' ?>">
<?php if ($a['zdjecie']): ?>
                            <img src="<?= e($a['zdjecie']) ?>" alt="<?= e($a['nazwa']) ?>" loading="lazy">
<?php else: ?>
                            <span class="nazwa-auta"><?= e($a['nazwa']) ?></span>
<?php endif; ?>
                            <span class="plakietka"><?= e($etykiety[$a['kat']]) ?></span>
                            <span class="skrot"><?= e($a['skrot']) ?></span>
                        </div>
                        <h3><button type="button" class="auto-open"><?= e($a['nazwa']) ?></button></h3>
                        <p class="meta"><?= (int) $a['rok'] ?> · <span class="moc"><?= e(moc_txt($a)) ?> KM</span> · <?= e($a['silnik']) ?></p>
                        <p class="price"><strong><?= number_format($a['cena'], 0, ',', "\u{00A0}") ?> zł</strong> / doba</p>
                        <div class="auto-foot">
                            <button type="button" class="btn small alt auto-open">Szczegóły</button>
                            <a class="btn small" href="Strona-glowna.php#rezerwacja">Rezerwuj</a>
                        </div>
                    </article>
<?php endforeach; ?>
                </div>
                <p class="empty" id="pusto" hidden>Brak aut spełniających te kryteria. Zmień kategorię lub obniż minimalną moc.</p>
            </div>
        </section>

        <dialog id="auto-modal" aria-labelledby="m-nazwa">
            <div class="m-media" id="m-media"></div>
            <div class="m-body">
                <button type="button" class="m-close" aria-label="Zamknij">×</button>
                <p class="m-kat" id="m-kat"></p>
                <h2 id="m-nazwa"></h2>
                <p id="m-opis"></p>
                <dl class="m-spec" id="m-spec"></dl>
                <p class="price"><strong id="m-cena"></strong> / doba</p>
                <a class="btn" href="Strona-glowna.php#rezerwacja">Zarezerwuj</a>
            </div>
        </dialog>

    </main>

    <script type="application/json" id="flota-dane"><?= json_encode($auta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script src="garaz.js"></script>
<?php render_stopka(); ?>