<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/flota.php';

$wybrane = ['bmw-m5-f90-competition', 'brabus-g800-widestar', 'gpower-m5-hurricane-rs'];
$polecane = [];
foreach (flota() as $a) {
    if (in_array($a['slug'], $wybrane, true)) {
        $a['rezerwuj'] = 'oferta.php';
        $polecane[] = $a;
    }
}
try {
    $st = db()->prepare('SELECT id, slug, cena FROM uslugi WHERE slug IS NOT NULL AND typ = ? AND aktywne = 1');
    $st->execute(['doby']);
    $zBazy = [];
    foreach ($st->fetchAll() as $w) {
        $zBazy[$w['slug']] = $w;
    }
    foreach ($polecane as $i => $a) {
        if (isset($zBazy[$a['slug']])) {
            $polecane[$i]['cena'] = (int) round((float) $zBazy[$a['slug']]['cena']);
            $polecane[$i]['rezerwuj'] = 'rezerwacja.php?usluga=' . (int) $zBazy[$a['slug']]['id'];
        }
    }
} catch (Throwable $ex) {
    error_log('Strona główna: brak danych z bazy: ' . $ex->getMessage());
}
$liczbaAut = count(flota());

render_naglowek('RentLoudCars – wypożyczalnia głośnych aut', 'glowna', [], 'Wypożyczalnia samochodów z charakterem. V8, turbo i wydechy, które słychać. Rezerwacja online od 1 doby.');
?>
    <main id="main">

        <section class="hero">
            <div class="wrap">
                <h1>Wynajmij auto,<br>które słychać<br>zanim wjedziesz.</h1>
                <p class="lead">V8, turbo i sportowe wydechy. Odbierasz kluczyki w 15 minut, bez kaucji ukrytej drobnym drukiem.</p>
                <div class="hero-actions">
                    <a class="btn" href="oferta.php">Zarezerwuj auto</a>
                    <a class="btn ghost" href="Garaz.php">Zobacz garaż</a>
                </div>
                <dl class="facts">
                    <div><dt><?= (int) $liczbaAut ?></dt><dd>aut w garażu</dd></div>
                    <div><dt>od 1 doby</dt><dd>minimalny wynajem</dd></div>
                    <div><dt>24/7</dt><dd>pomoc drogowa</dd></div>
                </dl>
            </div>
        </section>

        <section class="block" id="flota">
            <div class="wrap">
                <h2>Najczęściej wynajmowane</h2>
                <p class="sub">Ceny za dobę, limit 250 km. Pełna oferta w garażu.</p>
                <div class="cards">
<?php foreach ($polecane as $a): ?>
                    <article class="card">
<?php if (is_file(__DIR__ . '/auta/' . $a['slug'] . '.jpg')): ?>
                        <div class="thumb"><img src="auta/<?= e($a['slug']) ?>.jpg" alt="<?= e($a['nazwa']) ?>" loading="lazy"></div>
<?php else: ?>
                        <div class="thumb brak"><span class="nazwa-auta"><?= e($a['nazwa']) ?></span></div>
<?php endif; ?>
                        <h3><?= e($a['nazwa']) ?></h3>
                        <p><?= e($a['silnik']) ?>, <?= e(moc_txt($a)) ?> KM. <?= e($a['skrot']) ?></p>
                        <p class="price"><strong><?= number_format((int) $a['cena'], 0, ',', "\u{00A0}") ?> zł</strong> / doba</p>
                        <a class="btn small" href="<?= e($a['rezerwuj']) ?>">Wynajmij</a>
                    </article>
<?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="block alt" id="jak-to-dziala">
            <div class="wrap">
                <h2>Jak to działa</h2>
                <ol class="steps">
                    <li><h3>Wybierasz auto i doby</h3><p>Formularz pokazuje wolne godziny odbioru i liczy koszt od razu.</p></li>
                    <li><h3>Potwierdzamy rezerwację</h3><p>Oddzwaniamy lub piszemy w ciągu godziny.</p></li>
                    <li><h3>Odbierasz kluczyki</h3><p>Wystarczy prawo jazdy i dowód. Umowa trwa 15 minut.</p></li>
                </ol>
            </div>
        </section>

        <section class="block" id="warunki">
            <div class="wrap">
                <h2>Warunki wynajmu</h2>
                <ul class="terms">
                    <li><strong>Wiek kierowcy:</strong> od 25 lat, prawo jazdy kat. B min. 3 lata.</li>
                    <li><strong>Kaucja:</strong> zwracana w dniu oddania auta, jeśli nie ma szkód.</li>
                    <li><strong>Ubezpieczenie:</strong> OC i AC w cenie, udział własny zależy od modelu.</li>
                    <li><strong>Paliwo:</strong> oddajesz z takim poziomem, z jakim odebrałeś.</li>
                </ul>
            </div>
        </section>

        <section class="block alt" id="rezerwacja">
            <div class="wrap narrow">
                <h2>Zarezerwuj auto na doby</h2>
                <p>Wybierz auto, pracownika, dzień odbioru i liczbę dób. Pokażemy tylko godziny, które są naprawdę wolne, a rezerwację zobaczysz w swoim koncie. Chcesz najpierw sprawdzić auto? Umów jazdę próbną na godzinę.</p>
                <p>
                    <a class="btn" href="rezerwacja.php">Wybierz auto i termin</a>
                    <a class="btn alt" href="oferta.php">Zobacz ofertę</a>
                </p>
            </div>
        </section>

    </main>
<?php render_stopka(); ?>
