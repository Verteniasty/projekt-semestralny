<?php
require __DIR__ . '/../src/bootstrap.php';
render_naglowek('Kontakt – RentLoudCars', 'kontakt', [], 'Skontaktuj się z RentLoudCars: telefon, e-mail, adres i formularz kontaktowy.');
?>
    <main id="main">

        <section class="page-hero">
            <div class="wrap">
                <h1>Kontakt</h1>
                <p class="lead">Pytania o auta, terminy lub cenę? Odpowiadamy w ciągu godziny w dni robocze.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap two-col">
                <div>
                    <h2>Dane kontaktowe</h2>
                    <address>
                        <p><strong>Telefon</strong><br><a href="tel:+48000000000">+48 000 000 000</a></p>
                        <p><strong>E-mail</strong><br><a href="mailto:kontakt@rentloudcars.pl">kontakt@rentloudcars.pl</a></p>
                        <p><strong>Adres</strong><br>ul. Przykładowa 1, 00-000 Warszawa</p>
                    </address>
                    <h3>Godziny otwarcia</h3>
                    <p>Pon–Pt: 8:00–19:00<br>Sob: 9:00–15:00<br>Niedz.: po wcześniejszym umówieniu</p>
                </div>

                <form id="contact" novalidate>
                    <div class="row">
                        <label for="c-name">Imię i nazwisko</label>
                        <input type="text" id="c-name" name="name" autocomplete="name" required>
                        <p class="err" data-for="name"></p>
                    </div>
                    <div class="row">
                        <label for="c-email">E-mail</label>
                        <input type="email" id="c-email" name="email" autocomplete="email" required>
                        <p class="err" data-for="email"></p>
                    </div>
                    <div class="row">
                        <label for="c-msg">Wiadomość</label>
                        <textarea id="c-msg" name="message" rows="5" required></textarea>
                        <p class="err" data-for="message"></p>
                    </div>
                    <button type="submit" class="btn">Wyślij wiadomość</button>
                    <p class="ok" id="contact-ok" hidden></p>
                </form>
            </div>
        </section>

    </main>
<?php render_stopka(); ?>
