<?php
require __DIR__ . '/../src/bootstrap.php';
render_naglowek('O nas – RentLoudCars', 'onas', [], 'Poznaj RentLoudCars: wypożyczalnię aut dla ludzi, którzy lubią słyszeć silnik.');
?>
    <main id="main">

        <section class="page-hero">
            <div class="wrap">
                <h1>Auta, które<br>mają głos.</h1>
                <p class="lead">RentLoudCars to wypożyczalnia dla ludzi, którzy lubią słyszeć silnik.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap two-col">
                <div>
                    <h2>Nasza historia</h2>
                    <p>Zaczęliśmy od jednego Mustanga i pytania: dlaczego wynajem auta ma być nudny? Dziś mamy garaż pełen samochodów sportowych i klientów, którzy wracają po kolejne.</p>
                    <p>Każde auto przechodzi przegląd przed wydaniem. Czyste, zatankowane, gotowe do jazdy.</p>
                </div>
                <dl class="facts dark">
                    <div><dt>2019</dt><dd>rok założenia</dd></div>
                    <div><dt>12</dt><dd>aut w garażu</dd></div>
                    <div><dt>1 500+</dt><dd>zrealizowanych wynajmów</dd></div>
                </dl>
            </div>
        </section>

        <section class="block alt">
            <div class="wrap">
                <h2>Na czym nam zależy</h2>
                <div class="cards">
                    <article class="card"><h3>Prostota</h3><p>Rezerwacja online, umowa w 15 minut, bez ukrytych opłat.</p></article>
                    <article class="card"><h3>Stan techniczny</h3><p>Serwis przed każdym wynajmem i pomoc drogowa 24/7.</p></article>
                    <article class="card"><h3>Uczciwe zasady</h3><p>Kaucja wraca w dniu zwrotu auta, jeśli nie ma szkód.</p></article>
                </div>
            </div>
        </section>

        <section class="block">
            <div class="wrap narrow">
                <h2>Najczęstsze pytania</h2>
                <details><summary>Od ilu lat można wynająć auto?</summary><p>Od 25 lat, z prawem jazdy kat. B od minimum 3 lat.</p></details>
                <details><summary>Czy jest limit kilometrów?</summary><p>Tak, 250 km na dobę. Każdy kolejny kilometr rozliczamy według cennika.</p></details>
                <details><summary>Czy mogę odwołać rezerwację?</summary><p>Tak, bezpłatnie do 48 godzin przed odbiorem.</p></details>
            </div>
        </section>

    </main>
<?php render_stopka(); ?>
