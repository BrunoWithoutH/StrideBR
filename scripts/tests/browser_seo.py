"""Read-only HTTP checks against the local application; no external crawler requests."""
import json
import os
import urllib.request
import urllib.error
import xml.etree.ElementTree as ET
from html.parser import HTMLParser
BASE = os.environ.get('STRIDEBR_TEST_BASE_URL','http://localhost:8080')
class Document(HTMLParser):
    def __init__(self, html):
        super().__init__(); self.metas={}; self.links={}; self.titles=[]; self.jsonld=[]; self.h1=0; self.main=0; self.current=None; self.buffer=''; self.feed(html)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='meta': self.metas.setdefault(a.get('name',a.get('property','')),[]).append(a.get('content',''))
        if tag=='link': self.links.setdefault(a.get('rel',''),[]).append(a.get('href',''))
        if tag=='title' or (tag=='script' and a.get('type')=='application/ld+json'): self.current=tag; self.buffer=''
        if tag=='h1': self.h1+=1
        if tag=='main': self.main+=1
    def handle_data(self,data):
        if self.current: self.buffer+=data
    def handle_endtag(self,tag):
        if tag==self.current:
            if tag=='title': self.titles.append(self.buffer)
            else: self.jsonld.append(json.loads(self.buffer))
            self.current=None
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args): return None
opener=urllib.request.build_opener(NoRedirect)
def request(path):
    try: response=opener.open(BASE+path, timeout=20)
    except urllib.error.HTTPError as e: response=e
    return response.status, response.headers, response.read().decode('utf-8')
checks=0
status,headers,html=request('/?utm_source=fixture')
assert status==200
home=Document(html)
assert home.titles==['StrideBR — Treinos, atividades e evolução esportiva'];checks+=1
assert 'plataforma brasileira' in home.metas['description'][0];checks+=1
assert home.links['canonical']==['https://stridebr.com.br/'];checks+=1
assert home.h1==1 and home.main==1;checks+=1
assert [x['@type'] for x in home.jsonld[0]['@graph']]==['Organization','WebSite'];checks+=1
assert 'noindex' not in headers.get('X-Robots-Tag','');checks+=1
status,headers,xml=request('/sitemap.xml')
assert status==200 and 'xml' in headers['Content-Type'];checks+=1
urls=[x.text for x in ET.fromstring(xml).findall('{*}url/{*}loc')]
assert len(urls)>=14;checks+=1
for url in urls:
    assert url.startswith('https://stridebr.com.br/');checks+=1
    assert not any(part in url for part in ['/user/','/auth/','/admin/','/api/','/home.php','/login','/signup','/u/']);checks+=1
    path=url.removeprefix('https://stridebr.com.br')
    status,headers,html=request(path)
    assert status==200,(path,status);checks+=1
    doc=Document(html)
    assert doc.links.get('canonical')==[url],(path,doc.links);checks+=1
    assert len(doc.titles)==1 and doc.h1==1 and doc.main==1,(path,doc.h1,doc.main);checks+=1
    for key in ['description','robots','og:title','og:description','og:url','og:image','og:site_name','twitter:card']:
        assert len(doc.metas.get(key,[]))==1,(path,key);checks+=1
    assert 'noindex' not in doc.metas['robots'][0],path;checks+=1
    assert doc.metas['og:url']==[url];checks+=1
    assert doc.metas['og:image'][0].startswith('https://stridebr.com.br/');checks+=1
status,headers,robots=request('/robots.txt')
assert status==200 and 'Sitemap: https://stridebr.com.br/sitemap.xml' in robots;checks+=1
assert 'Disallow: /user/' in robots and 'Disallow: /assets' not in robots;checks+=1
for path in ['/missing-seo-fixture','/errors/404.php','/evento.php?e=missing-seo-fixture']:
    status,headers,html=request(path); doc=Document(html)
    assert status==404,(path,status);checks+=1
    assert 'noindex' in headers.get('X-Robots-Tag','') and 'noindex' in doc.metas['robots'][0];checks+=1
    assert not doc.links.get('canonical');checks+=1
for path in ['/login.php','/signup.php','/forgot-password.php','/reset-password.php','/home.php','/user/settings.php','/admin/index.php','/auth/integration.php','/api/not-found','/accept-legal.php','/resend-verification.php']:
    status,headers,html=request(path)
    assert 'noindex' in headers.get('X-Robots-Tag',''),(path,status,dict(headers));checks+=1
status,headers,html=request('/calendario.php?lang=en&utm_source=fixture')
doc=Document(html)
assert doc.metas['og:locale']==['en_US'] and doc.links['canonical']==['https://stridebr.com.br/calendario.php'];checks+=1
print(f'PASS SEO HTTP: {checks} checks, {len(urls)} public sitemap URLs')

# Exercise environment-dependent HTML and robots via a separate loopback PHP server.
import subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
server=subprocess.Popen(['php','-S','127.0.0.1:8098','-t',str(ROOT/'public'),str(ROOT/'scripts/tests/seo_http_fixture.php')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
    import time
    original_base=BASE; BASE='http://127.0.0.1:8098'
    for attempt in range(40):
        try:
            status,headers,html=request('/__seo/verified/'); break
        except urllib.error.URLError: time.sleep(.05)
    doc=Document(html)
    assert doc.metas['google-site-verification']==['synthetic-google"><b>']
    assert doc.metas['msvalidate.01']==['synthetic-bing']
    status,headers,html=request('/__seo/plain/')
    doc=Document(html)
    assert 'google-site-verification' not in doc.metas and 'msvalidate.01' not in doc.metas
    status,headers,html=request('/__seo/staging/')
    assert 'noindex' in headers.get('X-Robots-Tag','') and 'noindex' in Document(html).metas['robots'][0]
    status,headers,robots=request('/__seo/staging/robots.txt')
    assert robots=='User-agent: *\nDisallow: /\n'
    status,headers,xml=request('/__seo/staging/sitemap.xml')
    assert not ET.fromstring(xml).findall('{*}url')
    print('PASS SEO environment HTTP: 6 checks (verification escaping/absence, staging HTML/robots/sitemap)')
finally:
    server.terminate(); server.wait(timeout=10)
