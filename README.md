# RentLoudCars

# Link do Strony
https://rentloudcars.infinityfree.io

## Autorzy
* **Tymoteusz Kurowski**
* **Sebastian Kobryń**

---

## Opis systemu
**RentLoudCars** to internetowy system rezerwacji i zarządzania flotą samochodową w wypożyczalni aut. Użytkownicy przeglądają auta z podziałem na kategorie (samochody osobowe, SUV-y, auta sportowe i premium), widzą cenę i czas wynajmu oraz rezerwują je on-line. System ma trzy role: klient, pracownik i administrator.

**Stan projektu:** wersja końcowa (etap 5). Gotowe: konta i role, oferta (28 aut), rezerwacje na doby i na godziny, panele klienta, pracownika i administratora, kalendarz floty, statystyki, eksport CSV, dziennik zmian.

**Jak działa rezerwacja:** auta wynajmuje się na **doby** (cena za dobę, 1–30 dób), a usługi dodatkowe (jazda próbna, sesja zdjęciowa) na **godziny**. Klient wybiera usługę, pracownika, datę i godzinę z listy wolnych terminów, potem widzi podsumowanie z ceną. Auto jest zablokowane na cały wynajem, a pracownik tylko na 30 minut przekazania przy odbiorze i zwrocie. Przedziały godzin, które się stykają (np. 9:00–10:00 i 10:00–11:00), nie są konfliktem.

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
│   ├── Strona-glowna.php, Garaz.php, O-nas.php, Kontakt.php, oferta.php
│   ├── rejestracja.php, logowanie.php, wylogowanie.php, profil.php
│   ├── reset-hasla.php, nowe-haslo.php
│   ├── rezerwacja.php (+ rezerwacja.js), api-sloty.php (AJAX: wolne godziny)
│   ├── moje-rezerwacje.php, panel-klienta.php, panel-pracownika.php
│   ├── panel-admina.php (pulpit), admin-rezerwacje.php, admin-rezerwacja.php
│   ├── admin-kalendarz.php, admin-uslugi.php, admin-kategorie.php
│   ├── admin-pracownicy.php, admin-dostepnosc.php, admin-uzytkownicy.php
│   ├── admin-log.php, admin-eksport.php (CSV)
│   ├── style.css, garaz-style.css, index.js, garaz.js
│   ├── garaz-hero.jpg, auta/  # zdjęcia aut (auta/<slug>.jpg, brak zdjęcia = tło zastępcze)
├── src/                     # kod wspólny (poza katalogiem publicznym)
│   ├── bootstrap.php        # start: konfiguracja, sesja, błędy
│   ├── auth.php             # logowanie, role, kontrola dostępu
│   ├── walidacja.php        # walidacja danych po stronie serwera
│   ├── pomocnicze.php       # escapowanie, CSRF, komunikaty
│   ├── dostepnosc.php       # godziny pracy, konflikty, wolne terminy
│   ├── rezerwacje.php       # tworzenie rezerwacji, statusy, historia
│   ├── admin.php            # wspólne elementy panelu admina, wykresy
│   ├── mail.php, reset.php  # wysyłka maili i reset hasła
│   ├── layout.php           # wspólny nagłówek, stopka, pole formularza
│   ├── db.php               # połączenie PDO
│   ├── flota.php            # dane aut w garażu
│   └── config.example.php   # wzór konfiguracji (config.php jest w .gitignore)
├── docs/                    # DOKUMENTACJA.md, OPIS-ROZWIAZAN-BAZY.txt (widoki, wyzwalacze, funkcje)
├── tests/                   # testy automatyczne HTTP (patrz tests/README.md)
├── storage/                 # maile.log (lokalny „skrzynka” z linkami resetu)
├── index.php                # przekierowanie na stronę główną
├── .gitignore
└── README.md
```

---

## Instrukcja uruchomienia

### 1. Baza danych
1. Uruchom MySQL/MariaDB (np. XAMPP).
2. Zaimportuj `database/database.sql` (phpMyAdmin, DBeaver lub MySQL Workbench). Skrypt kasuje i tworzy od nowa bazę `Wypozyczalnia`, tabele, indeksy i dane testowe. **Po aktualizacji projektu zaimportuj go ponownie** (doszły m.in. rezerwacje na doby, tabele `tokeny_hasla`, `historia_statusow`, `log_admina`).

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

## Funkcje

### Konta i role (etap 2)
* rejestracja (imię, nazwisko, e-mail, telefon, hasło i jego powtórzenie) z walidacją po stronie serwera i kontrolą unikalności e-maila,
* logowanie, wylogowanie i sesje; po zalogowaniu przekierowanie do panelu właściwego dla roli,
* trzy role (klient, pracownik, administrator); każdy panel jest dostępny tylko dla swojej roli, inni dostają błąd 403 także po ręcznym wpisaniu adresu URL,
* profil użytkownika: edycja imienia, nazwiska i telefonu oraz zmiana hasła po podaniu starego,
* zmiana hasła i przycisk „Wyloguj z innych urządzeń” unieważniają wszystkie pozostałe sesje konta (`wersja_sesji`),
* reset hasła przez e-mail (`reset-hasla.php`): jednorazowy token ważny 60 minut. Lokalnie „mail” ląduje w `storage/maile.log`, stamtąd skopiuj link.

### Klient
* oferta z podziałem na kategorie, rezerwacja krok po kroku (usługa → pracownik → data → godzina → potwierdzenie),
* wolne godziny ładowane przez AJAX (`api-sloty.php`), liczba dób i cena przeliczane na bieżąco,
* „Moje rezerwacje” z filtrem statusu i anulowaniem własnej przyszłej rezerwacji; klient widzi tylko swoje dane.

### Pracownik
* panel z własnymi rezerwacjami, potwierdzanie, anulowanie i kończenie; cudze rezerwacje są niedostępne.

### Administrator
* pulpit: auta na wynajmie, wydania i zwroty dziś, rezerwacje do potwierdzenia, przychód, obłożenie floty, wykresy,
* rezerwacje (wyszukiwanie, filtry, sortowanie, paginacja, zmiana statusu), szczegóły z historią statusów,
* kalendarz floty (miesiąc, filtr kategorii), eksport rezerwacji do CSV,
* CRUD usług, kategorii i pracowników, godziny pracy (tydzień + wyjątki dla dat), użytkownicy (rola, blokada konta),
* dziennik działań administratora (`admin-log.php`),
* ochrony: nie usuniesz usługi, kategorii ani pracownika z rezerwacjami (można je dezaktywować), nie zmienisz sobie roli ani nie zablokujesz własnego konta.

### Reguły rezerwacji
* statusy: oczekujące → potwierdzone | anulowane, potwierdzone → zakończone | anulowane; pozostałe przejścia są zablokowane,
* godziny pracy pracownika z przerwą i wyjątkami, krok 15 min, rezerwacje do 365 dni naprzód, brak dat z przeszłości,
* konflikty: to samo auto, ten sam pracownik i ten sam klient w nakładających się terminach; zapis w transakcji z blokadami (`FOR UPDATE`), więc równoległe kliknięcia nie tworzą dubli,
* cena jest zapisywana w rezerwacji (późniejsza zmiana cennika jej nie zmienia).

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

## Testy
Logika jest testowana automatycznie skryptami HTTP z folderu `tests/` (instrukcja w `tests/README.md`) na kopii bazy w SQLite: dostęp do każdej strony dla gościa i każdej roli, przejścia statusów, konflikty terminów, 8 równoległych rezerwacji tego samego auta/terminu, IDOR, XSS, SQL injection, CSRF, blokada logowania, reset hasła, eksport CSV. Wygląd sprawdzono na szerokościach 1366 px i 390 px. Przed oddaniem warto przejść ręcznie scenariusz: rejestracja → rezerwacja → potwierdzenie przez pracownika → anulowanie na MySQL.

---

## Lista kontrolna do etapu 5
- [x] repozytorium z pracą obu autorów, README z instrukcją i kontami testowymi
- [x] `database/database.sql` odtwarza całą bazę, `erd.png` zgodny ze schematem
- [x] rejestracja, logowanie, wylogowanie, sesje, trzy role
- [x] oferta i trzy panele
- [x] rezerwacja: usługa → pracownik → data → godzina → potwierdzenie
- [x] godziny pracy, czas usługi i wszystkie konflikty
- [x] zabezpieczenia po stronie serwera, responsywny interfejs z komunikatami
- [x] filtry oferty (kategoria, rodzaj, kalendarz, dostępność), mail powitalny po rejestracji
- [x] widoki, funkcja, procedura i wyzwalacze w bazie, dokumentacja w `docs/`
- [x] rozszerzenia: kalendarz, AJAX, reset hasła (e-mail do pliku), statystyki, eksport CSV, historia operacji, dziennik admina
