<?php

function render_naglowek(string $tytul, string $aktywna = '', array $css = [], string $opis = ''): void
{
    $u = aktualny_uzytkownik();
    $linki = [
        'glowna'  => ['Strona-glowna.php', 'Strona główna'],
        'garaz'   => ['Garaz.php', 'Garaż'],
        'onas'    => ['O-nas.php', 'O nas'],
        'kontakt' => ['Kontakt.php', 'Kontakt'],
    ];
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tytul) ?></title>
<?php if ($opis !== ''): ?>
    <meta name="description" content="<?= e($opis) ?>">
<?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Barlow:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
<?php foreach ($css as $plik): ?>
    <link rel="stylesheet" href="<?= e($plik) ?>">
<?php endforeach; ?>
</head>
<body>
    <a class="skip" href="#main">Przejdź do treści</a>

    <header class="site-header">
        <div class="wrap bar">
            <a class="logo" href="Strona-glowna.php">Rent<span>Loud</span>Cars</a>
            <button class="menu-btn" aria-expanded="false" aria-controls="menu">Menu</button>
            <nav id="menu" aria-label="Główna nawigacja">
                <ul>
<?php foreach ($linki as $klucz => [$url, $nazwa]): ?>
                    <li><a href="<?= $url ?>"<?= $klucz === $aktywna ? ' aria-current="page"' : '' ?>><?= $nazwa ?></a></li>
<?php endforeach; ?>
<?php if ($u): ?>
                    <li><a href="<?= e(panel_roli($u['rola'])) ?>"<?= $aktywna === 'panel' ? ' aria-current="page"' : '' ?>>Moje konto</a></li>
                    <li><a href="profil.php"<?= $aktywna === 'profil' ? ' aria-current="page"' : '' ?>>Profil</a></li>
                    <li>
                        <form action="wylogowanie.php" method="post">
                            <?= csrf_pole() ?>
                            <button type="submit" class="btn-link">Wyloguj (<?= e($u['imie']) ?>)</button>
                        </form>
                    </li>
<?php else: ?>
                    <li><a href="logowanie.php"<?= $aktywna === 'logowanie' ? ' aria-current="page"' : '' ?>>Zaloguj się</a></li>
                    <li><a class="btn small" href="rejestracja.php">Rejestracja</a></li>
<?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
<?php foreach (pobierz_flash() as $f): ?>
    <div class="wrap"><p class="komunikat komunikat-<?= e($f['typ']) ?>" role="status"><?= e($f['tekst']) ?></p></div>
<?php endforeach; ?>

<?php
}

function render_stopka(): void
{
    ?>

    <footer class="site-footer">
        <div class="wrap foot">
            <p><strong>RentLoudCars</strong><br>Wypożyczalnia aut z charakterem.</p>
            <ul>
                <li><a href="Garaz.php">Garaż</a></li>
                <li><a href="O-nas.php">O nas</a></li>
                <li><a href="Kontakt.php">Kontakt</a></li>
            </ul>
            <p class="small">© 2026 RentLoudCars. Treści i zdjęcia są chronione, nie kopiuj strony.</p>
        </div>
    </footer>

    <script src="index.js"></script>
</body>
</html>
<?php
}


function pole(string $nazwa, string $etykieta, string $typ = 'text', string $wartosc = '', ?string $blad = null, string $atrybuty = ''): void
{
    $id = 'f-' . $nazwa;
    echo '<div class="row"><label for="' . $id . '">' . e($etykieta) . '</label>';
    echo '<input id="' . $id . '" name="' . e($nazwa) . '" type="' . e($typ) . '" value="' . e($wartosc) . '"'
       . ($blad ? ' class="invalid" aria-invalid="true" aria-describedby="' . $id . '-blad"' : '') . ' ' . $atrybuty . '>';
    if ($blad) {
        echo '<p class="err" id="' . $id . '-blad">' . e($blad) . '</p>';
    }
    echo '</div>';
}
