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
STRIDEBR_SUPPORT_EMAIL=SEU_EMAIL_PUBLICO_DE_CONTATO
STRIDEBR_TERMS_VERSION=2026-08-25-1
STRIDEBR_PRIVACY_VERSION=2026-08-25-1
STRIDEBR_ELEVATION_API_ENABLED=1
```

`src/config/pg_config.php` não contém senha de produção e lê esses valores com `getenv()`.


## E-mail da conta

Para a versão pública, crie um endereço de envio no provedor de e-mail usado pelo projeto e configure:

```text
STRIDEBR_APP_URL=https://stridebr.alwaysdata.net
STRIDEBR_MAIL_FROM=ENDERECO_DE_ENVIO
STRIDEBR_MAIL_FROM_NAME=StrideBR
```

Com `STRIDEBR_MAIL_FROM` válido, a aplicação disponibiliza verificação de e-mail e recuperação de senha conforme as feature flags. Sem um remetente válido, os fluxos de e-mail ficam indisponíveis e a aplicação não bloqueia login por falta de confirmação.

Depois de aplicar `20260903_v1_rc.sql`, contas ativas já existentes são consideradas verificadas. Novos cadastros passam pelo fluxo de confirmação quando o envio estiver configurado.

## Migração de produto

Depois que o schema principal, o schema de atividades e os seeds já existirem no banco, aplique as migrações na ordem abaixo. Para produção, `-v ON_ERROR_STOP=1` evita continuar depois de um erro SQL.

```bash
for migration in \
  src/database/migrations/20260815_product_foundation.sql \
  src/database/migrations/20260815_alpha_readiness.sql \
  src/database/migrations/20260815_feedback_anonymous.sql \
  src/database/migrations/20260815_fix_cronograma_delete_activity_trigger.sql \
  src/database/migrations/20260903_v1_rc.sql
do
  psql \
    -h postgresql-SUA_CONTA.alwaysdata.net \
    -p 5432 \
    -U SEU_USUARIO \
    -d SEU_BANCO \
    -v ON_ERROR_STOP=1 \
    -f "$migration" || exit 1
done
```

O runner também pode carregar as credenciais de `~/.config/stridebr/db.env`, mantendo a senha fora do repositório. O fluxo recomendado é:

```bash
./scripts/migrate_product.sh status
./scripts/migrate_product.sh
./scripts/migrate_product.sh status
```

Se uma migration tiver sido aplicada manualmente pelo pgAdmin e faltar apenas registrar o histórico:

```bash
./scripts/migrate_product.sh mark NOME_DA_MIGRATION.sql
```

Veja `docs/MIGRATIONS_CLI.md` para a configuração completa.

A migration `20260903_v1_rc.sql` consolida todo o trabalho de banco desenvolvido depois da closed alpha: segurança de identidade, treinador e agenda, Activities v2, metas, eventos, rotas, biblioteca de treinos, importação/exportação, compartilhamento, GPS web, integrações, progresso de força, taxonomia esportiva, energia e métricas específicas por modalidade. Rode as migrations antes de publicar PHP que dependa delas.

Para promover a conta proprietária pela primeira vez:

```sql
SET search_path TO stridebr, public;
UPDATE usuarios
SET papelusuario = 'owner'
WHERE lower(emailusuario) = lower('SEU_EMAIL');
```

Saia e entre novamente depois disso para atualizar a sessão.

## Banco Docker já existente

Os arquivos em `/docker-entrypoint-initdb.d` só rodam quando o volume PostgreSQL é criado. Num volume local existente, use o mesmo `./scripts/migrate_product.sh` com as variáveis apontando para o banco Docker, ou execute a migration desejada diretamente:

```bash
docker exec -i stridebr-postgres \
  psql -v ON_ERROR_STOP=1 -U stridebr -d stridebr \
  < src/database/migrations/20260903_v1_rc.sql
```

Se o banco local puder ser apagado, outra opção é recriar o volume:

```bash
docker compose down -v
docker compose up -d --build
```

## Backup antes do deploy

Antes de migration ou mudança relevante em produção, exporte as variáveis `STRIDEBR_DB_*` e crie um dump:

```bash
./scripts/backup_db.sh
```

Valide periodicamente o restore em um banco separado. O script de restore exige `--yes` e não deve ser usado para teste apontando para produção:

```bash
STRIDEBR_DB_NAME=stridebr_restore_test ./scripts/restore_db.sh backups/ARQUIVO.dump --yes
```

## Rsync

Use o script de deploy a partir da raiz do projeto:

```bash
./scripts/deploy_alwaysdata.sh
```

Ele usa `--delete`, mas protege `public/uploads/` e não envia `.env`. Isso é importante porque avatares e imagens de eventos são dados persistentes do servidor e não devem sumir em cada deploy.

Se precisar executar `rsync` manualmente, mantenha a proteção de uploads:

```bash
rsync -avz --delete --progress \
  --exclude='.git/' \
  --exclude='.env' \
  --exclude='compose.yaml' \
  --exclude='Dockerfile' \
  --exclude='stridebr.sql' \
  --exclude='*.dump' \
  --exclude='node_modules/' \
  --filter='protect public/uploads/***' \
  --include='public/uploads/' \
  --include='public/uploads/.htaccess' \
  --include='public/uploads/avatars/' \
  --include='public/uploads/avatars/.htaccess' \
  --include='public/uploads/avatars/index.html' \
  --include='public/uploads/events/' \
  --exclude='public/uploads/***' \
  ./ \
  stridebr@ssh-stridebr.alwaysdata.net:~/www/stridebr/
```

Um `rsync --delete` sem essas duas regras pode apagar foto de perfil e outras imagens que só existem em produção.

O destino pode ser sobrescrito com `STRIDEBR_DEPLOY_REMOTE` e `STRIDEBR_DEPLOY_PATH`.

`public/.htaccess` mantém assets estáticos em cache com versão pelo `filemtime`; PHP fica sem cache persistente. Isso reduz o problema de CSS antigo no Safari sem exigir nomes de arquivo diferentes a cada deploy.

## Depois do deploy

Confira login, onboarding, criação/remoção de treino, início/finalização de sessão, cards de atividades, amigos, perfil público, agenda mensal, vínculo treinador-atleta, prescrição/execução/feedback e `/admin/`.

Cronogramas sincronizados continuam atrás da feature flag `synced_schedules.enabled`, mas já fazem parte da Release Candidate em modo leitura. Depois do deploy, valide convite, aceite, atualização do original, notificação e revogação; o snapshot continua disponível para cópias independentes.


## Feature flags do produto

No painel `/admin/`, administradores podem ativar ou desativar:

- `registration.enabled` — cadastro de contas
- `registration.invite_only.enabled` — cadastro somente por convite
- `auth.email_verification.enabled` — envio de verificação de e-mail
- `auth.email_verification.required` — bloqueia login sem e-mail confirmado
- `auth.password_reset.enabled` — recuperação de senha por e-mail
- `feedback.enabled` — formulário e atalho global de feedback
- `access_logs.enabled` — logs de acesso autenticado
- `trainer.enabled` — vínculo treinador-atleta e prescrição
- `monthly_calendar.enabled` — agenda mensal e treinos específicos por data

Para verificação e recuperação por e-mail, configure `STRIDEBR_APP_URL` com URL HTTPS explícita e `STRIDEBR_MAIL_FROM` antes de ativar as flags.

O painel administrativo possui páginas separadas para usuários e feedback. Bloquear uma conta incrementa a versão de sessão do usuário, fazendo com que sessões antigas sejam invalidadas na próxima requisição que conecta ao banco.
