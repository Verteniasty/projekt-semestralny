"""Buduje kopię projektu w tests/work/app z bazą SQLite (bez MySQL), do testów automatycznych.

Uruchomienie: python3 tests/zbuduj_srodowisko_sqlite.py
Potem:  cd tests/work/app && PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8099 -t public
        python3 tests/uruchom_testy.py
Wyzwalacze i funkcje MySQL nie są testowane w SQLite (patrz README.md w tym folderze).
"""
import os, re, shutil, sqlite3

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.dirname(HERE)
DST = os.path.join(HERE, 'work', 'app')
shutil.rmtree(DST, ignore_errors=True)
shutil.copytree(SRC, DST, ignore=shutil.ignore_patterns('.git', 'work', 'tests'))

sql = open(DST + '/database/database.sql', encoding='utf8').read()
sql = sql[:sql.index('-- Funkcja i procedura oraz wyzwalacze')]   # DELIMITER/wyzwalacze tylko dla MySQL
sql = sql.replace('CREATE OR REPLACE VIEW', 'CREATE VIEW')
sql = re.sub(r'DROP DATABASE[^;]*;', '', sql)
sql = re.sub(r'CREATE DATABASE[^;]*;', '', sql, flags=re.S)
sql = re.sub(r'USE \w+;', '', sql)
sql = sql.replace('INT NOT NULL AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT')
sql = re.sub(r'ENUM\([^)]*\)', 'TEXT', sql).replace('TINYINT UNSIGNED', 'INTEGER')
sql = re.sub(r'\bTRUE\b', '1', sql)
sql = re.sub(r'\bFALSE\b', '0', sql)
db = DST + '/test.sqlite'
c = sqlite3.connect(db)
c.executescript(sql)
c.commit()
c.close()

open(DST + '/src/db.php', 'w').write('''<?php
class TDB extends PDO {
  public $t = false;
  public function prepare(string $q, array $o = []): PDOStatement|false { $q = preg_replace('/\\s+FOR UPDATE/i', '', $q); return parent::prepare($q, $o); }
  public function beginTransaction(): bool { $this->exec('BEGIN IMMEDIATE'); $this->t = true; return true; }
  public function commit(): bool { $this->exec('COMMIT'); $this->t = false; return true; }
  public function rollBack(): bool { $this->exec('ROLLBACK'); $this->t = false; return true; }
  public function inTransaction(): bool { return $this->t; }
}
function db(): PDO {
  static $p = null;
  if ($p === null) {
    $p = new TDB('sqlite:' . __DIR__ . '/../test.sqlite');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $p->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=10000');
  }
  return $p;
}
''')
shutil.copy(DST + '/src/config.example.php', DST + '/src/config.php')
print('Gotowe:', DST)
