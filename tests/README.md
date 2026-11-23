# Testy automatyczne

Testy HTTP uruchamiają prawdziwą aplikację (`php -S`) i sprawdzają ją jak przeglądarka (ciasteczka, tokeny CSRF).
Do testów używana jest kopia projektu z bazą **SQLite** (nie trzeba MySQL), budowana ze skryptu `database/database.sql`.

```bash
python3 tests/zbuduj_srodowisko_sqlite.py
cd tests/work/app && PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8099 -t public &
python3 tests/uruchom_testy.py
```

* `test_dostep.py`: dostęp do każdej strony dla gościa, klienta, pracownika i admina, API, ochrona katalogów, nagłówki CSP,
* `test_rezerwacje.py`: konflikty terminów, godziny pracy, wynajem na doby, przekazanie auta, równoległe rezerwacje,
* `test_bezpieczenstwo.py`: przejścia statusów, cudze dane (IDOR), CSRF, XSS, SQL injection, blokada logowania, reset hasła, eksport CSV.

Ograniczenia: wyzwalacze, funkcja i procedura MySQL nie działają w SQLite, więc testuje się je ręcznie w phpMyAdmin (przykłady w `docs/OPIS-ROZWIAZAN-BAZY.txt`).
Przed ponownym uruchomieniem zatrzymaj serwer i zbuduj środowisko od nowa (testy zmieniają dane). Katalog `tests/work/` jest w `.gitignore`.
