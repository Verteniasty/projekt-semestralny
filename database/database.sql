DROP DATABASE IF EXISTS Wypozyczalnia;

CREATE DATABASE IF NOT EXISTS Wypozyczalnia
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE Wypozyczalnia

CREATE TABLE uzytkownicy (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    imie VARCHAR(40) NOT NULL,
    nazwisko VARCHAR(40) NOT NULL,
    email VARCHAR(60) NOT NULL,
    haslo VARCHAR(255) NOT NULL,
    numer_telefonu VARCHAR(30) NOT NULL,
    rola VARCHAR(50) NOT NULL,
    aktywne BOOLEAN NOT NULL DEFAULT TRUE,
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
    id_uzytkownika INT NOT NULL,
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
    status VARCHAR(30) NOT NULL DEFAULT 'oczekujace',
    komentarz VARCHAR(512) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pracownika) REFERENCES pracownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (id_uslugi) REFERENCES uslugi(id) ON DELETE CASCADE
);

-- e1dddc844ca8ad19718295dbf2f0ed6746b459c2e3582ef8bf909812a24d9fe7 = test123!

INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, numer_telefonu, rola)
VALUES ("Tomasz", "Kuzicki", "Tomasz.Kuzicki@gmail.com", "e1dddc844ca8ad19718295dbf2f0ed6746b459c2e3582ef8bf909812a24d9fe7", "+48203591342", "użytkownik")