#!/usr/bin/env python3
from pathlib import Path
import sys
try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print(f"Playwright indisponível: {exc}", file=sys.stderr); sys.exit(2)
ROOT=Path(__file__).resolve().parents[2]
assertions=0; failures=[]
def check(v,m):
    global assertions; assertions+=1
    if not v: raise AssertionError(m)
def parse(rgb): return [int(float(x)) for x in rgb.replace('rgba(','').replace('rgb(','').replace(')','').split(',')[:3]]
def lum(rgb):
    vals=parse(rgb)
    def f(c): c/=255; return c/12.92 if c<=.04045 else ((c+.055)/1.055)**2.4
    return .2126*f(vals[0])+.7152*f(vals[1])+.0722*f(vals[2])
def ratio(a,b):
    x,y=lum(a),lum(b); return (max(x,y)+.05)/(min(x,y)+.05)
HTML='''<!doctype html><html><body><dialog class="ui-confirm-overlay" open><button class="ui-confirm-backdrop"></button><div class="ui-confirm-dialog"><h2>Apagar atividade física</h2><div class="ui-confirm-copy"><p>Apagar “Corrida à noite”?</p><p>Se ela estiver vinculada a um treino do cronograma, esse treino volta a ficar pendente. Você poderá desfazer logo depois.</p></div><div class="ui-confirm-actions"><button class="ui-confirm-cancel">Cancelar</button><button class="ui-confirm-ok is-danger">Apagar</button></div></div></dialog></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for theme in ('light','dark'):
        page=browser.new_page(viewport={'width':900,'height':700})
        try:
            page.set_content(HTML)
            for css in ('style.css','ui-refresh.css'): page.add_style_tag(path=str(ROOT/'public/assets/css'/css))
            page.evaluate("t=>document.documentElement.dataset.theme=t",theme); page.wait_for_timeout(180)
            check(page.locator('.ui-confirm-copy p').count()==2,f'{theme}: dois blocos')
            check('\\n' not in page.locator('.ui-confirm-dialog').inner_text(),f'{theme}: newline literal')
            btn=page.locator('.ui-confirm-ok'); check(btn.inner_text()=='Apagar',f'{theme}: texto')
            st=btn.evaluate("e=>{let s=getComputedStyle(e);return [s.color,s.backgroundColor]}")
            check(ratio(*st)>=4.5,f'{theme}: contraste default {st}')
            btn.hover(); page.wait_for_timeout(150); st=btn.evaluate("e=>{let s=getComputedStyle(e);return [s.color,s.backgroundColor]}")
            check(ratio(*st)>=4.5,f'{theme}: contraste hover {st}')
            btn.focus(); check(btn.evaluate("e=>e.matches(':focus-visible')"),f'{theme}: focus')
        except Exception as e: failures.append(str(e))
        finally: page.close()
    browser.close()
if failures:
    print('Falhas delete confirm:',file=sys.stderr); [print('- '+x,file=sys.stderr) for x in failures]; sys.exit(1)
print(f'✓ activity delete confirm: {assertions} assertions; light/dark')
