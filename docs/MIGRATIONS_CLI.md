# Migrations — contrato atual

O runner é `sh scripts/migrate_product.sh apply`. Requer `psql` e `STRIDEBR_DB_HOST`, `STRIDEBR_DB_PORT`, `STRIDEBR_DB_NAME`, `STRIDEBR_DB_USER`, `STRIDEBR_DB_PASSWORD`; SSL via `STRIDEBR_DB_SSLMODE`. Credenciais vêm do ambiente ou de arquivo privado explícito. Não usar banco remoto nos testes locais.

- `status`: lista aplicada/PENDENTE.
- `apply`: inicializa schema base se necessário e aplica somente arquivos pendentes.
- `mark ARQUIVO.sql`: ferramenta excepcional de reconciliação; não substitui execução normal.

A imagem `migrations` do Dockerfile contém o runner e SQL, sem bind mount. `compose.dokploy.yaml` só inicia o app após o job terminar com sucesso. O runner mantém advisory lock no banco durante execução para serializar jobs/replicas; falha SQL retorna erro e interrompe a cadeia. Os nomes e o histórico das seis migrations atuais permanecem intactos, inclusive tratamento de blocos consolidados legados.

A semântica histórica dos SQL é preservada: nem toda sequência antiga é uma única transação. Se houver falha/interrupção, investigar antes de retomar; testar restore/rollback na cópia do banco real com Infra. Não editar arquivos já aplicados.

Local: `./scripts/test_all.sh` testa fresh/idempotência em banco descartável; `sh scripts/tests/test_migrations_upgrade.sh` testa upgrade sem alterar timestamps anteriores. Release publicada exige configuração válida e migrations sem pendências; `php scripts/config_check.php --database` também verifica readiness.

Procedimento operacional completo: [DEPLOY_DOKPLOY.md](DEPLOY_DOKPLOY.md). Nenhuma migration é executada por request HTTP.
