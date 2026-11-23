import os
import sys, sqlite3, threading; sys.path.insert(0,os.path.dirname(os.path.abspath(__file__)))
from klient_http import *
DB=os.path.join(os.path.dirname(os.path.abspath(__file__)),'work','app','test.sqlite')
def q(sql,a=()):
    c=sqlite3.connect(DB); r=c.execute(sql,a).fetchall(); c.close(); return r
ok=bad=0
def chk(n,c,i=''):
    global ok,bad
    if c: ok+=1
    else: bad+=1; print('FAIL',n,i)
def login(e):
    c=C(); st,_,_=c.login(e); assert st==302,e; return c
def book(c,usl,prac,data,godz,doby=None,kom=''):
    d={'usluga':usl,'pracownik':prac,'data':data,'godzina':godz,'komentarz':kom}
    if doby: d['doby']=doby
    n0=q('select count(*) from rezerwacje')[0][0]
    st,b,h=c.post('rezerwacja.php',d,csrf_from='rezerwacja.php?usluga=%s'%usl)
    n1=q('select count(*) from rezerwacje')[0][0]
    return n1>n0,st,b
def msg(b):
    import re; m=re.findall(r'class="(?:alert|flash|err|blad)[^"]*"[^>]*>(.*?)<',b,re.S); return m[:2]
K1=login('Tomasz.Kuzicki@gmail.com'); K4=login('Karolina.Celin@gmail.com')
# hourly svc 29 (60 min) emp 1, Tue 2027-03-02
D='2027-03-02'
r,st,b=book(K1,29,1,D,'10:00'); chk('hourly 10:00 ok',r,(st,msg(b)))
for t,exp in [('09:30',False),('10:00',False),('10:15',False),('10:30',False),('09:00',True),('11:00',True)]:
    r,st,b=book(K4,29,1,D,t); chk(f'hourly {t} exp {exp}',r==exp,(r,msg(b)))
# svc 31 30min
r,st,b=book(K4,31,2,D,'09:00') if False else (None,0,'')
# client double-booking at same time with other employee (svc 32 emp 2 at 10:30 while K1 busy 10-11)
r,st,b=book(K1,32,2,D,'10:30'); chk('client self overlap blocked',not r,msg(b))
# outside working hours / break
r,st,b=book(K4,29,1,D,'12:00'); chk('break 12:00-13:00 crosses 12:00 end',not r)
r,st,b=book(K4,29,1,D,'07:30'); chk('before hours',not r)
r,st,b=book(K4,29,1,D,'17:30'); chk('after hours',not r)
# service not handled by employee: svc 29 emp 2
r,st,b=book(K4,29,2,D,'10:00'); chk('emp not assigned',not r)
# past date
r,st,b=book(K4,29,1,'2026-11-01','10:00'); chk('past',not r)
r,st,b=book(K4,29,1,'2030-03-02','10:00'); chk('too far',not r)
r,st,b=book(K4,29,1,'2027-02-30','10:00'); chk('bad date',not r)
r,st,b=book(K4,29,1,D,'25:99'); chk('bad time',not r)
r,st,b=book(K4,29,1,D,'10:07'); chk('off-grid 10:07 (15min step)',not r)
r,st,b=book(K4,999,1,D,'10:00'); chk('bad service',not r)
r,st,b=book(K4,29,1,D,'13:00',kom='x'*501); chk('long comment',not r)
# Sunday employee 1 (does not work)
r,st,b=book(K4,29,1,'2027-03-07','10:00'); chk('sunday',not r)
# rentals
R='2027-03-09'
r,st,b=book(K1,1,1,R,'10:00',2); chk('rental ok',r,msg(b))
rows=q("select data_rezerwacji,godzina_rozpoczecia,data_zakonczenia,liczba_dob,cena,status from rezerwacje where id_uslugi=1 and data_rezerwacji=?",(R,)); print(rows)
chk('rental price 1980',rows and rows[0][4]==1980 and rows[0][3]==2 and rows[0][2]=='2027-03-11')
r,st,b=book(K4,1,1,'2027-03-10','10:00',1); chk('car busy mid',not r)
r,st,b=book(K4,1,1,'2027-03-08','10:00',2); chk('car overlap start',not r)  # 8->10 10:00 return == pickup
r,st,b=book(K4,1,1,'2027-03-08','10:00',1); print('  return 03-09 10:00 == pickup 10:00:',r)
r,st,b=book(K4,1,1,'2027-03-11','11:00',1); chk('after return 11:00',r,msg(b))
# employee handover: different car by emp 1 at R 10:15 blocked; at 12:00 ok
r,st,b=book(K4,12,1,R,'10:15',1); chk('handover block',not r)
r,st,b=book(K4,12,1,R,'14:00',1); chk('emp free mid-rental',r,msg(b))
# doby invalid
for dd in ['0','31','-1','abc','1.5']:
    r,st,b=book(K4,2,1,'2027-04-06','10:00',dd); chk('doby '+repr(dd),not r)
# status check
print(q('select id,id_uslugi,data_rezerwacji,godzina_rozpoczecia,status from rezerwacje where id>10'))
# concurrency: 8 clients same car same time
import re
cl=[]
for i in range(8):
    c=C(); c.post('rejestracja.php',{'imie':'Jan','nazwisko':'Test'+'abcdefgh'[i],'email':f'par{i}@test.pl','telefon':'600100200','haslo':'Haslo123!x','haslo2':'Haslo123!x'}); st,_,_=c.login(f'par{i}@test.pl','Haslo123!x'); chk('reg login',st==302,st); cl.append(c)
tokens=[c.csrf('rezerwacja.php?usluga=2') for c in cl]
res=[]
def go(i):
    st,b,_=cl[i].req('rezerwacja.php',{'usluga':2,'pracownik':1,'data':'2027-05-04','godzina':'09:00','doby':2,'komentarz':'','csrf':tokens[i]}); res.append(st)
th=[threading.Thread(target=go,args=(i,)) for i in range(8)]
[t.start() for t in th]; [t.join() for t in th]
n=q("select count(*) from rezerwacje where id_uslugi=2 and data_rezerwacji='2027-05-04'")[0][0]; chk('concurrent same car only 1',n==1,n)
# concurrent hourly same slot
tokens=[c.csrf('rezerwacja.php?usluga=29') for c in cl]
th=[threading.Thread(target=lambda i=i: cl[i].req('rezerwacja.php',{'usluga':29,'pracownik':1,'data':'2027-05-05','godzina':'10:00','komentarz':'','csrf':tokens[i]})) for i in range(8)]
[t.start() for t in th]; [t.join() for t in th]
n=q("select count(*) from rezerwacje where id_uslugi=29 and data_rezerwacji='2027-05-05'")[0][0]; chk('concurrent same slot only 1',n==1,n)
# concurrent: employee handover race: 8 different cars same employee same time
tokens=[c.csrf('rezerwacja.php?usluga=3') for c in cl]
th=[threading.Thread(target=lambda i=i: cl[i].req('rezerwacja.php',{'usluga':3+i,'pracownik':1,'data':'2027-05-11','godzina':'10:00','doby':1,'komentarz':'','csrf':tokens[i]})) for i in range(8)]
[t.start() for t in th]; [t.join() for t in th]
n=q("select count(*) from rezerwacje where data_rezerwacji='2027-05-11'")[0][0]; chk('concurrent employee handover only 1',n==1,n)
print(ok,'ok',bad,'bad')
