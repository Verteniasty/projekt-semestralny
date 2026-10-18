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
    stworzony TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE kategorie_uslug (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nazwa VARCHAR(50) NOT NULL,
    opis VARCHAR(512)
);

CREATE TABLE uslugi (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_kategorii INT NOT NULL,
    nazwa VARCHAR(50) NOT NULL,
    opis VARCHAR(512),
    czas_trwania INT NOT NULL,
    cena DECIMAL(16,2) NOT NULL,
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
    data_rezerwacji DATE NOT NULL,
    godzina_rozpoczecia TIME NOT NULL,
    godzina_zakonczenia TIME NOT NULL,
    status ENUM("oczekujace", "potwierdzone", "anulowane", "zakonczone") NOT NULL DEFAULT 'oczekujace',
    komentarz VARCHAR(512) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pracownika) REFERENCES pracownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (id_uslugi) REFERENCES uslugi(id) ON DELETE CASCADE
);

CREATE INDEX idx_rezerwacje_data ON rezerwacje(data_rezerwacji);

-- Hasło wszystkich kont testowych: test123!
-- W bazie jest hash bcrypt (password_hash w PHP), każde konto ma własny "salt".

INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, numer_telefonu, rola) VALUES
("Tomasz", "Kuzicki", "Tomasz.Kuzicki@gmail.com", "$2y$10$wfduwn8Crbvgfvc0aSUqcez7Zsl9dKWm5A6THnLoLA38fUUqhrzby", "+48203591342", "klient"),
("Anna", "Nowak", "Anna.Nowak@gmail.com", "$2y$10$owSanR8r35KITWdrmYCwV.pTUyl2xl1FU4AoTCCia7RQWa.JYudGG", "+48493582532", "pracownik"),
("Kamil", "Woźnica", "Kamil.Woznica@gmail.com", "$2y$10$HT1Fw.mh8sNPA9CE.3i/oOZyEgvthwRQ1mT0c3szsmBtD6VOM0cIK", "+48624593201", "pracownik"),
("Karolina", "Celin", "Karolina.Celin@gmail.com", "$2y$10$0dP7t8aIdT8uZHxW.n0QWeX2JnvPlboPja7WyWYKj/7/M.Xsib/q6", "+48734593428", "klient"),
("Adam", "Administrator", "admin@rentloudcars.pl", "$2y$10$VYFR0k5NKW2XsmgOYlb.HOYMcuKRx8hhjzvsjozYH09OwLTYulJxO", "+48500100200", "administrator");

INSERT INTO kategorie_uslug (nazwa, opis) VALUES
("Samochody Osobowe", "Ekonomiczne i komfortowe auta miejskie oraz kompaktowe."),
("SUV / Crossover", "Przestronne auta z napędem 4x4 na każdą trasę i rodzinne wyjazdy."),
("Samochody Sportowe / Premium", "Ekskluzywne auta o wysokich osiągach na specjalne okazje.");

INSERT INTO uslugi (id_kategorii, nazwa, opis, czas_trwania, cena) VALUES 
(1, 'Toyota Corolla (Doba)', 'Najpopularniejszy sedan, hybryda, automatyczna skrzynia biegów.', 1440, 180.00),
(1, 'Skoda Fabia (Doba)', 'Ekonomiczne auto miejskie, idealne na codzienne dojazdy.', 1440, 130.00),
(2, 'Kia Sportage (Doba)', 'Nowoczesny SUV z bogatym wyposażeniem i napędem 4x4.', 1440, 320.00),
(3, 'BMW Serii 4 Coupe (Doba)', 'Sportowe coupe o dynamicznym charakterze i luksusowym wnętrzu.', 1440, 650.00);

INSERT INTO pracownicy (id_uzytkownika, opis, aktywne) VALUES
(2, "Specjalista ds. obsługi klienta i wydań floty osobowej.", TRUE),
(3, "Kierownik floty aut premium i sportowych", TRUE);

INSERT INTO uslugi_pracownikow (id_pracownika, id_uslugi) VALUES
(1,1),
(1,2),
(1,3),
(2,4);

INSERT INTO godziny_pracy_pracownikow (id_pracownika, dzien_tygodnia, data, godzina_rozpoczecia, godzina_zakonczenia) VALUES
(1, 1, NULL, "08:00:00", "18:00:00"),
(1, 2, NULL, "08:00:00", "18:00:00"),
(2, 5, NULL, "09:00:00", "17:00:00"),
(2, 6, NULL, "09:00:00", "14:00:00");

INSERT INTO rezerwacje (id_uzytkownika, id_pracownika, id_uslugi, data_rezerwacji, godzina_rozpoczecia, godzina_zakonczenia, status, komentarz) VALUES
(1, 1, 1, "2026-06-10", "10:00:00", "10:00:00", "potwierdzone", "Proszę o przygotowanie fotelika dziecięcego."),
(4, 2, 4, "2026-06-15", "14:00:00", "14:00:00", "oczekujace", "Wynajem na weekend ślubny.");