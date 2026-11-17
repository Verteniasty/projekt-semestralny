<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_roli('klient');

$dzis = date('Y-m-d');
$nadchodzace = array_slice(array_values(array_filter(
    lista_rezerwacji(['klient' => (int) $u['id'], 'od' => $dzis], 20, 0, true),
    fn($r) => in_array($r['status'], ['oczekujace', 'potwierdzone'], true) && poczatek_wizyty($r) > time()
)), 0, 3);

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
                    <h2>Najbliższe rezerwacje</h2>
<?php if ($nadchodzace): ?>
                    <ul>
<?php foreach ($nadchodzace as $r): ?>
                        <li><?= e(termin_txt($r)) ?>, <?= e($r['usluga']) ?> (<?= e(mb_strtolower(etykieta_statusu($r['status']))) ?>)</li>
<?php endforeach; ?>
                    </ul>
<?php else: ?>
                    <p>Nie masz zaplanowanych rezerwacji.</p>
<?php endif; ?>
                    <a class="btn small" href="rezerwacja.php">Zarezerwuj termin</a>
                    <a class="btn small alt" href="moje-rezerwacje.php">Wszystkie rezerwacje</a>
                </article>
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
