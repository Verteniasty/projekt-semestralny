<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();


$m = pobierz('miesiac');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) || $m < '2020-01' || $m > '2100-12') {
    $m = date('Y-m');
}
$pierwszy = $m . '-01';
$dniMiesiaca = (int) date('t', strtotime($pierwszy));
$ostatni = $m . '-' . sprintf('%02d', $dniMiesiaca);
$poprzedni = date('Y-m', strtotime($pierwszy . ' -1 month'));
$nastepny = date('Y-m', strtotime($pierwszy . ' +1 month'));
$dzis = date('Y-m-d');
$kategoria = id_z_tekstu(pobierz('kategoria'));

$kategorie = db()->query("SELECT DISTINCT k.id, k.nazwa FROM kategorie_uslug k JOIN uslugi us ON us.id_kategorii = k.id WHERE us.typ = 'doby' ORDER BY k.id")->fetchAll(PDO::FETCH_KEY_PAIR);
if (!isset($kategorie[$kategoria])) {
    $kategoria = 0;
}

$sql = "SELECT id, nazwa FROM uslugi WHERE typ = 'doby' AND aktywne = 1" . ($kategoria ? ' AND id_kategorii = ?' : '') . ' ORDER BY nazwa';
$st = db()->prepare($sql);
$st->execute($kategoria ? [$kategoria] : []);
$auta = $st->fetchAll(PDO::FETCH_KEY_PAIR);


$st = db()->prepare(
    "SELECT r.id, r.id_uslugi, r.data_rezerwacji, r.data_zakonczenia, r.godzina_rozpoczecia, r.status, k.imie, k.nazwisko
     FROM rezerwacje r JOIN uslugi us ON us.id = r.id_uslugi JOIN uzytkownicy k ON k.id = r.id_uzytkownika
     WHERE us.typ = 'doby' AND r.status <> 'anulowane' AND r.data_zakonczenia >= ? AND r.data_rezerwacji <= ?"
);
$st->execute([$pierwszy, $ostatni]);
$poAutach = [];
foreach ($st->fetchAll() as $r) {
    $poAutach[(int) $r['id_uslugi']][] = $r;
}


$lewa = 210; $kol = 30; $wiersz = 34; $gora = 44;
$szer = $lewa + $dniMiesiaca * $kol;
$wys = $gora + max(1, count($auta)) * $wiersz + 4;
$nazwyDni = ['N', 'Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So'];
$svg = '<svg class="kalendarz" role="img" aria-label="Kalendarz floty na ' . e($m) . '" viewBox="0 0 ' . $szer . ' ' . $wys . '" width="' . $szer . '" height="' . $wys . '" xmlns="http://www.w3.org/2000/svg">';
for ($d = 1; $d <= $dniMiesiaca; $d++) {
    $data = sprintf('%s-%02d', $m, $d);
    $x = $lewa + ($d - 1) * $kol;
    $dow = (int) date('w', strtotime($data));
    if ($dow === 0 || $dow === 6) {
        $svg .= '<rect x="' . $x . '" y="' . $gora . '" width="' . $kol . '" height="' . ($wys - $gora) . '" class="kal-weekend"/>';
    }
    if ($data === $dzis) {
        $svg .= '<rect x="' . $x . '" y="0" width="' . $kol . '" height="' . $wys . '" class="kal-dzis"/>';
    }
    $svg .= '<text x="' . ($x + $kol / 2) . '" y="18" text-anchor="middle" class="kal-dzien">' . $d . '</text>'
          . '<text x="' . ($x + $kol / 2) . '" y="34" text-anchor="middle" class="kal-dow">' . $nazwyDni[$dow] . '</text>';
}
$i = 0;
foreach ($auta as $idAuta => $nazwa) {
    $y = $gora + $i * $wiersz;
    $svg .= '<line x1="0" y1="' . $y . '" x2="' . $szer . '" y2="' . $y . '" class="kal-linia"/>'
          . '<text x="8" y="' . ($y + 22) . '" class="kal-auto">' . e(mb_strimwidth($nazwa, 0, 28, '…')) . '<title>' . e($nazwa) . '</title></text>';
    foreach ($poAutach[$idAuta] ?? [] as $r) {

        $od = max(0, (int) round((strtotime($r['data_rezerwacji'] . ' UTC') - strtotime($pierwszy . ' UTC')) / 86400));
        $do = min($dniMiesiaca, (int) round((strtotime($r['data_zakonczenia'] . ' UTC') - strtotime($pierwszy . ' UTC')) / 86400) + 1);
        $x1 = $lewa + $od * $kol + ($r['data_rezerwacji'] >= $pierwszy ? $kol / 2 : 0);
        $x2 = $lewa + $do * $kol - ($r['data_zakonczenia'] <= $ostatni ? $kol / 2 : 0);
        $opis = '#' . $r['id'] . ' ' . $r['imie'] . ' ' . $r['nazwisko'] . ': ' . $r['data_rezerwacji'] . ' ' . substr($r['godzina_rozpoczecia'], 0, 5) . ' → ' . $r['data_zakonczenia'] . ' (' . etykieta_statusu($r['status']) . ')';
        $svg .= '<a href="admin-rezerwacja.php?id=' . (int) $r['id'] . '"><rect x="' . $x1 . '" y="' . ($y + 5) . '" width="' . max(8, $x2 - $x1) . '" height="' . ($wiersz - 10) . '" rx="4" class="kal-pasek kal-' . e($r['status']) . '"><title>' . e($opis) . '</title></rect></a>';
    }
    $i++;
}
$svg .= '<line x1="0" y1="' . ($gora + $i * $wiersz) . '" x2="' . $szer . '" y2="' . ($gora + $i * $wiersz) . '" class="kal-linia"/></svg>';

$miesiaceNazwy = [1 => 'styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień'];
$tytul = ucfirst($miesiaceNazwy[(int) date('n', strtotime($pierwszy))]) . ' ' . date('Y', strtotime($pierwszy));
$url = fn(string $mies) => 'admin-kalendarz.php?miesiac=' . $mies . ($kategoria ? '&kategoria=' . $kategoria : '');

admin_start('Kalendarz floty', 'kalendarz', 'Kalendarz floty', 'Które auto jest kiedy wynajęte. Kliknij pasek, aby otworzyć rezerwację.');
?>
                <div class="kal-pasek-nawig">
                    <a class="btn small alt" href="<?= e($url($poprzedni)) ?>">← Poprzedni</a>
                    <strong class="kal-tytul"><?= e($tytul) ?></strong>
                    <a class="btn small alt" href="<?= e($url($nastepny)) ?>">Następny →</a>
                    <a class="btn small" href="<?= e($url(date('Y-m'))) ?>">Dziś</a>
                    <form class="inline" method="get" action="admin-kalendarz.php">
                        <input type="hidden" name="miesiac" value="<?= e($m) ?>">
                        <label class="sr-only" for="f-kat">Kategoria</label>
                        <select id="f-kat" name="kategoria">
                            <option value="0">Wszystkie kategorie</option>
<?php foreach ($kategorie as $kid => $knazwa): ?>
                            <option value="<?= (int) $kid ?>"<?= $kid === $kategoria ? ' selected' : '' ?>><?= e($knazwa) ?></option>
<?php endforeach; ?>
                        </select>
                        <button class="btn small" type="submit">Filtruj</button>
                    </form>
                </div>
                <ul class="kal-legenda">
                    <li><span class="kal-probka kal-oczekujace"></span> oczekujące</li>
                    <li><span class="kal-probka kal-potwierdzone"></span> potwierdzone</li>
                    <li><span class="kal-probka kal-zakonczone"></span> zakończone</li>
                </ul>
<?php if ($auta): ?>
                <div class="kal-wrap"><?= $svg ?></div>
<?php else: ?>
                <p class="muted">Brak aktywnych aut w tej kategorii.</p>
<?php endif; ?>
<?php admin_koniec(); ?>
