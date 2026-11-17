<?php


const NA_STRONE = 10;


function wymagaj_admina(): array
{
    return wymagaj_roli('administrator');
}


function loguj_admina(string $akcja, string $opis): void
{
    db()->prepare('INSERT INTO log_admina (id_admina, akcja, opis, utworzony) VALUES (?, ?, ?, ?)')
        ->execute([$_SESSION['uid'] ?? null, $akcja, mb_substr($opis, 0, 500), date('Y-m-d H:i:s')]);
}


function ten_plik(): string
{
    return basename((string) parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH));
}


function sprawdz_csrf_lub_wroc(string $powrot): void
{
    if (!csrf_sprawdz()) {
        flash('err', 'Formularz wygasł. Spróbuj jeszcze raz.');
        redirect($powrot);
    }
}

function admin_start(string $tytul, string $aktywna, string $naglowek, string $opis = ''): void
{
    $menu = [
        'panel'        => ['panel-admina.php', 'Pulpit'],
        'rezerwacje'   => ['admin-rezerwacje.php', 'Rezerwacje'],
        'kalendarz'    => ['admin-kalendarz.php', 'Kalendarz floty'],
        'uslugi'       => ['admin-uslugi.php', 'Auta i usługi'],
        'kategorie'    => ['admin-kategorie.php', 'Kategorie'],
        'pracownicy'   => ['admin-pracownicy.php', 'Pracownicy'],
        'uzytkownicy'  => ['admin-uzytkownicy.php', 'Użytkownicy'],
        'dziennik'     => ['admin-log.php', 'Dziennik'],
    ];
    render_naglowek($tytul . ' – panel administratora', 'panel');
    ?>
    <main id="main">
        <section class="page-hero admin-hero">
            <div class="wrap">
                <h1><?= e($naglowek) ?></h1>
<?php if ($opis !== ''): ?>
                <p class="lead"><?= e($opis) ?></p>
<?php endif; ?>
            </div>
        </section>
        <nav class="admin-nav" aria-label="Nawigacja panelu administratora">
            <div class="wrap">
                <ul>
<?php foreach ($menu as $klucz => [$url, $nazwa]): ?>
                    <li><a href="<?= $url ?>"<?= $klucz === $aktywna ? ' aria-current="page"' : '' ?>><?= $nazwa ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
        </nav>
        <section class="block admin-tresc">
            <div class="wrap">
<?php
}

function admin_koniec(): void
{
    ?>
            </div>
        </section>
    </main>
<?php
    render_stopka();
}


function paginacja(int $razem, int $naStrone = NA_STRONE): array
{
    $stron = max(1, (int) ceil($razem / $naStrone));
    $strona = min(max(1, id_z_tekstu(pobierz('strona')) ?: 1), $stron);
    return ['strona' => $strona, 'stron' => $stron, 'offset' => ($strona - 1) * $naStrone, 'limit' => $naStrone, 'razem' => $razem];
}


function url_z_parametrami(array $zmiany): string
{
    $par = [];
    foreach ($_GET as $k => $v) {
        if (is_string($v) && $v !== '' && !in_array($k, ['edytuj', 'usun', 'anuluj', 'usun_wyjatek'], true)) {
            $par[$k] = $v;
        }
    }
    foreach ($zmiany as $k => $v) {
        if ($v === null || $v === '') {
            unset($par[$k]);
        } else {
            $par[$k] = $v;
        }
    }
    return ten_plik() . ($par ? '?' . http_build_query($par) : '');
}

function render_paginacje(array $p): void
{
    if ($p['stron'] <= 1) {
        return;
    }
    echo '<nav class="paginacja" aria-label="Strony wyników"><ul>';
    if ($p['strona'] > 1) {
        echo '<li><a href="' . e(url_z_parametrami(['strona' => $p['strona'] - 1])) . '">‹ Poprzednia</a></li>';
    }
    $od = max(1, $p['strona'] - 2);
    $do = min($p['stron'], $p['strona'] + 2);
    for ($i = $od; $i <= $do; $i++) {
        echo '<li><a href="' . e(url_z_parametrami(['strona' => $i])) . '"'
           . ($i === $p['strona'] ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
    }
    if ($p['strona'] < $p['stron']) {
        echo '<li><a href="' . e(url_z_parametrami(['strona' => $p['strona'] + 1])) . '">Następna ›</a></li>';
    }
    echo '</ul></nav>';
}


function strona_potwierdzenia(string $tytul, string $pytanie, array $ukryte, string $powrot, string $przycisk = 'Tak, usuń'): never
{
    render_naglowek($tytul . ' – RentLoudCars', 'panel');
    ?>
    <main id="main" class="block">
        <div class="wrap auth-card">
            <h1><?= e($tytul) ?></h1>
            <p><?= e($pytanie) ?></p>
            <form method="post" action="<?= e(ten_plik()) ?>" class="akcje-potwierdzenia">
                <?= csrf_pole() ?>
<?php foreach ($ukryte as $nazwa => $wartosc): ?>
                <input type="hidden" name="<?= e($nazwa) ?>" value="<?= e((string) $wartosc) ?>">
<?php endforeach; ?>
                <button type="submit" class="btn"><?= e($przycisk) ?></button>
                <a class="btn alt" href="<?= e($powrot) ?>">Anuluj</a>
            </form>
        </div>
    </main>
<?php
    render_stopka();
    exit;
}


function przycisk_post(string $akcja, array $pola, string $etykieta, string $klasa = 'btn small alt'): string
{
    $html = '<form method="post" action="' . e(ten_plik()) . '" class="inline">' . csrf_pole()
          . '<input type="hidden" name="akcja" value="' . e($akcja) . '">';
    foreach ($pola as $n => $w) {
        $html .= '<input type="hidden" name="' . e($n) . '" value="' . e((string) $w) . '">';
    }
    return $html . '<button type="submit" class="' . e($klasa) . '">' . e($etykieta) . '</button></form>';
}



function adres_powrotu(string $plik, string $zapytanie, array $dozwolone = ['q', 'status', 'pracownik', 'od', 'do', 'strona', 'id']): string
{
    parse_str($zapytanie, $par);
    $czyste = [];
    foreach ($dozwolone as $k) {
        if (isset($par[$k]) && is_string($par[$k]) && $par[$k] !== '' && mb_strlen($par[$k]) <= 60) {
            $czyste[$k] = $par[$k];
        }
    }
    return $plik . ($czyste ? '?' . http_build_query($czyste) : '');
}



function przyciski_zmiany_statusu(array $r, string $wroc = ''): string
{
    $html = '';
    foreach (PRZEJSCIA_STATUSOW[$r['status']] ?? [] as $nowy) {
        if ($nowy === 'zakonczone' && poczatek_wizyty($r) > time()) {
            continue;
        }
        if ($nowy === 'anulowane') {
            $html .= '<a class="btn small alt" href="' . e(ten_plik() . '?anuluj=' . (int) $r['id'] . ($wroc !== '' ? '&wroc=' . rawurlencode($wroc) : '')) . '">Anuluj</a> ';
        } else {
            $html .= przycisk_post('status', ['id' => $r['id'], 'nowy' => $nowy, 'wroc' => $wroc], $nowy === 'potwierdzone' ? 'Potwierdź' : 'Zakończ', 'btn small') . ' ';
        }
    }
    return $html;
}

const MIESIACE_SKROT = [1 => 'sty', 2 => 'lut', 3 => 'mar', 4 => 'kwi', 5 => 'maj', 6 => 'cze', 7 => 'lip', 8 => 'sie', 9 => 'wrz', 10 => 'paź', 11 => 'lis', 12 => 'gru'];



function wykres_slupkowy(array $dane, string $opis, string $jednostka = ''): string
{
    if (!$dane) {
        return '<p class="muted">Brak danych.</p>';
    }
    $maks = max(1, max($dane));
    $wys = 30;
    $svg = '<svg class="wykres" role="img" aria-label="' . e($opis) . '" viewBox="0 0 520 ' . (count($dane) * $wys + 4) . '" xmlns="http://www.w3.org/2000/svg">';
    $i = 0;
    foreach ($dane as $etykieta => $wartosc) {
        $y = $i * $wys + 2;
        $skrot = mb_strlen((string) $etykieta) > 20 ? mb_substr((string) $etykieta, 0, 19) . '…' : (string) $etykieta;
        $szer = (int) round(($wartosc / $maks) * 250);
        $svg .= '<text x="0" y="' . ($y + 19) . '" class="wykres-etykieta">' . e($skrot) . '</text>'
              . '<rect x="150" y="' . $y . '" width="' . max($wartosc > 0 ? 3 : 0, $szer) . '" height="24" class="wykres-slupek"><title>' . e($etykieta . ': ' . $wartosc . $jednostka) . '</title></rect>'
              . '<text x="' . (150 + max(3, $szer) + 8) . '" y="' . ($y + 19) . '" class="wykres-wartosc">' . e((string) $wartosc . $jednostka) . '</text>';
        $i++;
    }
    return $svg . '</svg>';
}
