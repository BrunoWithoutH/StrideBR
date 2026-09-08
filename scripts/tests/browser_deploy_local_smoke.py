"""Smoke the normal localhost stack using a temporary test-only administrator."""
import subprocess, uuid
from playwright.sync_api import sync_playwright
fixture='ds_smoke_'+uuid.uuid4().hex[:12]
def php(code):
    guard="require 'src/includes/app.php'; require 'src/config/pg_config.php'; if (!stridebr_is_development() || getenv('STRIDEBR_DB_HOST') !== 'postgres') exit(2); "
    subprocess.run(['docker','exec','-i','stridebr-app','php','-r',guard+code],check=True,capture_output=True)
php("$s=$pdo->prepare(\"INSERT INTO usuarios (idusuario,nomeusuario,nome_exibicao,emailusuario,senhausuario,username,statususuario,onboarding_concluido,papelusuario) VALUES (:id,'Deploy fixture','Deploy fixture',:email,:password,:username,'Ativo',TRUE,'admin')\"); $s->execute(['id'=>'"+fixture+"','email'=>'"+fixture+"@alpha-test.invalid','password'=>stridebr_password_hash('Deploy-fixture-123!'),'username'=>'"+fixture+"']);")
try:
    with sync_playwright() as p:
        browser=p.chromium.launch(headless=True)
        page=browser.new_page(viewport={'width':375,'height':812})
        errors=[]
        page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto('http://localhost:8080/login.php')
        page.locator('[name=UEmail]').fill(fixture+'@alpha-test.invalid')
        page.locator('[name=USenha]').fill('Deploy-fixture-123!')
        page.locator('button[name=submit]').click()
        page.wait_for_url('**/home.php')
        for width in [375,1440]:
            page.set_viewport_size({'width':width,'height':900})
            for route in ['/home.php','/user/atividades.php','/user/cronogramatreinos.php','/calendario.php?view=week','/user/gravar-atividade.php','/admin/index.php']:
                response=page.goto('http://localhost:8080'+route)
                assert response.status==200,(route,response.status)
                assert '/login.php' not in page.url
                assert not page.locator('body').get_by_text('Fatal error',exact=False).count()
                assert page.locator('main').count()>0,route
        assert not errors,errors
        browser.close()
    print('PASS localhost login, Home, activities, schedules, week, GPS and authenticated Admin at 375/1440px')
finally:
    php("$pdo->prepare('DELETE FROM usuarios WHERE idusuario = :id')->execute(['id'=>'"+fixture+"']);")
