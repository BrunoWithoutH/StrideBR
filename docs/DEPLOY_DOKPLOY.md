# StrideBR Web 1.0 — contrato para Infra

O repositório continua RC. Desenvolvimento ocorre em localhost, seguido por Git/GitHub → staging Dokploy → validação → production. Não editar código no VPS. Este documento não configura infraestrutura.

## Imagem e ambientes

- `compose.yaml`: desenvolvimento, bind mount local, porta 8080 e PostgreSQL local persistente. `./scripts/setup_env.sh` cria `.env` sem sobrescrever arquivo existente. `docker compose up --build -d` mantém o fluxo habitual.
- `compose.dokploy.yaml`: stack independente, sem bind mount do código nem portas publicadas no host; serviço `app` expõe 80 apenas na rede. Infra associa a rede/roteamento do proxy ao serviço depois.
- Dockerfile: targets `app` (PHP 8.4/Apache, código e Composer `--no-dev`) e `migrations` (PostgreSQL client 17, runner e SQL). Não há Node runtime/build obrigatório. Extensões usadas: PDO PostgreSQL, mbstring, intl/Normalizer, curl, GD para reencodar imagens; openssl/fileinfo/XML presentes na base PHP.
- Document root: `/var/www/html/public`. Código permanece root-owned; app tem filesystem read-only no Compose publicado. Apache `headers` e `rewrite` habilitados.
- `STRIDEBR_APP_ENV`: somente development/staging/production. Staging tem segurança de ambiente publicado, mas não é produção oficial. Erros detalhados só em development; logs no stderr, UI controlada nos demais.
- `STRIDEBR_APP_URL`: origem absoluta sem path/query/credenciais. Development aceita HTTP; staging/production exigem HTTPS. URLs públicas, reset e callbacks não usam Host arbitrário. Exemplos: `http://localhost:8080`, `https://staging.stridebr.com.br`, `https://stridebr.com.br`.
- `STRIDEBR_VERSION` e `STRIDEBR_BUILD` são env. Preservar RC; preencher build por artefato, sem editar source.

## Configuração

`.env.example` é o contrato mínimo. O arquivo real permanece privado, fora do Git/imagem. `setup_env.sh --staging|--production --url https://ORIGEM` cria somente template sem credenciais, sem modificar arquivo existente.

No Dokploy, preencher **Environment** com as variáveis privadas de cada ambiente. O Dokploy salva esses valores em `.env` ao lado do Compose:

```text
Dokploy → Environment → variáveis privadas
compose.dokploy.yaml → carrega .env
containers → recebem configuração
```

Não é necessário definir `STRIDEBR_DEPLOY_ENV_FILE` no painel. O `.env` é usado na interpolação e pelo `env_file` dos serviços; não é montado no container. `.gitignore` e `.dockerignore` mantêm esse arquivo fora do Git e da imagem. Nunca colocar secrets em argumentos de build ou no source.

Para validar manualmente com `.env` ao lado do Compose, sem iniciar serviços:

```sh
docker compose -f compose.dokploy.yaml config --quiet
```

Opcionalmente, fora do Dokploy, um arquivo privado alternativo continua suportado. Use o mesmo arquivo para interpolação e runtime:

```sh
export STRIDEBR_DEPLOY_ENV_FILE=/caminho/absoluto/privado.env
docker compose --env-file "$STRIDEBR_DEPLOY_ENV_FILE" -f compose.dokploy.yaml config --quiet
```

Não publicar a saída completa de `compose config`: pode conter secrets. O `env_file` fornece todas as variáveis do contrato; interpolação exige explicitamente APP_ENV/URL e DB host/name/user/password. O entrypoint falha em staging/production se configuração obrigatória estiver ausente/inválida; nunca usa senha padrão local em produção.

```sh
php scripts/config_check.php
php scripts/config_check.php --database
```

O checker mostra estados, não passwords/tokens/hosts. Obrigatórios em ambiente publicado: origem HTTPS, DB válido, SMTP, `STRIDEBR_SUPPORT_EMAIL` válido e uploads graváveis. Maps/OAuth/integrações/ads/doações são opcionais. SMTP é obrigatório porque signup e recuperação fazem parte da 1.0. Proxy vazio é válido para terminação TLS direta, mas para Traefik precisa ser configurado por Infra.

## Banco, migrations e readiness

O Compose de deploy não provisiona DB: `STRIDEBR_DB_*` aceita banco de outro stack, rede privada ou serviço gerenciado. Se Infra escolher PostgreSQL em container, montar volume persistente em `/var/lib/postgresql/data` para PostgreSQL 17, sem publicar 5432 por padrão. `STRIDEBR_DB_SSLMODE` suporta disable/allow/prefer/require/verify-ca/verify-full; CA adicional via configuração/mount libpq quando necessário. Usuário de migrations precisa das permissões DDL exigidas pelos SQL existentes.

Job dedicado `migrate` executa `apply` e deve terminar com sucesso antes de iniciar `app`. Pode ser executado explicitamente em pre-deploy com o mesmo artefato/env:

```sh
docker compose -f compose.dokploy.yaml run --rm migrate apply
docker compose -f compose.dokploy.yaml run --rm migrate status
```

Runner usa advisory lock no PostgreSQL para serializar jobs, `ON_ERROR_STOP` e registro de arquivos aplicados. Não roda por request, não renomeia migrations e não regrava histórico. Para uma release nova, Infra deve recriar/executar o job com a imagem nova; não reaproveitar container concluído de release anterior. Backup/restore e avaliação de rollback precedem migração real; não há rollback automático destrutivo.

- `/health.php`: liveness PHP, 200 `OK`, sem DB/session/secrets. HEALTHCHECK da imagem usa esse endpoint.
- `/ready.php`: 200 `READY` somente com configuração válida, DB acessível e migrations atuais registradas; caso contrário 503 `NOT READY`. Sem detalhes internos.
- `release_check.sh --full`: estáticos + suíte PostgreSQL descartável; em ambiente publicado exige também readiness e status real sem pendências. Para testes locais não exige secrets reais. Não apontar a suíte para banco de produção.

## Persistência e permissões

Volume `stridebr_uploads` em `/var/www/html/public/uploads`, gravável por `www-data` (UID/GID da imagem). Volume novo herda ownership do diretório da imagem. Para volume restaurado, Infra deve verificar ownership antes de iniciar.

| Dados | Classificação | Persistência |
| --- | --- | --- |
| `public/uploads/avatars/` | originais de perfil | obrigatória |
| `public/uploads/profile-banners/` | banners de perfil | obrigatória |
| `public/uploads/events/` | imagens de eventos | obrigatória |
| `public/uploads/avatars/cache/` | variantes regeneráveis | acompanha volume; pode ser regenerado |
| `/tmp/stridebr-elevation`, `/tmp/stridebr-map-geometry` | caches regeneráveis | não persistir |
| upload temporário PHP | temporário | `/tmp` |
| GPX/TCX/FIT importado | parsing/preview temporário; resultado no DB/sessão | não é arquivo permanente adicional |
| exportações de atividade/conta | resposta gerada ao usuário | não persistir |
| sessões PHP e lock local de sync | runtime | `/tmp`, efêmero |

`/tmp`, `/var/run/apache2`, `/var/lock/apache2` são tmpfs. Recriar o container encerra sessões PHP; usuário pode precisar autenticar novamente. Para Web 1.0, usar uma réplica web por ambiente; múltiplas réplicas exigem estratégia de sessão que está fora desta rodada. Valores de treino ficam no DB e GPS usa recuperação local existente.

Uploads não entram no build. Apache impõe `AllowOverride None`, sem execução CGI/includes, sem symlinks e nega extensões executáveis case-insensitive, inclusive extensões compostas. Essa regra está fora do volume, portanto não depende de `.htaccess` restaurado. Validações de MIME/imagem e nomes gerados existentes foram preservadas.

## Proxy, HTTPS e headers

`STRIDEBR_TRUSTED_PROXIES` recebe IPs/CIDRs separados por vírgula. Vazio ignora todos os forwarded headers. Não configurar `0.0.0.0/0`/`::/0`; o checker rejeita confiança universal. A lista deve conter somente proxies controlados, não toda rede de clientes.

Somente REMOTE_ADDR confiável permite headers. X-Forwarded-For: valida a cadeia toda, percorre da direita para esquerda, removendo hops confiáveis; usa o primeiro endereço não confiável a partir da direita. Cadeia inválida retorna REMOTE_ADDR; X-Real-IP só serve de fallback quando XFF está ausente. Proxy deve remover/substituir headers recebidos do cliente. X-Forwarded-Proto só aceita valor único `https` vindo do proxy confiável. Não confiar em X-Forwarded-Host.

Cookies de sessão configurados antes de session_start: HttpOnly, SameSite=Lax e Secure em staging/production, origem HTTPS ou conexão HTTPS reconhecida. Local HTTP continua sem Secure obrigatório. CSRF atual permanece.

App/Apache fornecem CSP, XFO (DENY; SAMEORIGIN nas rotas de editor embed existentes), nosniff, Referrer-Policy e Permissions-Policy. CSP não libera scripts inline, permite assets/maps existentes e acrescenta origins AdSense somente quando master flag e publisher estão configurados. Imagens externas existentes continuam suportadas. Não configurar uma segunda CSP conflitante no proxy.

Infra fornece TLS, redirect HTTP→HTTPS e HSTS final **somente em HTTPS**, com política de duração/subdomínios definida após validar staging/produção. App não força redirect para domínio de produção e não emite HSTS no localhost.

Staging tem noindex por padrão; `STRIDEBR_ROBOTS_NOINDEX=1` explicita. `robots.txt` é dinâmico, derivando sitemap de APP_URL; páginas privadas têm X-Robots-Tag. Production continua indexável nas páginas públicas apropriadas. Nenhuma regra funcional depende da hospedagem anterior.

## E-mail e serviços opcionais

SMTP via PHPMailer, sem provider novo: `STRIDEBR_MAIL_TRANSPORT=smtp`, `STRIDEBR_SMTP_HOST/PORT/AUTH/USERNAME/PASSWORD/ENCRYPTION`, `STRIDEBR_MAIL_FROM`, `STRIDEBR_MAIL_FROM_NAME`. TLS validado, sem opções para ignorar certificado. `tls` = STARTTLS, `ssl` = TLS implícito. `none` e transporte legado `mail` somente development; default local é disabled. Falhas retornam false e log genérico, sem credenciais/conteúdo. `STRIDEBR_SUPPORT_EMAIL` é obrigatório e válido em staging/production, sem fallback para contato pessoal. Quando válido e diferente do remetente, é adicionado como Reply-To; vazio/inválido ou igual ao From não gera esse header. Exemplo de configuração de produção: `STRIDEBR_MAIL_FROM=noreply@stridebr.com.br`, `STRIDEBR_MAIL_FROM_NAME=StrideBR` e `STRIDEBR_SUPPORT_EMAIL=suporte@stridebr.com.br`. Infra valida entrega, DNS de e-mail e fluxos reais posteriormente.

Maps: `STRIDEBR_MAPS_ARCGIS_KEY`, fallback existente sem token. OAuth Google: flags/client ID/secret; callback APP_URL + `/auth/google-callback.php`. O override legado só é aceito pelo checker se idêntico ao derivado.

Strava/Polar/Fitbit/Suunto: client ID/secret e `STRIDEBR_INTEGRATIONS_SECRET` (mínimo 32 caracteres, preservar ao migrar tokens existentes). Suunto precisa também subscription key. Callbacks: APP_URL + `/auth/integration-callback.php?provider=PROVIDER`. Providers sem configuração ficam indisponíveis. Garmin permanece não validado/desativado, mesmo com credenciais.

Ads e doações vêm OFF. Infra pode configurar flags e valores opcionais depois, sem reabrir placements. Publisher ID não é senha, mas nenhum ID real foi inserido. Developer Portals/callbacks reais pertencem à Infra.

## PWA/GPS

Manifest standalone, ícones 192/512/maskable/Apple, offline shell e safe areas existentes. Service worker usa build para separar caches, network-first para assets e updateViaCache=none; não guarda APIs, POST, uploads, páginas privadas ou respostas de login. Navegação offline retorna shell neutro, sem dados da conta. Não recarrega atividade automaticamente após atualização.

GPS conserva Wake Lock quando disponível, fallback sem hack, bloqueio dos controles do Stride e desbloqueio deliberado por hold, métricas/cronômetro ativos e recuperação local. Isso não bloqueia o sistema operacional e não oferece GPS nativo em background. Bloqueio do telefone/suspensão pode interromper GPS; iOS antigo pode encerrar a PWA e não recuperar todos os dados. Teste físico iOS/Android instalado continua obrigatório.

## Ordem de handoff

Build → provisionar volumes → banco → env → migrations → iniciar app sem tráfego externo → readiness → smoke → liberar tráfego. Infra define os comandos finais e a rede. Staging vem antes de production; versão/tag 1.0.0 só depois da validação final autorizada.

Job opcional existente: `php scripts/sync_integrations.php --limit=500`, somente com integrações efetivamente conectadas. Sugestão operacional: a cada 15 minutos, uma execução por ambiente, respeitando quotas dos providers; não é requisito para subir a 1.0 sem integrações. Não foi configurado scheduler. Backup: `scripts/backup_db.sh`; executar e ensaiar restore somente na etapa Infra.
