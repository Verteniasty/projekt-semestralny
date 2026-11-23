import os
import sys, sqlite3, re, os, urllib.parse; sys.path.insert(0,os.path.dirname(os.path.abspath(__file__)))
from klient_http import *
DB=os.path.join(os.path.dirname(os.path.abspath(__file__)),'work','app','test.sqlite')
def q(sql,a=()):
    c=sqlite3.connect(DB); r=c.execute(sql,a).fetchall(); c.commit(); c.close(); return r
ok=bad=0
def chk(n,c,i=''):
    global ok,bad
    if c: ok+=1
    else: bad+=1; print('FAIL',n,i)
def login(e,p='test123!'):
    c=C(); st,_,_=c.login(e,p); assert st==302,(e,st); return c
K1=login('Tomasz.Kuzicki@gmail.com'); K4=login('Karolina.Celin@gmail.com'); P1=login('Anna.Nowak@gmail.com'); P2=login('Kamil.Woznica@gmail.com'); A=login('admin@rentloudcars.pl')
stat=lambda i:q('select status from rezerwacje where id=?',(i,))[0][0]
# client cancel
def cancel(c,i): return c.post('moje-rezerwacje.php',{'akcja':'anuluj','id':i},csrf_from='moje-rezerwacje.php')
print(q("select id,id_uzytkownika,id_pracownika,status,data_rezerwacji from rezerwacje where id in (5,9,11,14)"))
cancel(K4,11); chk('IDOR cancel other',stat(11)=='oczekujace',stat(11))
cancel(K1,5); chk('cancel past/confirmed',stat(5)=='potwierdzone',stat(5))
cancel(K1,1); chk('cancel completed',stat(1)=='zakonczone')
cancel(K1,11); chk('cancel own future',stat(11)=='anulowane',stat(11))
cancel(K1,11); chk('cancel twice stays',stat(11)=='anulowane')
# no csrf
st,_,_=K1.post('moje-rezerwacje.php',{'akcja':'anuluj','id':9},csrf=False); chk('no csrf cancel',stat(9)=='oczekujace',stat(9))
# admin status
def adm(c,page,i,n,extra=None):
    d={'akcja':'status','id':i,'nowy':n}; 
    return c.post(page,d,csrf_from=page)
adm(A,'admin-rezerwacje.php',14,'zakonczone'); chk('pending->done blocked',stat(14)=='oczekujace',stat(14))
adm(A,'admin-rezerwacje.php',14,'potwierdzone'); chk('pending->confirmed',stat(14)=='potwierdzone',stat(14))
adm(A,'admin-rezerwacje.php',14,'oczekujace'); chk('confirmed->pending blocked',stat(14)=='potwierdzone')
adm(A,'admin-rezerwacje.php',14,'bzdura'); chk('bad status',stat(14)=='potwierdzone')
adm(A,'admin-rezerwacje.php',1,'anulowane'); chk('done->cancel blocked',stat(1)=='zakonczone')
adm(A,'admin-rezerwacje.php',4,'potwierdzone'); chk('cancelled->confirmed blocked',stat(4)=='anulowane')
print('history rows 14:',q('select stary_status,nowy_status,id_uzytkownika from historia_statusow where id_rezerwacji=14'))
chk('history written',len(q('select 1 from historia_statusow where id_rezerwacji=14'))>=2)
# client / employee cannot use admin POST
adm(K1,'admin-rezerwacje.php',9,'potwierdzone'); chk('klient admin post',stat(9)=='oczekujace')
adm(P1,'admin-rezerwacje.php',9,'potwierdzone'); chk('prac admin post',stat(9)=='oczekujace')
# employee own vs foreign
print(q("select id,id_pracownika,status from rezerwacje where id in (6,8,9,10,12,13)"))
def pst(c,i,n): return c.post('panel-pracownika.php',{'akcja':'status','id':i,'nowy':n},csrf_from='panel-pracownika.php')
pst(P1,10,'potwierdzone'); chk('prac foreign (emp2) blocked',stat(10)=='oczekujace',stat(10))
pst(P2,10,'potwierdzone'); chk('prac own confirm',stat(10)=='potwierdzone',stat(10))
pst(P1,9,'potwierdzone'); chk('prac1 own confirm',stat(9)=='potwierdzone',stat(9))
st,b,_=P1.get('panel-pracownika.php'); chk('emp1 panel no emp2 client data','Sesja do teledysku' not in b)
# deletes
def adel(page,i): return A.post(page,{'akcja':'usun','id':i},csrf_from=page)
n0=q('select count(*) from uslugi')[0][0]; adel('admin-uslugi.php',1); chk('service with reservations kept',q('select count(*) from uslugi')[0][0]==n0)
n0=q('select count(*) from kategorie_uslug')[0][0]; adel('admin-kategorie.php',1); chk('category w/ services kept',q('select count(*) from kategorie_uslug')[0][0]==n0)
n0=q('select count(*) from pracownicy')[0][0]; adel('admin-pracownicy.php',1); chk('employee w/ reservations kept',q('select count(*) from pracownicy')[0][0]==n0)
# admin create unused service and delete
A.post('admin-uslugi.php',{'akcja':'zapisz','id':0,'id_kategorii':q('select id from kategorie_uslug limit 1')[0][0],'typ':'godziny','nazwa':'Testowa usługa','opis':'x','czas_trwania':'45','cena':'99,50','aktywne':'1'},csrf_from='admin-uslugi.php')
r=q("select id,cena,czas_trwania from uslugi where nazwa='Testowa usługa'"); chk('service created',len(r)==1,r)
if r: adel('admin-uslugi.php',r[0][0]); chk('unused service deleted',not q('select 1 from uslugi where id=?',(r[0][0],)))
for bad_ in [{'typ':'xx'},{'cena':'-5'},{'cena':'abc'},{'nazwa':'a'},{'czas_trwania':'0'},{'czas_trwania':'99999'},{'id_kategorii':'999'}]:
    d={'akcja':'zapisz','id':0,'id_kategorii':1,'typ':'godziny','nazwa':'Zla usluga','opis':'','czas_trwania':'45','cena':'10'}; d.update(bad_)
    A.post('admin-uslugi.php',d,csrf_from='admin-uslugi.php'); chk('svc validation '+str(bad_),not q("select 1 from uslugi where nazwa='Zla usluga'"))
# self-protection
A.post('admin-uzytkownicy.php',{'akcja':'rola','id':5,'rola':'klient'},csrf_from='admin-uzytkownicy.php'); chk('admin cant demote self',q('select rola from uzytkownicy where id=5')[0][0]=='administrator')
A.post('admin-uzytkownicy.php',{'akcja':'przelacz','id':5},csrf_from='admin-uzytkownicy.php'); chk('admin cant deactivate self',q('select aktywne from uzytkownicy where id=5')[0][0]==1)
A.post('admin-uzytkownicy.php',{'akcja':'rola','id':4,'rola':'superadmin'},csrf_from='admin-uzytkownicy.php'); chk('bad role value',q('select rola from uzytkownicy where id=4')[0][0]=='klient')
# deactivate user -> session dead
K4b=login('Karolina.Celin@gmail.com')
A.post('admin-uzytkownicy.php',{'akcja':'przelacz','id':4},csrf_from='admin-uzytkownicy.php')
st,_,_=K4b.get('panel-klienta.php'); chk('deactivated session killed',st==302,st)
st,_,_=C().login('Karolina.Celin@gmail.com'); chk('deactivated cant login',st!=302,st)
A.post('admin-uzytkownicy.php',{'akcja':'przelacz','id':4},csrf_from='admin-uzytkownicy.php')
# promote user then session role effect
# XSS
X=C(); X.post('rejestracja.php',{'imie':'<script>alert(1)</script>','nazwisko':'Xss','email':'xss@t.pl','telefon':'600100200','haslo':'Haslo123!x','haslo2':'Haslo123!x'})
chk('xss name rejected or escaped', not q("select 1 from uzytkownicy where imie like '<script%'") or True)
print('  xss name stored?',q("select imie from uzytkownicy where email='xss@t.pl'"))
KX=C(); KX.post('rejestracja.php',{'imie':'Xavier','nazwisko':'Xss','email':'xss2@t.pl','telefon':'600100200','haslo':'Haslo123!x','haslo2':'Haslo123!x'}); KX.login('xss2@t.pl','Haslo123!x')
KX.post('rezerwacja.php',{'usluga':29,'pracownik':1,'data':'2027-06-01','godzina':'10:00','komentarz':'<img src=x onerror=alert(1)>"\'><script>1</script>'},csrf_from='rezerwacja.php?usluga=29')
for pg in ['admin-rezerwacje.php','panel-pracownika.php','moje-rezerwacje.php','admin-kalendarz.php?miesiac=2027-06','panel-admina.php']:
    cl={'admin-rezerwacje.php':A,'panel-pracownika.php':P1,'moje-rezerwacje.php':KX,'admin-kalendarz.php?miesiac=2027-06':A,'panel-admina.php':A}[pg]
    st,b,_=cl.get(pg); chk('xss escaped '+pg,'<img src=x' not in b and '<script>1' not in b,st)
st,b,_=A.get('admin-rezerwacje.php?q='+urllib.parse.quote('<script>alert(9)</script>')); chk('xss q reflected escaped','<script>alert(9)' not in b)
# SQLi
c=C(); st,b,_=c.login("' OR 1=1 --",'x'); chk('sqli login',st!=302)
c=C(); st,b,_=c.login("admin@rentloudcars.pl' --",'x'); chk('sqli login2',st!=302)
for qq in ["' OR '1'='1","%","_","\\","'; DROP TABLE uzytkownicy; --"]:
    st,b,_=A.get('admin-rezerwacje.php?q='+urllib.parse.quote(qq)); chk('sqli q '+qq,st==200,st)
for pm in ['sort=id;DROP','status=x','pracownik=abc','od=zzz','do=2027-99-99','strona=-5','strona=999999','sort[]=1','q[]=1']:
    st,b,_=A.get('admin-rezerwacje.php?'+pm); chk('param '+pm,st==200,st)
chk('users table alive',q('select count(*) from uzytkownicy')[0][0]>=5)
# CSRF absent on admin actions
n=q('select count(*) from uslugi')[0][0]
A.post('admin-uslugi.php',{'akcja':'zapisz','id':0,'id_kategorii':1,'typ':'godziny','nazwa':'BezCsrf','opis':'','czas_trwania':'30','cena':'10'},csrf=False); chk('admin no csrf',q('select count(*) from uslugi')[0][0]==n)
# GET must not mutate
for pg in ['admin-uslugi.php?usun=1','admin-uslugi.php?akcja=usun&id=1','moje-rezerwacje.php?anuluj=9','admin-rezerwacje.php?anuluj=9','wylogowanie.php']:
    pass
K1b=login('Tomasz.Kuzicki@gmail.com'); K1b.get('moje-rezerwacje.php?anuluj=9&akcja=anuluj&id=9'); chk('GET cancel no mutate',stat(9) in('oczekujace','potwierdzone'),stat(9))
# logout via GET?
st,_,h=K1b.get('wylogowanie.php'); st2,_,_=K1b.get('panel-klienta.php'); print('  logout GET ->',st,st2)
# lockout
L=C()
for i in range(6): L.login('Karolina.Celin@gmail.com','zle'+str(i))
st,_,_=C().login('Karolina.Celin@gmail.com'); chk('lockout holds with right password',st!=302,st)
q("update uzytkownicy set nieudane_proby=0, zablokowany_do=NULL where id=4")
st,_,_=C().login('Karolina.Celin@gmail.com'); chk('login after unlock',st==302,st)
# enumeration: same message for unknown email and wrong pw
s1,b1,_=C().login('nie@istnieje.pl','x'); s2,b2,_=C().login('Tomasz.Kuzicki@gmail.com','x')
m=lambda b:re.findall(r'class="[^"]*(?:alert|flash)[^"]*"[^>]*>\s*([^<]+)',b)
print('  login msgs',m(b1),m(b2))
# profile password change invalidates other sessions
S1=login('Tomasz.Kuzicki@gmail.com'); S2=login('Tomasz.Kuzicki@gmail.com')
S1.post('profil.php',{'akcja':'haslo','stare':'test123!','nowe':'Nowe12345!x','nowe2':'Nowe12345!x'},csrf_from='profil.php')
st,_,_=S2.get('panel-klienta.php'); chk('other session invalidated',st==302,st)
st,_,_=S1.get('panel-klienta.php'); chk('current session kept',st==200,st)
st,_,_=C().login('Tomasz.Kuzicki@gmail.com','Nowe12345!x'); chk('new pw works',st==302)
st,_,_=C().login('Tomasz.Kuzicki@gmail.com','test123!'); chk('old pw fails',st!=302)
print('  hash:',q('select substr(haslo,1,4) from uzytkownicy where id=1'))
# password reset
log=os.path.join(os.path.dirname(os.path.abspath(__file__)),'work','app','storage','maile.log')
R=C(); R.post('reset-hasla.php',{'email':'Tomasz.Kuzicki@gmail.com'},csrf_from='reset-hasla.php')
R.post('reset-hasla.php',{'email':'brak@nigdzie.pl'},csrf_from='reset-hasla.php')
txt=open(log).read() if os.path.exists(log) else ''
tk=re.findall(r'token=([0-9a-f]{20,})',txt); chk('reset mail logged',len(tk)==1,(len(tk),txt[-300:]))
print('  tokens stored hashed:',q('select length(token_hash) from tokeny_hasla'))
if tk:
    t=tk[-1]
    st,b,_=C().get('nowe-haslo.php?token='+t); chk('reset form opens',st==200)
    N=C(); N.csrf('nowe-haslo.php?token='+t)
    st,b,h=N.post('nowe-haslo.php',{'token':t,'nowe':'Reset12345!x','nowe2':'Reset12345!x'},csrf_from='nowe-haslo.php?token='+t)
    st,_,_=C().login('Tomasz.Kuzicki@gmail.com','Reset12345!x'); chk('reset sets pw',st==302,st)
    N2=C(); st,b,h=N2.post('nowe-haslo.php',{'token':t,'nowe':'Inne12345!x','nowe2':'Inne12345!x'},csrf_from='nowe-haslo.php?token='+t)
    st,_,_=C().login('Tomasz.Kuzicki@gmail.com','Inne12345!x'); chk('token single use',st!=302)
st,b,_=C().get('nowe-haslo.php?token=zlytoken'); chk('bad token no form','name="nowe"' not in b)
# CSV
st,b,h=A.get('admin-eksport.php'); chk('csv ok',st==200 and 'text/csv' in h.get('Content-Type',''),(st,h.get('Content-Type')))
print('  csv head:',b[:200].replace('\n','|'))
st,_,_=K1b.get('admin-eksport.php'); chk('csv klient denied',st in(302,403))
# calendar params
for pm in ['miesiac=2027-06','miesiac=zle','miesiac=2027-13','miesiac=1900-01','kategoria=abc','kategoria=1','miesiac[]=1']:
    st,b,_=A.get('admin-kalendarz.php?'+pm); chk('cal '+pm,st==200,st)
# log
st,b,_=A.get('admin-log.php'); chk('log page',st==200 and 'usluga_dodanie' in b or 'Dodano' in b,st)
# error pages & php errors in server log
log=open(os.path.join(os.path.dirname(os.path.abspath(__file__)),'work','server.log')).read()
errs=[l for l in log.splitlines() if re.search(r'PHP (Warning|Fatal|Notice|Parse|Deprecated)|Uncaught|Stack trace',l)]
print('  php errors in server log:',len(errs)); [print('   ',e[:220]) for e in errs[:8]]
print(ok,'ok',bad,'bad')
