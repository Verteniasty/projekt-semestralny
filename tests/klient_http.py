import urllib.request, urllib.parse, http.cookiejar, re, json
B='http://127.0.0.1:8099/'
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*a,**k): return None
class C:
    def __init__(s):
        s.jar=http.cookiejar.CookieJar()
        s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.jar),NoRedir)
    def req(s,path,data=None,headers=None):
        url=path if path.startswith('http') else B+path
        d=urllib.parse.urlencode(data).encode() if data is not None else None
        r=urllib.request.Request(url,data=d,headers=headers or {})
        try: resp=s.op.open(r,timeout=30)
        except urllib.error.HTTPError as e: resp=e
        body=resp.read().decode('utf8','replace')
        return resp.status, body, dict(resp.headers)
    def get(s,p): return s.req(p)
    def csrf(s,page='Strona-glowna.php'):
        st,b,_=s.get(page)
        m=re.search(r'name="csrf" value="([0-9a-f]+)"',b)
        if not m:
            for p in ('logowanie.php','profil.php','oferta.php'):
                st,b,_=s.get(p); m=re.search(r'name="csrf" value="([0-9a-f]+)"',b)
                if m: break
        return m.group(1) if m else ''
    def post(s,p,data,csrf_from=None,csrf=True):
        d=dict(data)
        if csrf: d['csrf']=s.csrf(csrf_from or p)
        return s.req(p,d)
    def login(s,email,pw='test123!'):
        return s.post('logowanie.php',{'email':email,'haslo':pw})
