<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('administrator');  

$stat = [
    'Klienci'    => (int) db_wartosc('SELECT COUNT(*) FROM uzytkownicy WHERE rola = ?', ['klient']),
    'Pracownicy' => (int) db_wartosc('SELECT COUNT(*) FROM uzytkownicy WHERE rola = ?', ['pracownik']),
    'Usługi'     => (int) db_wartosc('SELECT COUNT(*) FROM uslugi'),
    'Rezerwacje' => (int) db_wartosc('SELECT COUNT(*) FROM rezerwacje'),
];

render_naglowek('Panel administratora – RentLoudCars', 'panel');
?>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <h1>Panel administratora</h1>
                <p class="lead">Witaj, <?= e($u['imie']) ?>. Tu będziesz zarządzać użytkownikami, usługami i rezerwacjami.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap panel-grid">
<?php foreach ($stat as $nazwa => $liczba): ?>
                <article class="stat">
                    <strong><?= $liczba ?></strong>
                    <span><?= e($nazwa) ?></span>
                </article>
<?php endforeach; ?>
            </div>
        </section>
    </main>
<?php render_stopka(); ?>
