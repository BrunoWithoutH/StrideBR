# Validação final — StrideBR 1.0.0-rc.3 — 08/09/2026

Versão: **1.0.0-rc.3**. Fonte de verdade: working tree recebido, incluindo Produto/UX/PWA e preparação de deploy. Commit de release ainda não conhecido na preparação deste documento; o commit que contém este relatório identifica o artefato. Tag canônica prevista: `v1.0.0-rc.3`, após push de `main`. Este relatório não declara a 1.0 final homologada.

## Escopo auditado

Incluídos planejamento semanal, dashboard, progresso, contexto esportivo, cargas/sessões de treino, atividades/compartilhamento, GPS, PWA/offline, administração de feedback, estilos, traduções PT/EN, fixtures/testes e evidências visuais existentes. Incluídos Dockerfile, `docker/`, `compose.dokploy.yaml`, ambiente, SMTP, proxy, health/readiness, runner, backup, documentação e testes de deploy. A remoção de `public/robots.txt` acompanha sua substituição por `public/robots.php` e rewrite Apache.

A identidade corrente foi atualizada em `.env.example`, fallback de `stridebr_version()`, notas e checklist. As referências a RC2 nos relatórios `WEB1_DEPLOY_READY_2026-09-08.md` e `WEB1_DOKPLOY_AUDIT_2026-09-08.md` foram preservadas como registros históricos, com links para este documento. Referências à consolidação RC1 e à migration posterior permanecem. Não houve alteração de migrations, reset, descarte ou reescrita de histórico.

## Contrato de deploy verificado

- Git/GitHub fornece o código; Compose publicado: `compose.dokploy.yaml`.
- `.env` privado ao lado do Compose é o padrão; override manual continua suportado. Sem credenciais no source ou argumentos de build.
- Targets `app` e `migrations` construídos localmente. App read-only, sem bind mount de source e sem porta host; porta interna 80 e document root `/public`.
- PostgreSQL externo ao Compose de deploy; job dedicado precisa concluir antes do app. Somente a fixture local acrescentou PostgreSQL descartável e porta HTTP em loopback.
- Uploads em named volume; persistência verificada após recriação. Apache bloqueia execução e overrides nos uploads.
- Staging exige origem HTTPS, SMTP e suporte válido; noindex habilitado por padrão. Production validada com configuração fictícia separada.
- Proxy vazio ignora forwarded headers; confiança universal recusada. Reply-To válido usa suporte quando diferente do remetente.
- Integrações/ads/doações opcionais; sem configuração ficam indisponíveis/desligadas. Garmin continua indisponível.
- Deploy legado Alwaysdata continua encerrando imediatamente com erro; não foi executado.

## Execuções desta rodada

| Validação | Resultado observado |
| --- | --- |
| `git diff --check` | Passou. |
| `./scripts/test_static.sh` | Passou; sintaxe PHP/JS/shell, checks estáticos/unitários. Saída reporta 4.586 assertions em 50 resultados com contagem; outros checks aprovados não fornecem contagem. Inclui configuração de deploy: 78 assertions; i18n: 3.177 assertions e 3.099 keys por locale. |
| `STRIDEBR_ENV_FILE=<fixture temporária> ./scripts/release_check.sh --full` | Passou: estáticos, 11 grupos PostgreSQL, 157 assertions, 0 falhas e status sem pendências. |
| `./scripts/tests/test_migrations_clean.sh` | Passou: seis migrations em banco descartável, segunda aplicação idempotente e tabelas finais presentes. |
| `sh scripts/tests/test_migrations_upgrade.sh` | Passou: somente filename pendente aplicado, timestamps anteriores preservados e registry exato. |
| `./scripts/migrate_product.sh status` com fixture temporária | Seis aplicadas, nenhuma pendente no banco local descartável da RC3. |
| `python3 scripts/tests/test_deploy_compose.py` | Passou nos dois modos: `.env` padrão e override manual; ambiente chega a ambos os serviços, sem portas host ou bind de source. |
| `docker compose --env-file <temporário> -f compose.dokploy.yaml config --quiet` | Passou com valores fictícios e override do env_file; arquivo removido ao final. Configuração completa não foi impressa. |
| Build dos targets Docker | Passou para app e migrations; build final repetido após revisão do dockerignore. |
| `python3 scripts/tests/test_deploy_http.py` | Passou em stack local descartável: health/readiness 200, readiness 503 com migration ausente e recuperação após runner, cookies Secure/HttpOnly, headers sem duplicação, noindex, caminhos privados negados, uploads executáveis negados, persistência após recriação; production simulada, configuração obrigatória ausente recusada e falha do runner com exit code não zero. |
| `python3 scripts/tests/test_smtp_transport.py` | Passou: **15 mensagens** capturadas somente em servidor local, STARTTLS e certificado verificado, development/staging/production; Reply-To válido, vazio, inválido, igual ao remetente e tentativa de injeção de header. Sem request ao Resend. |
| Sintaxe Python via `ast.parse` | Passou em 51 arquivos de scripts, sem gerar artefatos no repositório. |
| `composer audit --locked --no-interaction` | Passou; nenhum aviso de vulnerabilidade encontrado. |
| `php scripts/tests/test_product_ux_round_static.php` | Passou: 29 assertions. |
| `php scripts/tests/test_progress_product_data.php` | Passou: 28 assertions. |
| `browser_deploy_pwa.py` com Chromium local | Passou: registro real do SW, cache por build, manifest/icons, nenhuma resposta privada em cache e tela offline. |
| `browser_deploy_local_smoke.py` com Chromium local | Passou: login, Home, atividades, cronogramas, semana, GPS e Admin autenticado em 375/1440px; fixture temporária removida pelo teste. |

Os testes de navegador usaram o Python/Playwright já disponível em `/tmp`; não houve instalação global. As matrizes visuais de rodadas anteriores não foram reexecutadas integralmente nesta rodada e continuam documentadas nos relatórios históricos.

### Intercorrências resolvidas no ambiente de teste

O sandbox inicialmente bloqueou subprocesso PHP iniciado pelo Node, Docker, socket SMTP e consulta do Composer. As execuções foram repetidas com acesso local autorizado e passaram. O primeiro release check completou a suíte PostgreSQL, mas falhou ao carregar pelo shell um valor sem aspas no `.env` privado. O arquivo privado foi preservado: a validação completa foi repetida com env temporário shell-compatible, transporte de e-mail desabilitado e banco PostgreSQL local descartável. Não é validação do `.env` real de staging.

A fixture antiga de deploy em `/tmp` estava sem `STRIDEBR_SUPPORT_EMAIL`; o entrypoint recusou corretamente a inicialização. Apenas a fixture recebeu suporte fictício válido e versão RC3; os testes HTTP passaram depois disso.

## Migrations preservadas

Registry local verificado com exatamente estes seis arquivos:

- `20260815_product_foundation.sql`
- `20260815_alpha_readiness.sql`
- `20260815_feedback_anonymous.sql`
- `20260815_fix_cronograma_delete_activity_trigger.sql`
- `20260903_v1_rc.sql`
- `20260903_z_activity_duration_precision_ms.sql`

Nenhum SQL existente foi editado e nenhuma migration foi criada. Fresh/upgrade/status referem-se exclusivamente a bancos locais de teste, não a Alwaysdata ou Dokploy.

## Auditoria de arquivos e secrets

`git ls-files .env` não retornou nada. Inspeção dos arquivos tracked/untracked, tipos, caminhos, diff e padrões de secrets não identificou credenciais privadas destinadas à release. Padrões de chaves privadas, tokens GitHub/AWS/Resend e URLs com credenciais não produziram achados. Ocorrências genéricas de password/secret/token foram classificadas como código, placeholders ou fixtures. A comparação silenciosa com valores sensíveis do dotenv encontrou somente a senha padrão local de desenvolvimento, já documentada e explicitamente recusada em ambientes publicados. Nenhum valor privado foi copiado para este relatório.

A revisão staged cobriu 238 caminhos (237 adições/modificações e a remoção de `public/robots.txt`), passou na auditoria de caminhos/padrões de secrets e em `git diff --cached --check` após remover uma linha vazia excedente no fim de `src/layout/trainer_as_athlete.php`; sua sintaxe PHP foi revalidada. Os arquivos binários da rodada são ícones PWA e capturas de UI de teste. Não foram incluídos `.env` real, dumps, ZIPs, logs, temporários, uploads reais, chaves, credenciais locais ou artefatos privados. `.gitignore` e `.dockerignore` revisados, com exclusões adicionais para ZIPs, temporários e backups; dumps SQL comprimidos também excluídos do build. Gitleaks não estava disponível; não foi instalado. A auditoria por padrões tem esse limite e não equivale a uma certificação absoluta de ausência de secrets.

## Pendências exclusivas de Infra/homologação

Deploy real no Dokploy, domínio staging, TLS/Traefik, lista real de proxies confiáveis, backup/restore real, migração/cutover do Alwaysdata, SMTP Resend real, OAuth/provider portals e homologação PWA em aparelhos físicos. Estas pendências permitem publicar a RC3 para staging, mas impedem declarar a 1.0 final pronta.

Nenhum deploy ou alteração de DNS, Cloudflare, Resend, banco real ou infraestrutura foi realizado. Próximo passo manual de Infra: versão `1.0.0-rc.3` → Preview Compose → revisar stack → domínio staging → primeiro deploy manual.
