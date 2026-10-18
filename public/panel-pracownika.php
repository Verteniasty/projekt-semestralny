<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('pracownik');   // tylko pracownik

// Dane stanowiska i przypisane usługi (relacja N:M przez uslugi_pracownikow).
$st = db()->prepare('SELECT id, opis FROM pracownicy WHERE id_uzytkownika = ? AND aktywne = 1');
$st->execute([$u['id']]);
$pracownik = $st->fetch();

$uslugi = [];
if ($pracownik) {
    $st = db()->prepare(
        'SELECT us.nazwa FROM uslugi_pracownikow up
         JOIN uslugi us ON us.id = up.id_uslugi
         WHERE up.id_pracownika = ? ORDER BY us.nazwa'
    );
    $st->execute([$pracownik['id']]);
    $uslugi = $st->fetchAll(PDO::FETCH_COLUMN);
}

render_naglowek('Panel pracownika – RentLoudCars', 'panel');
?>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <h1>Panel pracownika</h1>
                <p class="lead">Witaj, <?= e($u['imie']) ?>. Tu zobaczysz swoje wizyty i będziesz zmieniać ich statusy.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap panel-grid">
                <article class="stat">
                    <h2>Twoje stanowisko</h2>
                    <p><?= $pracownik ? e($pracownik['opis']) : 'Brak aktywnego profilu pracownika. Skontaktuj się z administratorem.' ?></p>
                    <a class="btn small" href="profil.php">Edytuj profil</a>
                </article>
                <article class="stat">
                    <h2>Obsługiwane auta</h2>
<?php if ($uslugi): ?>
                    <ul>
<?php foreach ($uslugi as $nazwa): ?>
                        <li><?= e($nazwa) ?></li>
<?php endforeach; ?>
                    </ul>
<?php else: ?>
                    <p>Nie przypisano jeszcze żadnych usług.</p>
<?php endif; ?>
                </article>
                <article class="stat">
                    <h2>Wizyty</h2>
                    <p>Wizyty na dziś, przyszłe i historia pojawią się tutaj po wdrożeniu systemu rezerwacji.</p>
                </article>
            </div>
        </section>
    </main>
<?php render_stopka(); ?>
