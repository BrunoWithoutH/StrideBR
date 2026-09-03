# Migrations pelo terminal

O runner oficial é `scripts/migrate_product.sh`. Ele aplica somente arquivos ainda não registrados em `public.stridebr_schema_migrations` e registra cada migration automaticamente depois que o SQL termina sem erro.

## Credenciais fora do repositório

Crie `~/.config/stridebr/db.env` e restrinja a leitura:

```bash
mkdir -p ~/.config/stridebr
chmod 700 ~/.config/stridebr
cat > ~/.config/stridebr/db.env <<'EOF'
STRIDEBR_DB_HOST=postgresql-SUA_CONTA.alwaysdata.net
STRIDEBR_DB_PORT=5432
STRIDEBR_DB_NAME=SEU_BANCO
STRIDEBR_DB_USER=SEU_USUARIO
STRIDEBR_DB_PASSWORD=SUA_SENHA
EOF
chmod 600 ~/.config/stridebr/db.env
```

O arquivo fica no diretório pessoal e não deve ser colocado no Git.

## Uso normal

```bash
./scripts/migrate_product.sh status
./scripts/migrate_product.sh
./scripts/migrate_product.sh status
```

`status` mostra cada arquivo como `aplicada` ou `PENDENTE`. O comando sem argumentos aplica todas as pendentes em ordem e registra as versões automaticamente.

## Migration aplicada manualmente

Quando uma migration já foi executada pelo pgAdmin e falta apenas registrar o histórico:

```bash
./scripts/migrate_product.sh mark 20260903_v1_rc.sql
```

O comando pede confirmação e só aceita nomes de arquivos que realmente existem em `src/database/migrations/`.

Use `mark` somente depois de confirmar que o SQL correspondente foi aplicado com sucesso.

## Outro arquivo de ambiente

Para usar outro local:

```bash
STRIDEBR_ENV_FILE=/caminho/seguro/db.env ./scripts/migrate_product.sh status
```

## Deploy com migration automática

O `scripts/deploy_alwaysdata.sh` executa o runner antes do `rsync`. Se uma migration falhar, o deploy para e os arquivos novos não são publicados.

```bash
./scripts/deploy_alwaysdata.sh
```

Em uma rede que bloqueie a porta PostgreSQL, aplique as migrations pelo pgAdmin e publique sem executar o runner local:

```bash
STRIDEBR_SKIP_MIGRATIONS=1 ./scripts/deploy_alwaysdata.sh
```

## Política de migrations por release

Migrations já publicadas em commits compartilhados são imutáveis. Durante desenvolvimento local, migrations intermediárias podem existir livremente; antes de um commit de release, migrations ainda não publicadas podem ser consolidadas em uma única migration que represente o salto entre o último commit compartilhado e a nova versão.

Para a StrideBR 1.0 RC, todas as migrations criadas após o commit `b0bab77` foram consolidadas em `20260903_v1_rc.sql`. As migrations de 15/08 que já faziam parte daquele commit permanecem separadas por compatibilidade histórica.
