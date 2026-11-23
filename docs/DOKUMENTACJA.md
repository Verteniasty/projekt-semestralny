# Dokumentacja aplikacji RentLoudCars

## 1. Cel i zakres
System rezerwacji aut wypożyczalni. Klient przegląda ofertę, filtruje ją (kategoria, rodzaj, data z kalendarza, dostępność) i rezerwuje auto na doby lub usługę na godziny. Pracownik obsługuje swoje rezerwacje, administrator zarządza całym serwisem.

## 2. Architektura
Aplikacja jest podzielona na front end i back end.

* **Front end** (przeglądarka): HTML5, CSS3 (`public/style.css`), JavaScript (`index.js` menu, `rezerwacja.js` pobieranie wolnych godzin przez AJAX, `garaz.js` galeria). Strona działa na telefonie i komputerze.
* **Back end** (serwer): PHP 8 z PDO i sesjami. Strony w `public/` tylko odbierają żądanie i wyświetlają wynik, a logika biznesowa jest w `src/`.
* **Baza danych**: MySQL 8 (`database/database.sql`): tabele, widoki, funkcja, procedura, wyzwalacze, dane testowe.

Przepływ żądania: przeglądarka → strona z `public/` → `src/bootstrap.php` (konfiguracja, sesja, nagłówki bezpieczeństwa) → funkcje z `src/` → PDO (prepared statements) → MySQL → HTML lub JSON (`api-sloty.php`).

## 3. Struktura folderów
| Folder | Zawartość |
|--------|-----------|
| `public/` | jedyny katalog dostępny z sieci: strony (widoki), CSS, JS, zdjęcia `auta/` |
| `src/` | logika: `auth.php` (logowanie, role), `dostepnosc.php` (godziny pracy, konflikty), `rezerwacje.php` (rezerwacje, statusy), `walidacja.php`, `pomocnicze.php` (CSRF, escapowanie), `admin.php`, `reset.php`, `mail.php`, `layout.php`, `db.php`, `flota.php` |
| `database/` | `database.sql` (cała baza), `erd.png` (diagram) |
| `storage/` | `maile.log`: lokalna skrzynka maili (reset hasła, potwierdzenia) |
| `tests/` | testy automatyczne HTTP (patrz `tests/README.md`) |
| `docs/` | dokumentacja i opis rozwiązań bazy danych |
| `assets/` | grafiki projektu |

Katalogi `src/`, `database/`, `storage/` mają `.htaccess` z zakazem dostępu z sieci.

## 4. Role i uprawnienia
| Rola | Może |
|------|------|
| Gość | oglądać ofertę i filtrować ją, rejestrować się, logować, resetować hasło |
| Klient | rezerwować, przeglądać historię, anulować własne przyszłe rezerwacje, edytować profil i hasło |
| Pracownik | widzieć i obsługiwać tylko własne rezerwacje (potwierdzenie, anulowanie, zakończenie) |
| Administrator | wszystko powyżej plus zarządzanie klientami, pracownikami, kategoriami, usługami, godzinami pracy, rezerwacjami, kalendarzem floty, eksport CSV, dziennik działań |

Rola i aktywność konta są sprawdzane w bazie przy każdym żądaniu. Menu zaplecza zależy od roli.

## 5. Najważniejsze procesy
**Rejestracja i logowanie.** Formularz z walidacją po stronie serwera, hasło w bcrypt, po rejestracji mail powitalny. Pięć błędnych haseł blokuje konto na 15 minut. Reset hasła: jednorazowy link ważny 60 minut (w bazie tylko hash tokenu).

**Rezerwacja.** Usługa → pracownik → data → godzina (AJAX) → potwierdzenie. Serwer przy zapisie ponownie sprawdza wszystko w transakcji z blokadami `FOR UPDATE`: godziny pracy pracownika, konflikt auta, pracownika i klienta, zakres dat, liczbę dób (1–30). Przedziały stykające się nie są konfliktem. Auto jest zajęte przez cały wynajem, pracownik tylko na 30 minut przekazania przy odbiorze i zwrocie.

**Statusy.** oczekujące → potwierdzone | anulowane; potwierdzone → zakończone | anulowane. Każda zmiana trafia do `historia_statusow`, a klient dostaje mail.

**Filtry oferty.** `oferta.php?kategoria=..&typ=..&data=..&dostepne=1`. Dla wybranej daty serwer sprawdza, czy któryś pracownik ma wolny termin, i pokazuje „Wolne/Zajęte”.

## 6. Baza danych
Diagram: `database/erd.png`. Tabele: `uzytkownicy`, `tokeny_hasla`, `kategorie_uslug`, `uslugi`, `pracownicy`, `uslugi_pracownikow`, `godziny_pracy_pracownikow`, `rezerwacje`, `historia_statusow`, `log_admina`. Do tego widoki, funkcja, procedura i wyzwalacze (opis w `OPIS-ROZWIAZAN-BAZY.txt`). Klucze obce z `ON DELETE RESTRICT` chronią usługi i pracowników mających rezerwacje, cena jest zapisywana w rezerwacji.

## 7. Zabezpieczenia
Prepared statements (PDO bez emulacji), hasła bcrypt, escapowanie wyjścia (XSS), tokeny CSRF we wszystkich formularzach POST (także wylogowanie), sesja HttpOnly + SameSite, wygasanie po 30 minutach, unieważnianie sesji po zmianie hasła, nagłówki CSP/X-Frame-Options/nosniff, jednolity komunikat przy błędnym logowaniu, ochrona CSV przed wstrzykiwaniem formuł, brak haseł w repozytorium (`src/config.php` w `.gitignore`).

## 8. Instalacja
1. Zaimportuj `database/database.sql` (phpMyAdmin → Importuj). Skrypt tworzy bazę `Wypozyczalnia`, tabele, widoki, wyzwalacze i dane testowe.
2. Skopiuj `src/config.example.php` jako `src/config.php` i wpisz dane bazy.
3. Ustaw katalog główny serwera na `public/` (albo `php -S localhost:8000 -t public`).
4. Konta testowe: patrz `README.md` (hasło `test123!`).

Na hostingu (np. InfinityFree) wgraj projekt tak, aby `public/` był katalogiem głównym domeny, a `src/`, `database/`, `storage/` leżały poza nim lub były zablokowane przez `.htaccess`. Jeśli hosting nie pozwala na `DELIMITER`, importuj część z wyzwalaczami osobno w zakładce SQL.

## 9. Testy
Opis w `tests/README.md`: około 250 sprawdzeń HTTP (dostęp, rezerwacje, równoległość, bezpieczeństwo, filtry).
