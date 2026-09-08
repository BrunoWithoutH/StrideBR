from playwright.sync_api import sync_playwright, expect
import os, threading, urllib.request, urllib.error
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
# A local reverse proxy lets the browser experience a real network outage.
# WebKit's context.set_offline can abort navigation before consulting its SW.
class LocalApp(BaseHTTPRequestHandler):
    offline = False
    def log_message(self, *args): pass
    def do_GET(self):
        if self.offline:
            self.close_connection = True
            return
        try:
            response = urllib.request.urlopen('http://localhost:8080' + self.path)
        except urllib.error.HTTPError as error:
            response = error
        self.send_response(response.status)
        for name, value in response.headers.items():
            if name.lower() not in ['transfer-encoding', 'connection', 'content-length']:
                self.send_header(name, value)
        body = response.read()
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)
server = ThreadingHTTPServer(('127.0.0.1', 0), LocalApp)
threading.Thread(target=server.serve_forever, daemon=True).start()
base = 'http://127.0.0.1:' + str(server.server_port)
with sync_playwright() as p:
    browser=getattr(p, os.environ.get('STRIDEBR_TEST_BROWSER', 'chromium')).launch(headless=True)
    context=browser.new_context(viewport={'width':375,'height':812})
    page=context.new_page();errors=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(base + '/login.php')
    for _ in range(150):
        if page.evaluate('navigator.serviceWorker.controller !== null'): break
        page.wait_for_timeout(100)
    assert page.evaluate('navigator.serviceWorker.controller !== null')
    assert page.evaluate("navigator.serviceWorker.controller.scriptURL.includes('/sw.js?build=')")
    manifest=page.request.get(base + '/manifest.webmanifest').json()
    assert manifest['display']=='standalone' and manifest['scope']=='/'
    for icon in manifest['icons']:assert page.request.get(base + icon['src']).ok
    await_cache=page.evaluate("async()=>{const keys=await caches.keys();return (await Promise.all(keys.map(async key=>(await (await caches.open(key)).keys()).map(r=>new URL(r.url).pathname)))).flat()}")
    assert all(url.startswith('/assets/') or url in ['/offline.html','/manifest.webmanifest'] for url in await_cache),await_cache
    LocalApp.offline = True
    page.goto(base + '/home.php')
    expect(page.locator('h1')).to_have_text('Sem conexão')
    assert page.locator('[data-offline-retry]').is_visible()
    assert not errors,errors
    browser.close()
server.shutdown()
print('PASS PWA real registration, build-scoped cache, manifest/icons, no private cached responses and offline shell')
