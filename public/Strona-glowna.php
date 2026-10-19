<?php
require __DIR__ . '/../src/bootstrap.php';
render_naglowek('RentLoudCars – wypożyczalnia głośnych aut', 'glowna', [], 'Wypożyczalnia samochodów z charakterem. V8, turbo i wydechy, które słychać. Rezerwacja online od 1 doby.');
?>
    <main id="main">

        <section class="hero">
            <div class="wrap">
                <h1>Wynajmij auto,<br>które słychać<br>zanim wjedziesz.</h1>
                <p class="lead">V8, turbo i sportowe wydechy. Odbierasz kluczyki w 15 minut, bez kaucji ukrytej drobnym drukiem.</p>
                <div class="hero-actions">
                    <a class="btn" href="#rezerwacja">Zarezerwuj auto</a>
                    <a class="btn ghost" href="Garaz.php">Zobacz garaż</a>
                </div>
                <dl class="facts">
                    <div><dt>12</dt><dd>aut w garażu</dd></div>
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
                    <article class="card">
                        <div class="thumb" role="img" aria-label="Miejsce na zdjęcie Forda Mustanga">Mustang GT</div>
                        <h3>Ford Mustang GT</h3>
                        <p>5.0 V8, 450 KM, manual. Klasyk, który brzmi jak amerykańska niedziela.</p>
                        <p class="price"><strong>690 zł</strong> / doba</p>
                        <a class="btn small" href="#rezerwacja" data-car="mustang">Wybierz</a>
                    </article>
                    <article class="card">
                        <div class="thumb" role="img" aria-label="Miejsce na zdjęcie BMW M3">BMW M3</div>
                        <h3>BMW M3 Competition</h3>
                        <p>3.0 biturbo, 510 KM, napęd na tył. Do jazdy, przy której nie odbiera się telefonów.</p>
                        <p class="price"><strong>990 zł</strong> / doba</p>
                        <a class="btn small" href="#rezerwacja" data-car="m3">Wybierz</a>
                    </article>
                    <article class="card">
                        <div class="thumb" role="img" aria-label="Miejsce na zdjęcie Audi RS3">Audi RS3</div>
                        <h3>Audi RS3</h3>
                        <p>Pięć cylindrów, 400 KM, quattro. Głośne, szybkie i sprawdzi się też w deszczu.</p>
                        <p class="price"><strong>820 zł</strong> / doba</p>
                        <a class="btn small" href="#rezerwacja" data-car="rs3">Wybierz</a>
                    </article>
                </div>
            </div>
        </section>

        <section class="block alt" id="jak-to-dziala">
            <div class="wrap">
                <h2>Jak to działa</h2>
                <ol class="steps">
                    <li><h3>Wybierasz auto i termin</h3><p>Formularz poniżej liczy koszt od razu.</p></li>
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
                <h2>Zarezerwuj auto</h2>
                <form id="booking" novalidate>
                    <div class="row">
                        <label for="car">Auto</label>
                        <select id="car" name="car" required>
                            <option value="">Wybierz auto</option>
                            <option value="mustang" data-price="690">Ford Mustang GT – 690 zł</option>
                            <option value="m3" data-price="990">BMW M3 Competition – 990 zł</option>
                            <option value="rs3" data-price="820">Audi RS3 – 820 zł</option>
                        </select>
                        <p class="err" data-for="car"></p>
                    </div>
                    <div class="grid2">
                        <div class="row">
                            <label for="from">Odbiór</label>
                            <input type="date" id="from" name="from" required>
                            <p class="err" data-for="from"></p>
                        </div>
                        <div class="row">
                            <label for="to">Zwrot</label>
                            <input type="date" id="to" name="to" required>
                            <p class="err" data-for="to"></p>
                        </div>
                    </div>
                    <div class="grid2">
                        <div class="row">
                            <label for="name">Imię i nazwisko</label>
                            <input type="text" id="name" name="name" autocomplete="name" required>
                            <p class="err" data-for="name"></p>
                        </div>
                        <div class="row">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" autocomplete="email" required>
                            <p class="err" data-for="email"></p>
                        </div>
                    </div>
                    <p class="total" aria-live="polite" id="total">Wybierz auto i daty, żeby zobaczyć cenę.</p>
                    <button type="submit" class="btn">Wyślij rezerwację</button>
                    <p class="ok" id="ok" hidden></p>
                </form>
            </div>
        </section>

    </main>
<?php render_stopka(); ?>
