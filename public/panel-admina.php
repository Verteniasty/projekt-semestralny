<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('administrator');   // tylko administrator

// Proste statystyki z bazy (zapytania bez danych od użytkownika, więc bez parametrów).
$stat = [
    'Klienci'    => (int) db()->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'klient'")->fetchColumn(),
    'Pracownicy' => (int) db()->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'pracownik'")->fetchColumn(),
    'Usługi'     => (int) db()->query('SELECT COUNT(*) FROM uslugi')->fetchColumn(),
    'Rezerwacje' => (int) db()->query('SELECT COUNT(*) FROM rezerwacje')->fetchColumn(),
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
