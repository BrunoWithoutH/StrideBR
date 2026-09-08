> HISTÓRICO — procedimento da hospedagem anterior. Não usar para Web 1.0. Contrato atual: [DEPLOY_DOKPLOY.md](DEPLOY_DOKPLOY.md).

# Migrations pelo pgAdmin do AlwaysData

Quando a rede local bloquear a porta externa do PostgreSQL, as migrations podem ser aplicadas pelo Query Tool do pgAdmin disponibilizado pelo AlwaysData.

## Regra

Não reaplique arquivos no escuro. Primeiro confira o registro do banco:

```sql
SELECT version, applied_at
FROM public.stridebr_schema_migrations
ORDER BY version;
```

O runner `scripts/migrate_product.sh` mantém essa tabela automaticamente. Quando uma migration for executada manualmente pelo pgAdmin, execute o SQL da migration e, depois de confirmar que terminou sem erro, registre a versão:

```sql
INSERT INTO public.stridebr_schema_migrations (version)
VALUES ('NOME_DO_ARQUIVO.sql')
ON CONFLICT (version) DO NOTHING;
```

## Migration da 1.0 RC

A atualização da closed alpha para a 1.0 RC é feita por:

```text
20260903_v1_rc.sql
```

Ela consolida as migrations intermediárias criadas durante o desenvolvimento desde o último commit publicado. O arquivo é transacional por etapa e pode ser executado inteiro no Query Tool.

## Verificação

Depois de aplicar e registrar:

```sql
SELECT to_regclass('stridebr.ux_cronograma_compartilhamento_pendente');
```

O resultado esperado é:

```text
stridebr.ux_cronograma_compartilhamento_pendente
```

Confirme também que todas as migrations presentes no repositório estão registradas:

```sql
SELECT version
FROM public.stridebr_schema_migrations
ORDER BY version;
```

No estado atual do repositório, as mudanças posteriores à closed alpha ficam concentradas em `20260903_v1_rc.sql`.

Para verificar Routes v1:

```sql
SELECT
    to_regclass('stridebr.rotas_atividade') AS rotas,
    EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'stridebr'
          AND table_name = 'modalidades'
          AND column_name = 'permite_rota'
    ) AS permite_rota,
    EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'stridebr'
          AND table_name = 'rotas_atividade'
          AND column_name = 'ganho_elevacao_m'
    ) AS elevacao_rota;
```

## Se der erro

Não registre a versão em `stridebr_schema_migrations` se o SQL falhar. Como as migrations desta fase usam `BEGIN`/`COMMIT`, uma falha antes do `COMMIT` deve deixar a alteração sem concluir. Copie a mensagem completa do PostgreSQL para revisão antes de publicar código que dependa dela.

## Releases consolidadas

Ao atualizar uma instalação que estava no último commit publicado, execute somente as migrations pendentes em ordem. A 1.0 RC concentra as alterações desenvolvidas após a closed alpha em `20260903_v1_rc.sql`; não é necessário executar dezenas de arquivos intermediários que existiram apenas durante o desenvolvimento local.
