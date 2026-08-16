# Deploy no AlwaysData

O StrideBR usa a mesma base de código no Docker e em produção. A conexão com PostgreSQL é definida pelas variáveis de ambiente e nenhuma credencial de produção deve ser salva no repositório.

## Diretório do site

Envie o projeto para:

```text
/home/stridebr/www/stridebr
```

No painel do AlwaysData, configure o `Root directory` do site PHP para:

```text
/home/stridebr/www/stridebr/public
```

## Variáveis de ambiente

Em `Web > Sites > Configuration > Environment variables`, defina os valores da conta atual:

```text
STRIDEBR_DB_HOST=postgresql-SUA_CONTA.alwaysdata.net
STRIDEBR_DB_PORT=5432
STRIDEBR_DB_NAME=SEU_BANCO
STRIDEBR_DB_USER=SEU_USUARIO
STRIDEBR_DB_PASSWORD=SUA_SENHA
STRIDEBR_APP_ENV=production
STRIDEBR_APP_URL=https://stridebr.alwaysdata.net
STRIDEBR_MAIL_FROM=SEU_EMAIL_DE_ENVIO
STRIDEBR_MAIL_FROM_NAME=StrideBR
STRIDEBR_TERMS_VERSION=2026-08-15-alpha-1
STRIDEBR_PRIVACY_VERSION=2026-08-15-alpha-1
```

`src/config/pg_config.php` não contém senha de produção e lê esses valores com `getenv()`.

## Migração de produto

Depois que o schema principal, o schema de atividades e os seeds já existirem no banco, execute:

```bash
psql \
  -h postgresql-SUA_CONTA.alwaysdata.net \
  -p 5432 \
  -U SEU_USUARIO \
  -d SEU_BANCO \
  -f src/database/migrations/20260815_product_foundation.sql

psql \
  -h postgresql-SUA_CONTA.alwaysdata.net \
  -p 5432 \
  -U SEU_USUARIO \
  -d SEU_BANCO \
  -f src/database/migrations/20260815_alpha_readiness.sql
```

As migrações adicionam perfil ampliado, papéis administrativos, amigos, compartilhamentos, execução de treinos, feature flags, mídia por URL, logs de acesso, audit log, feedback da alpha, termos versionados, tokens de autenticação, convites e controles de bloqueio de conta.

Se as variáveis `STRIDEBR_DB_*` já estiverem exportadas no terminal, o mesmo processo pode ser executado com:

```bash
./scripts/migrate_product.sh
```

Para promover a conta proprietária pela primeira vez:

```sql
SET search_path TO stridebr, public;
UPDATE usuarios
SET papelusuario = 'owner'
WHERE lower(emailusuario) = lower('SEU_EMAIL');
```

Saia e entre novamente depois disso para atualizar a sessão.

## Banco Docker já existente

Os arquivos em `/docker-entrypoint-initdb.d` só rodam quando o volume PostgreSQL é criado. Num volume local existente, aplique a migração manualmente:

```bash
docker exec -i stridebr-postgres \
  psql -U stridebr -d stridebr \
  < src/database/migrations/20260815_product_foundation.sql

docker exec -i stridebr-postgres \
  psql -U stridebr -d stridebr \
  < src/database/migrations/20260815_alpha_readiness.sql
```

Se o banco local puder ser apagado, outra opção é recriar o volume:

```bash
docker compose down -v
docker compose up -d --build
```

## Rsync

A partir da raiz local do projeto:

```bash
rsync -avz --delete --progress \
  --exclude='.git/' \
  --exclude='.env' \
  --exclude='compose.yaml' \
  --exclude='Dockerfile' \
  --exclude='stridebr.sql' \
  --exclude='*.dump' \
  --exclude='node_modules/' \
  ./ \
  stridebr@ssh-stridebr.alwaysdata.net:~/www/stridebr/
```

O mesmo comando está disponível em:

```bash
./scripts/deploy_alwaysdata.sh
```

O destino pode ser sobrescrito com `STRIDEBR_DEPLOY_REMOTE` e `STRIDEBR_DEPLOY_PATH`.

`public/.htaccess` mantém assets estáticos em cache com versão pelo `filemtime`; PHP fica sem cache persistente. Isso reduz o problema de CSS antigo no Safari sem exigir nomes de arquivo diferentes a cada deploy.

## Depois do deploy

Confira login, onboarding, criação/remoção de treino, início/finalização de sessão, cards de atividades, amigos, perfil público e `/admin/`.

A área de cronogramas sincronizados e a descoberta pública ainda ficam atrás de feature flags ou como próximas etapas. O compartilhamento por snapshot já é a base estável para cópias independentes.


## Recursos da alpha por feature flag

No painel `/admin/`, administradores podem ativar ou desativar:

- `registration.enabled` — cadastro de contas
- `registration.invite_only.enabled` — cadastro somente por convite
- `auth.email_verification.enabled` — envio de verificação de e-mail
- `auth.email_verification.required` — bloqueia login sem e-mail confirmado
- `auth.password_reset.enabled` — recuperação de senha por e-mail
- `feedback.enabled` — formulário e atalho global de feedback
- `access_logs.enabled` — logs de acesso autenticado

Para verificação e recuperação por e-mail, configure `STRIDEBR_APP_URL` e `STRIDEBR_MAIL_FROM` antes de ativar as flags.

O painel administrativo possui páginas separadas para usuários e feedback. Bloquear uma conta incrementa a versão de sessão do usuário, fazendo com que sessões antigas sejam invalidadas na próxima requisição que conecta ao banco.
