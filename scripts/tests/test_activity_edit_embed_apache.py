#!/usr/bin/env python3
from pathlib import Path
import shutil, subprocess, tempfile, time, sys, urllib.request
ROOT=Path(__file__).resolve().parents[2]
APACHE=shutil.which('apache2') or shutil.which('httpd')
if not APACHE:
    print('Apache indisponível', file=sys.stderr); sys.exit(2)
assertions=0
def check(v,m):
    global assertions; assertions+=1
    if not v: raise AssertionError(m)
with tempfile.TemporaryDirectory(prefix='stridebr-frame-') as td:
    d=Path(td); (d/'user').mkdir(); (d/'function').mkdir(); (d/'errors').mkdir()
    (d/'.htaccess').write_text((ROOT/'public/.htaccess').read_text())
    (d/'user/editatividade.php').write_text('ok'); (d/'function/apagaratividade.php').write_text('ok')
    for c in ('403','404','500'): (d/f'errors/{c}.php').write_text('err')
    conf=d/'apache.conf'; log=d/'error.log'
    conf.write_text(f'''ServerRoot "/etc/apache2"\nPidFile {d/'apache.pid'}\nListen 127.0.0.1:18080\nLoadModule mpm_event_module /usr/lib/apache2/modules/mod_mpm_event.so\nLoadModule authz_core_module /usr/lib/apache2/modules/mod_authz_core.so\nLoadModule headers_module /usr/lib/apache2/modules/mod_headers.so\nLoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so\nLoadModule setenvif_module /usr/lib/apache2/modules/mod_setenvif.so\nLoadModule mime_module /usr/lib/apache2/modules/mod_mime.so\nTypesConfig /etc/mime.types\nServerName localhost\nErrorLog {log}\nDocumentRoot "{d}"\n<Directory "{d}">\n AllowOverride All\n Require all granted\n</Directory>\n''')
    subprocess.run([APACHE,'-t','-f',str(conf)],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    proc=subprocess.Popen([APACHE,'-f',str(conf),'-DFOREGROUND'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        time.sleep(.35)
        def headers(url):
            req=urllib.request.Request(url,method='HEAD')
            with urllib.request.urlopen(req,timeout=3) as r: return {k.lower():v for k,v in r.headers.items()}
        normal=headers('http://127.0.0.1:18080/user/editatividade.php?id=1')
        embed=headers('http://127.0.0.1:18080/user/editatividade.php?id=1&embed=1')
        check(normal.get('x-frame-options')=='DENY','normal XFO')
        check("frame-ancestors 'none'" in normal.get('content-security-policy',''),'normal CSP')
        check(embed.get('x-frame-options')=='SAMEORIGIN','embed XFO')
        check("frame-ancestors 'self'" in embed.get('content-security-policy',''),'embed CSP')
        check('*' not in embed.get('x-frame-options',''),'embed não abriu wildcard XFO')
        check("frame-ancestors *" not in embed.get('content-security-policy',''),'embed não abriu wildcard CSP')
        script_src=embed.get('content-security-policy','').split('script-src ',1)[1].split(';',1)[0] if 'script-src ' in embed.get('content-security-policy','') else ''
        check("'unsafe-inline'" not in script_src,'embed liberou inline script')
        delete_embed=headers('http://127.0.0.1:18080/function/apagaratividade.php?embed=1')
        check(delete_embed.get('x-frame-options')=='SAMEORIGIN','delete embed XFO')
        check("frame-ancestors 'self'" in delete_embed.get('content-security-policy',''),'delete embed CSP')
    finally:
        proc.terminate(); proc.wait(timeout=3)
print(f'✓ activity edit embed apache: {assertions} assertions; scoped headers')
