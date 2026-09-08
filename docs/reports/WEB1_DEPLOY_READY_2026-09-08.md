# Preparação do código Web 1.0 — 08/09/2026

> Registro histórico anterior à RC3. Para a validação desta publicação, consulte [RC3_RELEASE_VALIDATION_2026-09-08.md](RC3_RELEASE_VALIDATION_2026-09-08.md).

Fonte de verdade: working tree atual, preservando as alterações anteriores. Release mantida em **1.0.0-rc.2**. Repositório preparado para revisão de Infra; isso não equivale a produção já homologada. Contrato operacional: [DEPLOY_DOKPLOY.md](../DEPLOY_DOKPLOY.md).

## A. Alterado no código

- Ambientes development/staging/production centralizados. Staging exige segurança de ambiente publicado, mantém sua própria origem e tem noindex por padrão. Erros detalhados somente development.
- APP_URL validada como origem, HTTPS obrigatório em staging/production; sem inferência de Host arbitrário. Google e integrações derivam callbacks dessa origem. Reset usa APP_URL; verificação de cadastro usa código, preservando o fluxo existente. Override legado Google divergente é rejeitado pelo checker.
- Removido redirect runtime da hospedagem anterior; robots/sitemap usam configuração. Script antigo de publicação agora recusa execução; documentos antigos explicitamente históricos. Referências históricas/changelog foram preservadas.
- `.env.example` consolidado; setup deriva dele sem sobrescrever configuração existente ou gerar credenciais. Adicionados/configurados DB_SSLMODE, TRUSTED_PROXIES, ROBOTS_NOINDEX, MAIL_TRANSPORT, SMTP_HOST/PORT/AUTH/USERNAME/PASSWORD/ENCRYPTION, endpoints opcionais existentes e parâmetros de runtime. FROM/FROM_NAME, versão/build, mapas, integrações, ads e doações continuam por env.
- Imagem PHP/Apache imutável com Composer no-dev otimizado, PDO PostgreSQL, mbstring, intl, curl e GD. Sem Node runtime. Document root `/public`; código root-owned, filesystem read-only no Compose publicado. Localhost mantém bind mount e banco local.
- `compose.dokploy.yaml` independente: app na porta interna 80, sem portas publicadas, job de migration e volume uploads. Banco externo ao Compose, configurável; Infra decide onde hospedá-lo e seu volume persistente.
- Uploads persistentes em `/var/www/html/public/uploads`: avatars, profile-banners e events. Cache de avatar regenerável acompanha volume. Importações/exports não exigem outro volume permanente. `/tmp` e runtime Apache em tmpfs; source não gravável.
- Proteção Apache fora do volume: scripts negados, inclusive extensão composta/case-insensitive; `.htaccess` enviado não altera regras; execução CGI/includes e symlinks desativados. Rewrite desativado especificamente em uploads para servir imagens válidas. Validações de MIME existentes preservadas.
- IP centralizado: REMOTE_ADDR por padrão; XFF somente de proxy confiável, validando cadeia e removendo hops confiáveis pela direita. X-Real-IP só é fallback sem XFF. Cabeçalhos malformados voltam ao REMOTE_ADDR. CIDRs IPv4/IPv6; confiança universal recusada.
- HTTPS forwarded somente de proxy confiável. Cookies antes de session_start, Secure em ambientes publicados/HTTPS, HttpOnly e SameSite=Lax. Local HTTP preservado.
- CSP e headers essenciais consolidados sem duplicação. Origins de anúncios condicionadas à configuração. HSTS/TLS/redirect HTTP→HTTPS ficam com Infra. Sem redirect de staging para production. Páginas privadas e staging com noindex.
- SMTP configurável via PHPMailer; certificados TLS validados. Transporte desativado por padrão local, `mail()` apenas opt-in development. Publicado exige SMTP configurado; erro de envio retorna falha e log genérico sem dados SMTP. Nenhum provider externo novo contratado.
- Health público mínimo (`health.php`), readiness com DB e registry (`ready.php`), respostas sem detalhes internos. Checker CLI distingue obrigatórios/opcionais, não imprime secrets; entrypoint rejeita configuração publicada incompleta e senha padrão.
- Runner de migrations com advisory lock, propagação de exit code e job separado antes do app. Release check inclui configuração e, em ambiente publicado, readiness/status sem pendências. Nenhum SQL ou nome histórico alterado; zero migrations novas.
- Service worker usa build no cache/registro, revalidação de assets e offline shell sem handler inline incompatível com CSP. Não armazena APIs, POST, uploads, autenticação ou páginas privadas. Não força reload de atividade em atualização. Fallback 100vh antes de 100dvh na tela offline.
- Endpoints OAuth opcionais exigem HTTPS para estado configurado; Garmin permanece indisponível. Ads/doações permanecem OFF por padrão. Sem credenciais opcionais, páginas continuam funcionando.

## B. Já estava pronto e foi preservado

- Manifest standalone, ícones reais 192/512/maskable/Apple, identidade visual e instalação PWA.
- GPS com Wake Lock progressivo, bloqueio dos controles da aplicação, desbloqueio deliberado, métricas ativas e recuperação local. Não é GPS nativo em background: bloquear telefone/suspender app pode interromper captura.
- Safe areas e correções anteriores de treino/agenda mobile; sem redesign desta rodada.
- CSRF, regras de autenticação/negócio e validações de uploads existentes.
- Seis migrations existentes, registry, estrutura de fresh/upgrade e suíte PostgreSQL.
- Mapa com token configurável/fallback, integrações opcionais, Garmin desativado e infraestrutura de ads/doações.
- Versão/build já configuráveis; RC preservada.

## C. Fica para Infra

Provisionamento real, roteamento, TLS/HTTPS/HSTS, secrets, redes confiáveis, DB persistente, ownership de volumes restaurados, backup/restore, migração real, entrega SMTP e Developer Portals. Validar staging antes de produção e depois smoke real. Nenhum desses serviços foi configurado nesta rodada.

Limites explícitos: teste físico de instalação iOS/Android, suspensão agressiva/GPS e home indicator reais ainda pertencem à homologação em aparelhos. WebKit automatizado não substitui iPhone antigo. Sessões PHP ficam em `/tmp`: recriar container pode exigir login novamente; contrato da 1.0 usa uma réplica web por ambiente. Não foi adicionado armazenamento distribuído.

## Testes e evidências

- `./scripts/test_static.sh`: verde, incluindo 60 asserts de deploy/config/proxy/URLs e cobertura i18n existente.
- `./scripts/release_check.sh --full`: verde com env local explícito; suíte PostgreSQL **11 grupos, 157 assertions, zero falhas**; seis migrations aplicadas, runner idempotente e sem pendências.
- Fresh install e upgrade PostgreSQL: verdes, sem migrations novas. Dois runners concorrentes no banco descartável: ambos concluíram; seis registros únicos. Comando inválido retorna exit não zero.
- Imagem efetivamente construída e iniciada em stack **descartável local**, sem bind mount: staging, source read-only, uploads persistentes após recriar app, scripts bloqueados e PNG servido. Paths `.env`, src, scripts, docs e Composer inacessíveis via web.
- HTTP real: cookies Secure/HttpOnly, noindex, origem staging, sem duplicação de headers; readiness 503 com registry pendente e 200 após runner. Configuração production simulada aprovada; senha obrigatória ausente impede inicialização; saída sem senha/token.
- SMTP STARTTLS com certificado local validado: três mensagens capturadas localmente, development/staging/production. Nenhum envio externo. URLs reais de fornecedores e entrega de verificação/reset em produção dependem da Infra.
- PWA em Chromium e WebKit: registro real, cache por build, manifest/ícones, ausência de cache privado e offline shell após queda real do servidor local de teste. Corrigida a simulação de rede do teste, sem relaxar CSP.
- Testes GPS: lock/Wake Lock 20 assertions; robustez 12; display mode PWA 12. Smoke UI signup/Home/settings preservado.
- Smoke autenticado localhost: login, Home, atividades, cronogramas, semana, GPS e Admin em 375/1440px. Conta administrativa temporária de teste removida ao final.
- `composer audit`: nenhuma vulnerabilidade reportada. Gitleaks redigido no histórico (71 commits) e working tree: achados classificados manualmente como falsos positivos entre variáveis vazias; nenhuma credencial real identificada. Arquivos locais privados preservados; sem reescrita de histórico.
- Sintaxe PHP/JS/shell/Python, Compose config e `git diff --check`: verdes.

Logs desta execução em `/tmp/stridebr-deploy-*`, incluindo `release-final.log`, `http-final.log`, `local-smoke.log`, `webkit.log`, `pwa-real.log`, `concurrency.log` e `composer-audit.log`. São evidências locais, não arquivos obrigatórios do produto. As screenshots das rodadas de treino/agenda continuam no relatório anterior; esta rodada não redesenhou essas telas.

## Arquivos desta rodada

Alguns já estavam modificados antes: a lista identifica participação nesta rodada, não autoria de todo o diff.

- Ambiente/build: `.env.example`, `.gitignore`, `.dockerignore`, `compose.yaml`, `compose.dokploy.yaml`, `Dockerfile`, `composer.json`, `composer.lock`, `docker/apache.conf`, `docker/php.ini`, `docker/app-entrypoint.sh`.
- Runtime: `src/includes/environment.php`, `configuration.php`, `http_headers.php`, `mail.php`, `app.php`, `auth.php`, `errors.php`, `i18n.php`; `src/config/pg_config.php`; `src/function/integrations.php`; `public/admin/diagnostics.php`.
- Web/PWA: `public/.htaccess`, `public/health.php`, `public/ready.php`, `public/robots.php` (substitui robots.txt), `public/offline.html`, `public/sw.js`, `public/assets/js/pwa.js`, `public/assets/js/offline.js`.
- Scripts: `setup_env.sh`, `config_check.php`, `migrate_product.sh`, `backup_db.sh`, `release_check.sh`, `deploy_alwaysdata.sh`, `test_static.sh`.
- Testes novos: `test_deploy_configuration.php`, `test_deploy_http.py`, `test_smtp_transport.py`, `browser_deploy_pwa.py`, `browser_deploy_local_smoke.py` em `scripts/tests/`.
- Testes adaptados ao contrato real: `test_auth.php`, `test_activity_edit_embed_static.php`, `test_production_theme_migrations_static.php`, `test_i18n_theme_google_static.php`, `test_ads_placements_static.php`, `test_pwa_foundation_static.php`, `test_web1_robustness_static.php`, `browser_pwa_display_mode.py`, `browser_gps_lock_wakelock.py`, `browser_gps_robustness.py`.
- Docs: `README.md`, `SECURITY.md`, `docs/README.md`, `DEPLOY_DOKPLOY.md`, `FINAL_SETUP_CHECKLIST.md`, `MIGRATIONS_CLI.md`, `GOOGLE_SIGNIN_SETUP.md`, `PERFORMANCE.md`, `MAPS.md`, banners históricos em `DEPLOY_ALWAYS_DATA.md`/`MIGRATIONS_PGADMIN.md` e este relatório.

## ENTREGAR PARA INFRA

- VPS e Dokploy; ambientes staging e production; DNS, domínio e TLS.
- Env/secrets; trusted proxy e headers sanitizados; HTTPS/HSTS.
- PostgreSQL, volumes, permissões e uploads persistentes.
- Migrations do artefato, backup/restore e migração real de dados/uploads.
- SMTP e teste de entrega; OAuth callbacks e integrações opcionais nos Developer Portals.
- Ads OFF até ativação configurada; doações conforme decisão/configuração.
- Smoke tests e homologação PWA/GPS física; readiness antes de tráfego; aprovação antes da versão/tag final.

**Confirmações:** não criou commit; não criou tag Git; não fez deploy; não mexeu no VPS; não alterou DNS; não migrou banco real; não migrou uploads reais; não inseriu credenciais reais; não promoveu para 1.0.0; não criou migration. Apenas bancos, mensagens SMTP e arquivos sintéticos locais foram usados nos testes.

## Revisão geral adicional

Revisão solicitada após a entrega: reconferidos código de configuração/proxy, Docker, upload security, cookies/headers, runner e logs originais das suítes completas. Encontrado e corrigido escopo Fitbit com espaços sem aspas no `.env.example`; teste estático agora verifica leitura real do template pelo shell. Runner resolve sua raiz e caminho de env explicitamente, permitindo execução fora do diretório do projeto. Testado a partir de `/tmp` com env temporário gerado pelo setup e status do PostgreSQL local: seis migrations aplicadas, nenhuma pendente. Removido bloco Apache vazio remanescente.

Estáticos novamente verdes, 60 asserts de configuração verdes, Compose válido, `git diff --check` limpo e localhost saudável. Logs: `/tmp/stridebr-general-review-static.log` e `/tmp/stridebr-general-review-env.log`. Sem nova alteração de schema ou ação em infraestrutura real.
