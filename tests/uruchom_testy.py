"""Uruchamia kolejno wszystkie testy (serwer php -S musi działać na porcie 8099)."""
import os, subprocess, sys
here = os.path.dirname(os.path.abspath(__file__))
kod = 0
for t in ('test_dostep.py', 'test_rezerwacje.py', 'test_bezpieczenstwo.py'):
    print('==', t)
    kod |= subprocess.call([sys.executable, os.path.join(here, t)])
sys.exit(kod)
