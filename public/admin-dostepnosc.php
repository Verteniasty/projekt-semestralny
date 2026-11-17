<?php
require __DIR__ . '/../src/bootstrap.php';
$u = wymagaj_admina();


$idPracownika = id_z_tekstu(metoda_post() ? post('id') : pobierz('id'));
$st = db()->prepare('SELECT p.id, u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.id_uzytkownika WHERE p.id = ?');
$st->execute([$idPracownika]);
$pracownik = $st->fetch();
if (!$pracownik) {
    flash('err', 'Nie znaleziono takiego pracownika.');
    redirect('admin-pracownicy.php');
}
$powrot = 'admin-dostepnosc.php?id=' . $idPracownika;

const DNI = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];



function przedzialy_z_formularza(string $prefiks): array
{
    $wynik = [];
    foreach ([1, 2] as $n) {
        $od = post("{$prefiks}_od{$n}");
        $do = post("{$prefiks}_do{$n}");
        if ($od === '' && $do === '') {
            continue;
        }
        if (!godzina_poprawna($od) || !godzina_poprawna($do)) {
            return [[], 'Podaj godziny w formacie GG:MM, oba pola przedziału.'];
        }
        if (minuty($od) >= minuty($do)) {
            return [[], 'Początek przedziału musi być wcześniejszy niż koniec.'];
        }
        $wynik[] = [minuty($od), minuty($do)];
    }
    if (count($wynik) === 2 && $wynik[1][0] < $wynik[0][1]) {
        return [[], 'Drugi przedział (po przerwie) musi zaczynać się po końcu pierwszego.'];
    }
    return [$wynik, null];
}

$bledyTydzien = [];
$bledyWyjatek = '';
$wyjatekDane = ['data' => '', 'rodzaj' => 'wolne'];

if (metoda_post()) {
    sprawdz_csrf_lub_wroc($powrot);
    $akcja = post('akcja');

    if ($akcja === 'tydzien') {
        $nowe = [];
        foreach (DNI as $nr => $nazwa) {
            [$przedzialy, $blad] = przedzialy_z_formularza('d' . $nr);
            if ($blad) {
                $bledyTydzien[$nr] = $blad;
            }
            $nowe[$nr] = $przedzialy;
        }
        if (!$bledyTydzien) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM godziny_pracy_pracownikow WHERE id_pracownika = ? AND data IS NULL')->execute([$idPracownika]);
                $ins = $pdo->prepare('INSERT INTO godziny_pracy_pracownikow (id_pracownika, dzien_tygodnia, data, godzina_rozpoczecia, godzina_zakonczenia) VALUES (?, ?, NULL, ?, ?)');
                foreach ($nowe as $nr => $przedzialy) {
                    foreach ($przedzialy as [$od, $do]) {
                        $ins->execute([$idPracownika, $nr, godz($od) . ':00', godz($do) . ':00']);
                    }
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            loguj_admina('dostepnosc_tydzien', "Zmieniono godziny pracy pracownika #{$idPracownika}: {$pracownik['imie']} {$pracownik['nazwisko']}");
            flash('ok', 'Godziny pracy zostały zapisane. Istniejące rezerwacje nie zostały zmienione.');
            redirect($powrot);
        }
    } elseif ($akcja === 'wyjatek') {
        $wyjatekDane = ['data' => post('data'), 'rodzaj' => post('rodzaj') === 'godziny' ? 'godziny' : 'wolne'];
        [$przedzialy, $blad] = $wyjatekDane['rodzaj'] === 'godziny' ? przedzialy_z_formularza('w') : [[], null];
        if (!data_poprawna($wyjatekDane['data'])) {
            $bledyWyjatek = 'Podaj poprawną datę.';
        } elseif ($wyjatekDane['data'] < date('Y-m-d')) {
            $bledyWyjatek = 'Wyjątek można ustawić tylko na dzisiaj lub przyszłość.';
        } elseif ($blad) {
            $bledyWyjatek = $blad;
        } elseif ($wyjatekDane['rodzaj'] === 'godziny' && !$przedzialy) {
            $bledyWyjatek = 'Podaj godziny pracy albo wybierz „dzień wolny”.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM godziny_pracy_pracownikow WHERE id_pracownika = ? AND data = ?')->execute([$idPracownika, $wyjatekDane['data']]);
                $ins = $pdo->prepare('INSERT INTO godziny_pracy_pracownikow (id_pracownika, dzien_tygodnia, data, godzina_rozpoczecia, godzina_zakonczenia) VALUES (?, NULL, ?, ?, ?)');
                if ($wyjatekDane['rodzaj'] === 'wolne') {
                    $ins->execute([$idPracownika, $wyjatekDane['data'], '00:00:00', '00:00:00']);
                } else {
                    foreach ($przedzialy as [$od, $do]) {
                        $ins->execute([$idPracownika, $wyjatekDane['data'], godz($od) . ':00', godz($do) . ':00']);
                    }
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            loguj_admina('dostepnosc_wyjatek', "Ustawiono wyjątek {$wyjatekDane['data']} ({$wyjatekDane['rodzaj']}) dla pracownika #{$idPracownika}");
            flash('ok', 'Wyjątek został zapisany.');
            redirect($powrot);
        }
    } elseif ($akcja === 'usun_wyjatek') {
        $data = post('data');
        if (data_poprawna($data)) {
            db()->prepare('DELETE FROM godziny_pracy_pracownikow WHERE id_pracownika = ? AND data = ?')->execute([$idPracownika, $data]);
            loguj_admina('dostepnosc_wyjatek_usuniecie', "Usunięto wyjątek {$data} pracownika #{$idPracownika}");
            flash('ok', 'Wyjątek został usunięty.');
        }
        redirect($powrot);
    } else {
        flash('err', 'Nieznana operacja.');
        redirect($powrot);
    }
}

$usunWyjatek = pobierz('usun_wyjatek');
if (!metoda_post() && data_poprawna($usunWyjatek)) {
    strona_potwierdzenia('Usuwanie wyjątku', "Czy na pewno usunąć wyjątek z dnia {$usunWyjatek}? Obowiązywać będą znowu zwykłe godziny tygodniowe.", ['akcja' => 'usun_wyjatek', 'id' => $idPracownika, 'data' => $usunWyjatek], $powrot);
}


$tydzien = array_fill_keys(array_keys(DNI), []);
if (metoda_post() && $bledyTydzien) {
    foreach (DNI as $nr => $_) {
        foreach ([1, 2] as $n) {
            $tydzien[$nr][] = [post("d{$nr}_od{$n}"), post("d{$nr}_do{$n}")];
        }
    }
} else {
    $st = db()->prepare('SELECT dzien_tygodnia, godzina_rozpoczecia, godzina_zakonczenia FROM godziny_pracy_pracownikow WHERE id_pracownika = ? AND data IS NULL ORDER BY dzien_tygodnia, godzina_rozpoczecia');
    $st->execute([$idPracownika]);
    foreach ($st->fetchAll() as $w) {
        $tydzien[(int) $w['dzien_tygodnia']][] = [substr($w['godzina_rozpoczecia'], 0, 5), substr($w['godzina_zakonczenia'], 0, 5)];
    }
}

$st = db()->prepare('SELECT data, godzina_rozpoczecia, godzina_zakonczenia FROM godziny_pracy_pracownikow WHERE id_pracownika = ? AND data IS NOT NULL AND data >= ? ORDER BY data, godzina_rozpoczecia');
$st->execute([$idPracownika, date('Y-m-d')]);
$wyjatki = [];
foreach ($st->fetchAll() as $w) {
    $wyjatki[$w['data']][] = [substr($w['godzina_rozpoczecia'], 0, 5), substr($w['godzina_zakonczenia'], 0, 5)];
}

admin_start('Dostępność', 'pracownicy', 'Dostępność: ' . $pracownik['imie'] . ' ' . $pracownik['nazwisko'], 'Godziny pracy decydują, jakie terminy zobaczą klienci.');
?>
                <p><a href="admin-pracownicy.php">‹ Wróć do pracowników</a></p>
                <div class="dwie-kolumny">
                    <div>
                        <h2>Zwykłe godziny tygodniowe</h2>
                        <p class="hint">Drugi przedział służy do przerwy w ciągu dnia (np. 08:00–12:00 i 12:30–16:00). Puste pola oznaczają dzień wolny.</p>
                        <form method="post" action="admin-dostepnosc.php" novalidate class="karta-formularz">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akcja" value="tydzien">
                            <input type="hidden" name="id" value="<?= (int) $idPracownika ?>">
<?php foreach (DNI as $nr => $nazwaDnia): ?>
                            <fieldset class="dzien">
                                <legend><?= e($nazwaDnia) ?></legend>
<?php for ($n = 1; $n <= 2; $n++): $w = $tydzien[$nr][$n - 1] ?? ['', '']; ?>
                                <label class="sr-only" for="d<?= $nr ?>_od<?= $n ?>">Początek przedziału <?= $n ?>, <?= e($nazwaDnia) ?></label>
                                <input type="time" id="d<?= $nr ?>_od<?= $n ?>" name="d<?= $nr ?>_od<?= $n ?>" value="<?= e($w[0]) ?>">
                                <span aria-hidden="true">–</span>
                                <label class="sr-only" for="d<?= $nr ?>_do<?= $n ?>">Koniec przedziału <?= $n ?>, <?= e($nazwaDnia) ?></label>
                                <input type="time" id="d<?= $nr ?>_do<?= $n ?>" name="d<?= $nr ?>_do<?= $n ?>" value="<?= e($w[1]) ?>">
<?php endfor; ?>
<?php if (!empty($bledyTydzien[$nr])): ?>
                                <p class="err"><?= e($bledyTydzien[$nr]) ?></p>
<?php endif; ?>
                            </fieldset>
<?php endforeach; ?>
                            <button type="submit" class="btn">Zapisz godziny</button>
                        </form>
                    </div>
                    <div>
                        <h2>Dni wolne i wyjątki</h2>
                        <form method="post" action="admin-dostepnosc.php" novalidate class="karta-formularz">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akcja" value="wyjatek">
                            <input type="hidden" name="id" value="<?= (int) $idPracownika ?>">
                            <?php pole('data', 'Data', 'date', $wyjatekDane['data'], $bledyWyjatek ?: null, 'required min="' . e(date('Y-m-d')) . '"'); ?>
                            <?php pole_lista('rodzaj', 'Rodzaj', ['wolne' => 'Dzień wolny (urlop)', 'godziny' => 'Inne godziny pracy tego dnia'], $wyjatekDane['rodzaj']); ?>
                            <p class="hint">Pola godzin wypełnij tylko dla „innych godzin pracy”.</p>
                            <div class="dzien">
<?php for ($n = 1; $n <= 2; $n++): ?>
                                <label class="sr-only" for="w_od<?= $n ?>">Początek przedziału <?= $n ?></label>
                                <input type="time" id="w_od<?= $n ?>" name="w_od<?= $n ?>" value="<?= e(post("w_od{$n}")) ?>">
                                <span aria-hidden="true">–</span>
                                <label class="sr-only" for="w_do<?= $n ?>">Koniec przedziału <?= $n ?></label>
                                <input type="time" id="w_do<?= $n ?>" name="w_do<?= $n ?>" value="<?= e(post("w_do{$n}")) ?>">
<?php endfor; ?>
                            </div>
                            <button type="submit" class="btn">Zapisz wyjątek</button>
                        </form>
                        <h3 class="odstep-maly">Zaplanowane wyjątki</h3>
<?php if ($wyjatki): ?>
                        <div class="tabela-wrap">
                            <table class="tabela">
                                <caption class="sr-only">Wyjątki od zwykłych godzin</caption>
                                <thead><tr><th scope="col">Data</th><th scope="col">Godziny</th><th scope="col">Akcje</th></tr></thead>
                                <tbody>
<?php foreach ($wyjatki as $data => $przedzialy):
    $wolne = count($przedzialy) === 1 && $przedzialy[0][0] === $przedzialy[0][1]; ?>
                                    <tr>
                                        <td><?= e($data) ?></td>
                                        <td><?= $wolne ? 'Dzień wolny' : e(implode(', ', array_map(fn($p) => $p[0] . '–' . $p[1], $przedzialy))) ?></td>
                                        <td class="akcje"><a class="btn small alt" href="admin-dostepnosc.php?id=<?= (int) $idPracownika ?>&amp;usun_wyjatek=<?= e($data) ?>">Usuń</a></td>
                                    </tr>
<?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
<?php else: ?>
                        <p class="muted">Brak zaplanowanych wyjątków.</p>
<?php endif; ?>
                    </div>
                </div>
<?php admin_koniec(); ?>
