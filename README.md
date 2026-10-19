# RentLoudCars

## Autorzy
* **Tymoteusz Kurowski**
* **Sebastian Kobryń**

---

## Opis systemu
**RentLoudCars** to internetowy system rezerwacji i zarządzania flotą samochodową w wypożyczalni aut. Użytkownicy przeglądają auta z podziałem na kategorie (samochody osobowe, SUV-y, auta sportowe i premium), widzą cenę i czas wynajmu oraz rezerwują je on-line. System ma trzy role: klient, pracownik i administrator.

**Stan projektu:** etap 2 (użytkownicy, rejestracja i logowanie) jest gotowy. Panele pracownika i administratora oraz rezerwacje powstaną w kolejnych etapach.

---

## Zastosowane technologie
* **Backend:** PHP 8.1+ (PDO, sesje)
* **Frontend:** HTML5, CSS3, JavaScript
* **Baza danych:** MySQL 8.0+ / MariaDB
* **Zarządzanie wersjami:** Git / GitHub

---

## Struktura projektu
```text
projekt-semestralny/
│
├── assets/                  # grafiki
├── database/
│   ├── database.sql         # struktura bazy + dane testowe
│   └── erd.png              # diagram ERD
├── public/                  # katalog główny serwera WWW
│   ├── Strona-glowna.php, Garaz.php, O-nas.php, Kontakt.php
│   ├── rejestracja.php, logowanie.php, wylogowanie.php, profil.php
│   ├── reset-hasla.php, nowe-haslo.php
│   ├── panel-klienta.php, panel-pracownika.php, panel-admina.php
│   ├── style.css, garaz-style.css, index.js, garaz.js
│   ├── garaz-hero.jpg, auta/  # zdjęcia (auta/<slug>.jpg są opcjonalne)
├── src/                     # kod wspólny (poza katalogiem publicznym)
│   ├── bootstrap.php        # start: konfiguracja, sesja, błędy
│   ├── auth.php             # logowanie, role, kontrola dostępu
│   ├── walidacja.php        # walidacja danych po stronie serwera
│   ├── pomocnicze.php       # escapowanie, CSRF, komunikaty
│   ├── mail.php, reset.php  # wysyłka maili i reset hasła
│   ├── layout.php           # wspólny nagłówek, stopka, pole formularza
│   ├── db.php               # połączenie PDO
│   ├── flota.php            # dane aut w garażu
│   └── config.example.php   # wzór konfiguracji (config.php jest w .gitignore)
├── storage/                 # maile.log (lokalny „skrzynka” z linkami resetu)
├── index.php                # przekierowanie na stronę główną
├── .gitignore
└── README.md
```

---

## Instrukcja uruchomienia

### 1. Baza danych
1. Uruchom MySQL/MariaDB (np. XAMPP).
2. Zaimportuj `database/database.sql` (phpMyAdmin, DBeaver lub MySQL Workbench). Skrypt kasuje i tworzy od nowa bazę `Wypozyczalnia`, tabele, indeksy i dane testowe. **Po aktualizacji projektu zaimportuj go ponownie** (doszły kolumna `wersja_sesji` i tabela `tokeny_hasla`).

### 2. Konfiguracja
Skopiuj `src/config.example.php` jako `src/config.php` i wpisz dane dostępowe do bazy (w XAMPP zwykle użytkownik `root` i puste hasło). Plik `src/config.php` nie trafia do repozytorium. Opcjonalnie ustaw `base_url` (adres w linkach resetu hasła) i `mail_tryb` (`plik` albo `mail`), patrz `config.example.php`; bez nich wszystko działa na wartościach domyślnych.

### 3. Uruchomienie
**Wariant A: wbudowany serwer PHP** (w katalogu projektu):
```bash
php -S localhost:8000 -t public
```
Strona: http://localhost:8000/Strona-glowna.php

**Wariant B: XAMPP.** Skopiuj projekt do `htdocs` i otwórz `http://localhost/projekt-semestralny/public/Strona-glowna.php`.

---

## Testowe dane logowania
Hasło wszystkich kont testowych: **`test123!`** (w bazie jest hash bcrypt).

| Rola | E-mail |
|------|--------|
| Administrator | `admin@rentloudcars.pl` |
| Pracownik 1 | `Anna.Nowak@gmail.com` |
| Pracownik 2 | `Kamil.Woznica@gmail.com` |
| Klient | `Tomasz.Kuzicki@gmail.com` |
| Klient | `Karolina.Celin@gmail.com` |

Nowe konta założone przez formularz rejestracji zawsze mają rolę klienta.

---

## Funkcje etapu 2
* rejestracja (imię, nazwisko, e-mail, telefon, hasło i jego powtórzenie) z walidacją po stronie serwera i kontrolą unikalności e-maila,
* logowanie, wylogowanie i sesje; po zalogowaniu przekierowanie do panelu właściwego dla roli,
* trzy role (klient, pracownik, administrator); każdy panel jest dostępny tylko dla swojej roli, inni dostają błąd 403 także po ręcznym wpisaniu adresu URL,
* profil użytkownika: edycja imienia, nazwiska i telefonu oraz zmiana hasła po podaniu starego,
* zmiana hasła i przycisk „Wyloguj z innych urządzeń” unieważniają wszystkie pozostałe sesje konta (`wersja_sesji`),
* reset hasła przez e-mail (`reset-hasla.php`): jednorazowy token ważny 60 minut. Lokalnie „mail” ląduje w `storage/maile.log`, stamtąd skopiuj link.

### Zabezpieczenia
* hasła: `password_hash()` / `password_verify()` (bcrypt), automatyczne odświeżanie hasha,
* wszystkie zapytania SQL jako prepared statements (PDO, bez emulacji),
* walidacja po stronie serwera, wyjście HTML escapowane (ochrona przed XSS),
* tokeny CSRF we wszystkich formularzach POST (także wylogowanie),
* sesja: ciasteczko `HttpOnly` + `SameSite=Lax`, nowy identyfikator po zalogowaniu, wygasanie po 30 minutach bezczynności,
* przy każdym żądaniu rola i aktywność konta są sprawdzane w bazie,
* reset hasła: w bazie tylko SHA-256 tokenu, token jednorazowy i wygasający, ta sama odpowiedź dla istniejącego i nieistniejącego e-maila, limit 1 link na minutę,
* nagłówki: Content-Security-Policy (skrypty tylko z własnej domeny), X-Frame-Options, nosniff, Referrer-Policy; własna nazwa sesji,
* katalogi `src/`, `database/`, `storage/` mają `.htaccess` z `Require all denied`,
* limit prób logowania: 5 błędnych haseł blokuje konto na 15 minut,
* jeden komunikat „Nieprawidłowy e-mail lub hasło” (nie zdradza, czy konto istnieje), błędy techniczne trafiają do logu, nie na ekran,
* dane dostępowe do bazy poza repozytorium (`src/config.php` w `.gitignore`).

---
