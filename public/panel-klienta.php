<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('klient');   

render_naglowek('Panel klienta – RentLoudCars', 'panel');
?>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <h1>Moje konto</h1>
                <p class="lead">Witaj, <?= e($u['imie']) ?>. Tu znajdziesz swoje dane i rezerwacje.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap panel-grid">
                <article class="stat">
                    <h2>Twoje dane</h2>
                    <p><?= e($u['imie'] . ' ' . $u['nazwisko']) ?><br><?= e($u['email']) ?><br><?= e($u['numer_telefonu']) ?></p>
                    <a class="btn small" href="profil.php">Edytuj profil</a>
                </article>
                <article class="stat">
                    <h2>Moje rezerwacje</h2>
                    <p>Lista przyszłych i zakończonych rezerwacji pojawi się tutaj po wdrożeniu systemu rezerwacji.</p>
                    <a class="btn small" href="Garaz.php">Przeglądaj garaż</a>
                </article>
            </div>
        </section>
    </main>
<?php render_stopka(); ?>
