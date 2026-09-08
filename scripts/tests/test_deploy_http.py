"""Run only against the disposable local stack described by STRIDEBR_TEST_DEPLOY_ENV.
Tests never connect to a remote host and never print the private Compose env.
"""
import base64, os, subprocess, urllib.request, urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
envfile=os.environ.get('STRIDEBR_TEST_DEPLOY_ENV','/tmp/stridebr-deploy-fixture.env')
override=os.environ.get('STRIDEBR_TEST_DEPLOY_COMPOSE','/tmp/stridebr-deploy-fixture.compose.yaml')
assert envfile.startswith('/tmp/') and override.startswith('/tmp/')
compose=['docker','compose','--project-name','stridebr-deploy-test','--env-file',envfile,'-f',str(ROOT/'compose.dokploy.yaml'),'-f',override]
def php(code):
    return subprocess.run(compose+['exec','-T','app','php','-r',code],cwd=ROOT,capture_output=True,check=True)
def get(path):
    try:r=urllib.request.urlopen('http://127.0.0.1:18081'+path)
    except urllib.error.HTTPError as e:r=e
    return r.status,r.headers,r.read()
assert get('/health.php')[0::2]==(200,b'OK\n')
assert get('/ready.php')[0::2]==(200,b'READY\n')
status,headers,body=get('/login.php')
assert status==200 and b'Fatal error' not in body
assert 'secure' in headers['Set-Cookie'].lower() and 'httponly' in headers['Set-Cookie'].lower()
for name in ['Content-Security-Policy','X-Frame-Options','X-Content-Type-Options','Referrer-Policy','Permissions-Policy']:
    assert len(headers.get_all(name,[]))==1,(name,headers.get_all(name))
assert 'noindex' in headers['X-Robots-Tag']
assert 'Strict-Transport-Security' not in headers
assert 'doubleclick' not in headers['Content-Security-Policy']
assert b'Disallow: /\n' in get('/robots.txt')[2]
assert b'https://staging.stridebr.com.br' in get('/sitemap.xml')[2]
for path in ['/.env','/src/includes/env.php','/composer.json','/scripts/config_check.php','/docs/DEPLOY_DOKPLOY.md']:
    assert get(path)[0] in [403,404],path
php("$p='/var/www/html/src/.deploy-readonly-probe'; if (@file_put_contents($p,'probe')!==false) {unlink($p);exit(1);}")
files=['deploy-fixture.php','deploy-fixture.PHP','deploy-fixture.phtml','deploy-fixture.phar','deploy-fixture.cgi','deploy-fixture.sh','deploy-fixture.php.jpg']
for file in files:php("file_put_contents('/var/www/html/public/uploads/"+file+"','<?php echo \"UNSAFE_EXEC\";');")
php("file_put_contents('/var/www/html/public/uploads/.htaccess',\"Require all granted\\n\");")
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP9sAAAAASUVORK5CYII=')
php("file_put_contents('/var/www/html/public/uploads/deploy-fixture.png',base64_decode('"+base64.b64encode(png).decode()+"'));")
for file in files:
    status,_,body=get('/uploads/'+file)
    assert status==403 and b'UNSAFE_EXEC' not in body,file
assert get('/uploads/.htaccess')[0]==403
assert get('/uploads/deploy-fixture.png')[2]==png
subprocess.run(compose+['up','-d','--no-deps','--force-recreate','app'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
import time
for _ in range(30):
    try:
        if get('/health.php')[0]==200:break
    except Exception:pass
    time.sleep(.2)
assert get('/uploads/deploy-fixture.png')[2]==png
assert get('/ready.php')[0]==200
# A missing registry entry must make readiness fail; runner restores only fixture DB.
subprocess.run(compose+['exec','-T','fixturedb','psql','-U','fixture','-d','stridebr_deploy_test','-c',"DELETE FROM public.stridebr_schema_migrations WHERE version = '20260903_z_activity_duration_precision_ms.sql'"],check=True,stdout=subprocess.DEVNULL)
assert get('/ready.php')[0]==503
subprocess.run(compose+['run','--rm','migrate','apply'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
assert get('/ready.php')[0]==200
# Validate production startup with fixture credentials, never contacting its APP_URL.
production=['run','--rm','--no-deps','-e','STRIDEBR_APP_ENV=production','-e','STRIDEBR_APP_URL=https://stridebr.com.br']
result=subprocess.run(compose+production+['app','php','scripts/config_check.php','--database'],capture_output=True)
assert result.returncode==0 and b'READINESS: ready' in result.stdout,result.stdout
missing=subprocess.run(compose+production+['-e','STRIDEBR_DB_PASSWORD=','app','php','scripts/config_check.php'],capture_output=True)
assert missing.returncode!=0 and b'DB' in missing.stdout
assert b'fixture-only-' not in result.stdout+result.stderr+missing.stdout+missing.stderr
invalid=subprocess.run(compose+['run','--rm','migrate','invalid-command'],capture_output=True)
assert invalid.returncode!=0
print('PASS immutable staging HTTP: Secure cookies, no duplicate headers, private paths denied, upload execution denied, persistence after recreation, readiness pending detection')
print('PASS production configuration, missing required startup rejection, no secret output and migration failure exit code')
