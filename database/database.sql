DROP DATABASE IF EXISTS Wypozyczalnia;

CREATE DATABASE IF NOT EXISTS Wypozyczalnia
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE Wypozyczalnia;

CREATE TABLE uzytkownicy (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    imie VARCHAR(40) NOT NULL,
    nazwisko VARCHAR(40) NOT NULL,
    email VARCHAR(60) UNIQUE NOT NULL,
    haslo VARCHAR(255) NOT NULL,
    numer_telefonu VARCHAR(30) NOT NULL,
    rola ENUM('klient', 'pracownik', 'administrator') NOT NULL DEFAULT 'klient',
    aktywne BOOLEAN NOT NULL DEFAULT TRUE,
    nieudane_proby TINYINT UNSIGNED NOT NULL DEFAULT 0,
    zablokowany_do DATETIME NULL,
    wersja_sesji INT NOT NULL DEFAULT 1,
    stworzony TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tokeny_hasla (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_uzytkownika INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    wazny_do DATETIME NOT NULL,
    utworzony DATETIME NOT NULL,

    FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id) ON DELETE CASCADE
);

CREATE TABLE kategorie_uslug (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nazwa VARCHAR(50) NOT NULL,
    opis VARCHAR(512)
);

CREATE TABLE uslugi (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_kategorii INT NOT NULL,
    nazwa VARCHAR(80) NOT NULL,
    slug VARCHAR(60) NULL UNIQUE,
    typ ENUM('doby', 'godziny') NOT NULL DEFAULT 'godziny',
    opis VARCHAR(512),
    czas_trwania INT NOT NULL,
    cena DECIMAL(16,2) NOT NULL,          -- dla typu "doby" cena za jedną dobę
    aktywne BOOLEAN NOT NULL DEFAULT TRUE,

    FOREIGN KEY (id_kategorii) REFERENCES kategorie_uslug(id) ON DELETE RESTRICT
);

CREATE TABLE pracownicy (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_uzytkownika INT NOT NULL UNIQUE,
    opis VARCHAR(255),
    aktywne BOOLEAN NOT NULL DEFAULT TRUE,

    FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id) ON DELETE CASCADE
);

CREATE TABLE uslugi_pracownikow (
    id_pracownika INT NOT NULL,
    id_uslugi INT NOT NULL,
    PRIMARY KEY (id_pracownika, id_uslugi),
    FOREIGN KEY (id_pracownika) REFERENCES pracownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (id_uslugi) REFERENCES uslugi(id) ON DELETE CASCADE
);

CREATE TABLE godziny_pracy_pracownikow (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_pracownika INT NOT NULL,
    dzien_tygodnia INT NULL,
    data DATE NULL,
    godzina_rozpoczecia TIME NOT NULL,
    godzina_zakonczenia TIME NOT NULL,

    FOREIGN KEY (id_pracownika) REFERENCES pracownicy(id) ON DELETE CASCADE
);

CREATE TABLE rezerwacje (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_uzytkownika INT NOT NULL,
    id_pracownika INT NOT NULL,
    id_uslugi INT NOT NULL,
    data_rezerwacji DATE NOT NULL,        -- dzień odbioru / początku wizyty
    godzina_rozpoczecia TIME NOT NULL,
    godzina_zakonczenia TIME NOT NULL,    -- wizyta: koniec; wynajem na doby: godzina zwrotu (= godzina odbioru)
    data_zakonczenia DATE NOT NULL,       -- wizyta: ten sam dzień; wynajem na doby: dzień zwrotu
    liczba_dob INT NULL,                  -- tylko wynajem na doby
    status ENUM("oczekujace", "potwierdzone", "anulowane", "zakonczone") NOT NULL DEFAULT 'oczekujace',
    komentarz VARCHAR(512) NULL,
    cena DECIMAL(16,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pracownika) REFERENCES pracownicy(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_uslugi) REFERENCES uslugi(id) ON DELETE RESTRICT
);

CREATE INDEX idx_rezerwacje_data ON rezerwacje(data_rezerwacji);
CREATE INDEX idx_rezerwacje_pracownik_dzien ON rezerwacje(id_pracownika, data_rezerwacji);

-- Historia zmian statusów rezerwacji (kto i kiedy zmienił status).
CREATE TABLE historia_statusow (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_rezerwacji INT NOT NULL,
    stary_status VARCHAR(20) NULL,
    nowy_status VARCHAR(20) NOT NULL,
    id_uzytkownika INT NULL,
    utworzony DATETIME NOT NULL,

    FOREIGN KEY (id_rezerwacji) REFERENCES rezerwacje(id) ON DELETE CASCADE,
    FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id) ON DELETE SET NULL
);

-- Log działań administratora (dodawanie, edycja, usuwanie, zmiany statusów).
CREATE TABLE log_admina (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_admina INT NULL,
    akcja VARCHAR(40) NOT NULL,
    opis VARCHAR(512) NOT NULL,
    utworzony DATETIME NOT NULL,

    FOREIGN KEY (id_admina) REFERENCES uzytkownicy(id) ON DELETE SET NULL
);

-- Hasło wszystkich kont testowych: test123!
-- W bazie jest hash bcrypt (password_hash w PHP), każde konto ma własny "salt".

INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, numer_telefonu, rola) VALUES
("Tomasz", "Kuzicki", "Tomasz.Kuzicki@gmail.com", "$2y$10$wfduwn8Crbvgfvc0aSUqcez7Zsl9dKWm5A6THnLoLA38fUUqhrzby", "+48203591342", "klient"),
("Anna", "Nowak", "Anna.Nowak@gmail.com", "$2y$10$owSanR8r35KITWdrmYCwV.pTUyl2xl1FU4AoTCCia7RQWa.JYudGG", "+48493582532", "pracownik"),
("Kamil", "Woźnica", "Kamil.Woznica@gmail.com", "$2y$10$HT1Fw.mh8sNPA9CE.3i/oOZyEgvthwRQ1mT0c3szsmBtD6VOM0cIK", "+48624593201", "pracownik"),
("Karolina", "Celin", "Karolina.Celin@gmail.com", "$2y$10$0dP7t8aIdT8uZHxW.n0QWeX2JnvPlboPja7WyWYKj/7/M.Xsib/q6", "+48734593428", "klient"),
("Adam", "Administrator", "admin@rentloudcars.pl", "$2y$10$VYFR0k5NKW2XsmgOYlb.HOYMcuKRx8hhjzvsjozYH09OwLTYulJxO", "+48500100200", "administrator");
INSERT INTO kategorie_uslug (nazwa, opis) VALUES
("Sedany i kombi", "Limuzyny z V8 i turbo: komfort na co dzień, a po wciśnięciu gazu prawdziwa bestia."),
("SUV", "Wielkie auta o mocy sportowych coupe, na wyjazdy i na pokaz."),
("Coupe i supersamochody", "Dwudrzwiowe auta o wysokich osiągach: V8, V10, boxer i hybrydy."),
("Tuned: po modyfikacjach", "Auta po tuningu (G-Power, Manhart, Brabus i inne): 600 do 1000+ KM, głośne wydechy, strzały."),
("Jazdy próbne i sesje", "Krótkie usługi na godziny: jazda próbna przed wynajmem i sesje zdjęciowe.");

INSERT INTO uslugi (id_kategorii, nazwa, slug, typ, opis, czas_trwania, cena) VALUES
(1, 'BMW M3 Competition', 'bmw-m3-competition', 'doby', 'Sedan na co dzień, a na torze zamienia się w bestię.', 1440, 990.00),
(1, 'Audi RS3 Sedan', 'audi-rs3-sedan', 'doby', 'Pięć cylindrów i quattro: dźwięk, który poznasz po jednym ruszeniu.', 1440, 820.00),
(1, 'Mercedes-AMG C63 S E Performance', 'mercedes-c63-s', 'doby', 'Hybryda 680 KM w garniturze: szybka i bardzo komfortowa.', 1440, 1800.00),
(1, 'Cadillac CTS-V', 'cadillac-cts-v', 'doby', 'Amerykański V8 z kompresorem, słychać go z dwóch ulic dalej.', 1440, 1100.00),
(1, 'BMW M5 F90 Competition', 'bmw-m5-f90-competition', 'doby', 'M5 F90: limuzyna, która w trybie 2WD pali gumę na każdym rondzie.', 1440, 1600.00),
(1, 'Mercedes-AMG E63 S', 'mercedes-e63s', 'doby', 'Klasa E z V8, która mruczy w mieście i ryczy na autostradzie.', 1440, 1300.00),
(1, 'Audi RS6 Avant', 'audi-rs6-avant', 'doby', 'Kombi z 600 KM: zabierze bagaż i zostawi sportowe auta z tyłu.', 1440, 1500.00),
(2, 'BMW X5 M Competition', 'bmw-x5-m-competition', 'doby', 'Rodzinny SUV, który zostawi większość sportowych aut z tyłu.', 1440, 1500.00),
(2, 'Audi RS Q8', 'audi-rs-q8', 'doby', 'Duży, luksusowy i zaskakująco szybki w zakrętach.', 1440, 1700.00),
(2, 'Porsche Cayenne Turbo GT', 'porsche-cayenne-turbo-gt', 'doby', 'SUV z genami toru: sztywny, szybki i bardzo głośny.', 1440, 2600.00),
(2, 'Lamborghini Urus', 'lamborghini-urus', 'doby', 'Supersamochód w ciele SUV-a, widać go z daleka.', 1440, 4200.00),
(3, 'Ford Mustang GT', 'ford-mustang-gt', 'doby', 'Klasyczne V8 i dźwięk, który kojarzy się z amerykańską niedzielą.', 1440, 690.00),
(3, 'Chevrolet Corvette C8', 'chevrolet-corvette-c8', 'doby', 'Silnik za plecami jak w supersamochodzie, ale za ułamek ceny.', 1440, 1400.00),
(3, 'Porsche 911 GT3', 'porsche-911-gt3', 'doby', 'Wolnossący boxer kręcący się do 9000 obr./min. Czysta rozkosz.', 1440, 3200.00),
(3, 'Lamborghini Huracán', 'lamborghini-huracan', 'doby', 'V10 i wygląd prosto z plakatu. Zwraca uwagę wszędzie.', 1440, 4500.00),
(3, 'Ferrari 296 GTB', 'ferrari-296-gtb', 'doby', 'Flagowiec floty: 830 KM i hybrydowy napęd.', 1440, 9500.00),
(3, 'BMW M4 CSL', 'bmw-m4-csl', 'doby', 'Lekkie coupe z toru, limitowana seria i dźwięk jak z wyścigówki.', 1440, 2900.00),
(4, 'BMW M3 Competition Stage 3', 'bmw-m3-stage3', 'doby', 'M3 po tuningu: ponad 900 KM, strzały z wydechu i dźwięk, którego nie zapomnisz.', 1440, 3500.00),
(4, 'Nissan GT-R R35 Stage 4', 'nissan-gtr-stage4', 'doby', 'Godzilla po tuningu: 1000 KM i przyspieszenie, które zostawia wrażenie.', 1440, 5500.00),
(4, 'Audi RS3 Stage 2', 'audi-rs3-stage2', 'doby', 'Mały sedan, duże możliwości: 550+ KM i bulgoczący pięciocylindrowiec.', 1440, 1500.00),
(4, 'Ford Mustang GT Kompresor', 'mustang-kompresor', 'doby', 'Mustang z kompresorem: 800+ KM i wycie, które słychać z daleka.', 1440, 1900.00),
(4, 'Dodge Challenger Hellcat Redeye', 'dodge-hellcat-tuned', 'doby', 'Muscle car z kompresorem i podniesioną mocą. Wycie V8 robi wrażenie.', 1440, 2400.00),
(4, 'Mercedes-AMG G63 Stage 2', 'g63-stage2', 'doby', 'Kultowa G-klasa z 800+ KM i głośnym wydechem. Auto, które robi show.', 1440, 6800.00),
(4, 'G-Power M5 F90 Hurricane RS', 'gpower-m5-hurricane-rs', 'doby', 'M5 F90 po przeróbce G-Power: 800+ KM i wydech, który budzi osiedle.', 1440, 3900.00),
(4, 'Manhart MH5 700 (BMW M5 F90)', 'manhart-mh5-700', 'doby', 'M5 po tuningu Manhart: 700+ KM i brzmienie, od którego drżą szyby.', 1440, 3200.00),
(4, 'Manhart MH4 (BMW M4 Competition)', 'manhart-mh4-m4', 'doby', 'M4 z pakietem Manhart: 620+ KM, napęd na tył i strzały z wydechu.', 1440, 2600.00),
(4, 'Brabus G 800 Widestar (G63)', 'brabus-g800-widestar', 'doby', 'Brabus 800 na bazie G63: najgłośniejsza twierdza na kołach.', 1440, 7900.00),
(4, 'Brabus 800 (Mercedes-AMG E63 S)', 'brabus-800-e63', 'doby', 'Limuzyna z 800 KM od Brabusa: w garniturze, ale z pazurami.', 1440, 4800.00),
(5, 'Jazda próbna: BMW M5 F90 Competition (1 godzina)', 'jazda-probna-m5', 'godziny', 'Godzina za kierownicą M5 F90 z pracownikiem obok. Idealna przed wynajmem na doby.', 60, 250.00),
(5, 'Jazda próbna: Brabus G 800 Widestar (1 godzina)', 'jazda-probna-brabus', 'godziny', 'Godzina w najgłośniejszej G-klasie w garażu.', 60, 450.00),
(5, 'Jazda próbna: Manhart MH5 700 (30 minut)', 'jazda-probna-manhart', 'godziny', 'Krótka jazda po okolicy, żeby poczuć 700+ KM.', 30, 180.00),
(5, 'Sesja zdjęciowa z autem (2 godziny)', 'sesja-zdjeciowa', 'godziny', 'Dwie godziny z wybranym autem na sesję zdjęciową lub filmową, bez jazdy po drogach publicznych.', 120, 600.00);

INSERT INTO pracownicy (id_uzytkownika, opis, aktywne) VALUES
(2, "Specjalistka ds. obsługi klienta i wydań aut (sedany, SUV, coupe).", TRUE),
(3, "Kierownik floty aut tuningowanych i sportowych.", TRUE);

INSERT INTO uslugi_pracownikow (id_pracownika, id_uslugi) VALUES
(1,1),
(1,2),
(1,3),
(1,4),
(1,5),
(1,6),
(1,7),
(1,8),
(1,9),
(1,10),
(1,11),
(1,12),
(2,12),
(1,13),
(2,13),
(1,14),
(2,14),
(1,15),
(2,15),
(1,16),
(2,16),
(1,17),
(2,17),
(2,18),
(2,19),
(2,20),
(2,21),
(2,22),
(2,23),
(2,24),
(2,25),
(2,26),
(2,27),
(2,28),
(1,29),
(2,30),
(2,31),
(1,32),
(2,32);

-- Godziny pracy: dzien_tygodnia 1 = poniedziałek ... 7 = niedziela.
-- Jeden dzień może mieć kilka przedziałów (przerwa = luka między nimi).
-- Wiersz z wypełnioną datą nadpisuje ten dzień; początek = koniec oznacza dzień wolny.
INSERT INTO godziny_pracy_pracownikow (id_pracownika, dzien_tygodnia, data, godzina_rozpoczecia, godzina_zakonczenia) VALUES
(1, 1, NULL, "08:00:00", "12:00:00"),
(1, 1, NULL, "12:30:00", "18:00:00"),
(1, 2, NULL, "08:00:00", "12:00:00"),
(1, 2, NULL, "12:30:00", "18:00:00"),
(1, 3, NULL, "08:00:00", "12:00:00"),
(1, 3, NULL, "12:30:00", "18:00:00"),
(1, 4, NULL, "08:00:00", "12:00:00"),
(1, 4, NULL, "12:30:00", "18:00:00"),
(1, 5, NULL, "08:00:00", "12:00:00"),
(1, 5, NULL, "12:30:00", "16:00:00"),
(2, 2, NULL, "09:00:00", "17:00:00"),
(2, 3, NULL, "09:00:00", "17:00:00"),
(2, 4, NULL, "09:00:00", "17:00:00"),
(2, 5, NULL, "09:00:00", "17:00:00"),
(2, 6, NULL, "09:00:00", "14:00:00"),
(2, 7, NULL, "10:00:00", "14:00:00"),
(2, 1, NULL, "10:00:00", "16:00:00"),
(1, 6, NULL, "09:00:00", "14:00:00");

INSERT INTO rezerwacje (id_uzytkownika, id_pracownika, id_uslugi, data_rezerwacji, godzina_rozpoczecia, godzina_zakonczenia, data_zakonczenia, liczba_dob, status, komentarz, cena) VALUES
(1, 2, (SELECT id FROM uslugi WHERE slug = 'brabus-g800-widestar'), "2026-06-12", "10:00:00", "10:00:00", "2026-06-14", 2, "zakonczone", 'Wesele: prosimy o przygotowanie auta do sesji zdjęciowej.', 15800.00),
(4, 1, (SELECT id FROM uslugi WHERE slug = 'bmw-m5-f90-competition'), "2026-07-03", "09:00:00", "09:00:00", "2026-07-06", 3, "zakonczone", NULL, 4800.00),
(1, 2, (SELECT id FROM uslugi WHERE slug = 'gpower-m5-hurricane-rs'), "2026-09-18", "10:00:00", "10:00:00", "2026-09-19", 1, "zakonczone", 'Prezent urodzinowy.', 3900.00),
(4, 1, (SELECT id FROM uslugi WHERE slug = 'ford-mustang-gt'), "2026-10-07", "08:00:00", "08:00:00", "2026-10-09", 2, "anulowane", NULL, 1380.00),
(1, 2, (SELECT id FROM uslugi WHERE slug = 'manhart-mh5-700'), "2026-11-12", "10:00:00", "10:00:00", "2026-11-15", 3, "potwierdzone", 'Wyjazd na weekend w góry.', 9600.00),
(4, 1, (SELECT id FROM uslugi WHERE slug = 'audi-rs6-avant'), "2026-11-20", "09:00:00", "09:00:00", "2026-11-23", 3, "oczekujace", NULL, 4500.00),
(1, 1, (SELECT id FROM uslugi WHERE slug = 'jazda-probna-m5'), "2026-11-10", "14:00:00", "15:00:00", "2026-11-10", NULL, "potwierdzone", 'Chcę sprawdzić auto przed wynajmem.', 250.00),
(4, 2, (SELECT id FROM uslugi WHERE slug = 'brabus-800-e63'), "2026-12-04", "10:00:00", "10:00:00", "2026-12-07", 3, "potwierdzone", 'Sylwester już zaplanowany, to przed.', 14400.00),
(1, 1, (SELECT id FROM uslugi WHERE slug = 'bmw-m3-competition'), "2026-11-26", "09:00:00", "09:00:00", "2026-11-28", 2, "oczekujace", NULL, 1980.00),
(4, 2, (SELECT id FROM uslugi WHERE slug = 'sesja-zdjeciowa'), "2026-11-18", "11:00:00", "13:00:00", "2026-11-18", NULL, "oczekujace", 'Sesja do teledysku.', 600.00);

INSERT INTO historia_statusow (id_rezerwacji, stary_status, nowy_status, id_uzytkownika, utworzony) VALUES
(1, NULL, "oczekujace", 1, "2026-06-05 12:00:00"),
(1, "oczekujace", "potwierdzone", 3, "2026-06-05 15:30:00"),
(1, "potwierdzone", "zakonczone", 3, "2026-06-14 18:05:00"),
(2, NULL, "oczekujace", 4, "2026-06-26 12:00:00"),
(2, "oczekujace", "potwierdzone", 2, "2026-06-26 15:30:00"),
(2, "potwierdzone", "zakonczone", 2, "2026-07-06 18:05:00"),
(3, NULL, "oczekujace", 1, "2026-09-11 12:00:00"),
(3, "oczekujace", "potwierdzone", 3, "2026-09-11 15:30:00"),
(3, "potwierdzone", "zakonczone", 3, "2026-09-19 18:05:00"),
(4, NULL, "oczekujace", 4, "2026-09-30 12:00:00"),
(4, "oczekujace", "anulowane", 4, "2026-09-30 20:40:00"),
(5, NULL, "oczekujace", 1, "2026-11-05 12:00:00"),
(5, "oczekujace", "potwierdzone", 3, "2026-11-05 15:30:00"),
(6, NULL, "oczekujace", 4, "2026-11-13 12:00:00"),
(7, NULL, "oczekujace", 1, "2026-11-03 12:00:00"),
(7, "oczekujace", "potwierdzone", 2, "2026-11-03 15:30:00"),
(8, NULL, "oczekujace", 4, "2026-11-27 12:00:00"),
(8, "oczekujace", "potwierdzone", 3, "2026-11-27 15:30:00"),
(9, NULL, "oczekujace", 1, "2026-11-19 12:00:00"),
(10, NULL, "oczekujace", 4, "2026-11-11 12:00:00");

INSERT INTO log_admina (id_admina, akcja, opis, utworzony) VALUES
(5, "usluga_dodanie", "Dodano usługę: Brabus G 800 Widestar (G63)", "2026-10-20 09:12:00"),
(5, "pracownik_edycja", "Zmieniono pracownika #2 (usługi: 16)", "2026-10-20 09:30:00"),
(5, "dostepnosc_tydzien", "Zmieniono godziny pracy pracownika #2: Kamil Woźnica", "2026-10-20 09:41:00");
