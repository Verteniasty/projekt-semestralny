# RentLoudCars

## Autorzy
* **Tymoteusz Kurowski**
* **Sebastian Kobryń**

---

## Opis systemu
**rentloudcars** to nowoczesny, internetowy system rezerwacji oraz zarządzania flotą samochodową w wypożyczalni aut. Aplikacja umożliwia użytkownikom przeglądanie dostępnych pojazdów z podziałem na kategorie (samochody osobowe, SUV-y, auta sportowe i premium), sprawdzanie szczegółowych informacji o cenie i czasie trwania wynajmu oraz dokonywanie rezerwacji on-line.

---

## Zastosowane technologie
* **Frontend:** HTML5, CSS3 (z wykorzystaniem dedykowanych arkuszy stylów dla floty i podstron)
* **Skrypty i Logika:** JavaScript
* **Baza danych:** MySQL (v8.0+)
* **Zarządzanie wersjami:** Git / GitHub

---

## Struktura projektu
```text
projekt-semestralny/
│
├── assets/
│   └── fura.jpg
├── css/
│   ├── garaz-style.css
│   └── style.css
├── database/
│   ├── database.sql
│   └── erd.png
├── js/
│   └── index.js
├── public/
│   ├── Garaz.html
│   ├── Kontakt.html
│   ├── O-nas.html
│   ├── Strona-glowna.html
│   └── wypozyczalnia.css
├── .gitignore
└── README.md
```

---

## Instrukcja uruchomienia

### 1. Konfiguracja bazy danych
1. Uruchom lokalny serwer MySQL (np. XAMPP, WampServer lub dedykowany MySQL Server).
2. Zaimportuj plik bazy danych znajdujący się w katalogu `database/database.sql` (możesz użyć narzędzia phpMyAdmin, DBeaver lub MySQL Workbench).
3. Skrypt automatycznie utworzy bazę danych `Wypozyczalnia`, stworzy wymagane tabele wraz z indeksami oraz uzupełni je danymi testowymi.

### 2. Uruchomienie aplikacji
1. Otwórz projekt w edytorze kodu (np. Visual Studio Code).
2. Uruchom lokalny serwer statyczny (np. za pomocą wtyczki **Live Server** w VS Code).
3. Otwórz w przeglądarce plik startowy z folderu `public/Strona-glowna.html`.

---

## Testowe dane logowania
Hasło dla wszystkich kont testowych to: **`test123!`** (w bazie zapisane jako hash SHA-256: `e1dddc844ca8ad19718295dbf2f0ed6746b459c2e3582ef8bf909812a24d9fe7`).

* **Pracownik 1:** `Anna.Nowak@gmail.com`
* **Pracownik 2:** `Kamil.Woznica@gmail.com`
* **Klient (użytkownik):** `Tomasz.Kuzicki@gmail.com`

---