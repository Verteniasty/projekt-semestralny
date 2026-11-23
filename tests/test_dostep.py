import os
import sys; sys.path.insert(0,os.path.dirname(os.path.abspath(__file__)))
from klient_http import *
ok=bad=0
def chk(name,cond,info=''):
    global ok,bad
    if cond: ok+=1
    else: bad+=1; print('FAIL',name,info)
USERS={'klient':'Tomasz.Kuzicki@gmail.com','prac':'Anna.Nowak@gmail.com','adm':'admin@rentloudcars.pl'}
def sess(k):
    c=C(); 
    if k: 
        st,b,h=c.login(USERS[k]); chk('login '+k,st==302,(st,b[:200]))
    return c
PUB=['Strona-glowna.php','Garaz.php','O-nas.php','Kontakt.php','oferta.php','logowanie.php','rejestracja.php','reset-hasla.php']
ADM=['panel-admina.php','admin-rezerwacje.php','admin-uslugi.php','admin-kategorie.php','admin-pracownicy.php','admin-uzytkownicy.php','admin-dostepnosc.php?id=1','admin-log.php','admin-kalendarz.php','admin-eksport.php']
CL=['moje-rezerwacje.php','panel-klienta.php','rezerwacja.php','profil.php']
PR=['panel-pracownika.php']
G=sess(None);K=sess('klient');P=sess('prac');A=sess('adm')
for p in PUB:
    st,b,_=G.get(p); chk('pub '+p,st==200,st)
    st,b,_=K.get(p); chk('pub-klient '+p,st in (200,302),st)
for p in ADM+CL+PR:
    st,_,h=G.get(p); chk('guest '+p,st==302 and 'logowanie' in h.get('Location',''),(st,h.get('Location')))
for p in ADM:
    st,_,h=K.get(p); chk('klient->adm '+p,st in(403,302),st)
    st,_,h=P.get(p); chk('prac->adm '+p,st in(403,302),st)
    st,b,h=A.get(p); chk('adm '+p,st==200,(st,b[:300]))
for p in PR:
    st,_,_=K.get(p); chk('klient->prac',st in(403,302),st)
    st,_,_=A.get(p); chk('adm->prac',st in(403,302),st)
    st,b,_=P.get(p); chk('prac '+p,st==200,(st,b[:300]))
for p in CL:
    st,b,h=K.get(p); chk('klient '+p,st==200,(st,b[:300]))
    st,_,_=P.get(p); chk('prac->'+p,st in(403,302) or p=='profil.php',st)
    st,_,_=A.get(p); print('  adm->',p,st)
st,b,_=G.get('api-sloty.php?usluga=1&pracownik=1&data=2027-03-02'); chk('api guest 401',st==401,st)
st,b,_=P.get('api-sloty.php?usluga=1&pracownik=1&data=2027-03-02'); chk('api prac 403',st==403,st)
st,b,_=K.get('api-sloty.php?usluga=1&pracownik=1&data=zla'); chk('api bad 400',st==400,st)
# nonexistent pages / source protection
for p in ['../src/config.php','../database/database.sql','../storage/maile.log']:
    st,_,_=G.get(p); chk('protect '+p,st in(403,404),st)
for p in ['src/config.php','database/database.sql','storage/maile.log']:
    st,_,_=G.get(p); chk('nonpublic '+p,st==404,st)
# headers
st,b,h=G.get('Strona-glowna.php'); chk('csp','Content-Security-Policy' in h); chk('inline script', not re.search(r'<script(?![^>]*src)[^>]*>',b)); chk('inline style',' style="' not in b)
print(ok,'ok',bad,'bad')
# --- filtry oferty i widoki
import re as _re
st,b,_=G.get('oferta.php'); n_all=len(_re.findall(r'<article class="card oferta-karta">',b)); chk('oferta all 32',n_all==32,n_all)
st,b,_=G.get('oferta.php?kategoria=1'); n1=len(_re.findall(r'<article class="card oferta-karta">',b)); chk('filtr kategorii',0<n1<n_all,n1)
st,b,_=G.get('oferta.php?typ=godziny'); n2=len(_re.findall(r'<article class="card oferta-karta">',b)); chk('filtr rodzaju',n2==4,n2)
st,b,_=G.get('oferta.php?typ=doby&data=2027-03-09&dostepne=1'); n3=len(_re.findall(r'<article class="card oferta-karta">',b)); chk('filtr dostepnosc',st==200 and 0<n3<=28,(st,n3)); chk('badge wolne','Wolne 09.03.2027' in b)
st,b,_=G.get('oferta.php?kategoria=abc&typ=x&data=zla&dostepne=1'); chk('filtry zle wartosci',st==200)
st,b,_=G.get('oferta.php?kategoria[]=1&data[]=1'); chk('filtry tablice',st==200,st)
st,b,_=G.get('oferta.php?data=1999-01-01'); chk('data przeszla ignorowana','Wolne 01' not in b and 'Zajęte 01' not in b)
import os as _os
_log=_os.path.join(_os.path.dirname(_os.path.abspath(__file__)),'work','app','storage','maile.log')
c=C(); c.post('rejestracja.php',{'imie':'Mail','nazwisko':'Test','email':'mail1@test.pl','telefon':'600100200','haslo':'Haslo123!x','haslo2':'Haslo123!x'})
chk('mail powitalny',_os.path.exists(_log) and 'Witaj w RentLoudCars' in open(_log).read())
print(ok,'ok',bad,'bad')
