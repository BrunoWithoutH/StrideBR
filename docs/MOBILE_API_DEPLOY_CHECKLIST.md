# Mobile API Completion V1 — Deploy checklist

## Banco

- Nenhuma migration nova foi criada nesta rodada.
- Confirmar que as 29 migrations atuais estão aplicadas com `sbcmigrationstatus`.
- Não restaurar banco nem remover volumes.

## Ambiente

- Nenhuma variável nova é obrigatória.
- Preservar `STRIDEBR_INTEGRATIONS_SECRET` e configurações existentes.
- Confirmar `STRIDEBR_BUILD` no deploy para `/api/v1/meta` quando usado pelo pipeline.

## Gates

```bash
cd /srv/http/stridebr
git diff --check
TERM=xterm ./scripts/test_static.sh
STRIDEBR_TEST_DB_NAME=stridebr_alpha_integration_test TERM=xterm ./scripts/test_all.sh
```

Obrigatório: `failed = 0`.

## Smoke público

Use token de teste próprio; nunca registre token real no repositório.

```bash
curl -sS https://stridebr.com.br/api/v1/meta
curl -sS -H 'Authorization: Bearer <TOKEN>' https://stridebr.com.br/api/v1/me
curl -sS -H 'Authorization: Bearer <TOKEN>' 'https://stridebr.com.br/api/v1/progress/dashboard?from=2026-08-01&to=2026-09-01&sport=corrida'
curl -sS -H 'Authorization: Bearer <TOKEN>' https://stridebr.com.br/api/v1/workout-sessions/current
```

Manual Activity:

```bash
curl -sS -X POST \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: smoke-manual-0001' \
  https://stridebr.com.br/api/v1/activities/manual \
  -d '{"sport":"corrida","started_at":"2026-09-19T07:30:00-03:00","duration_s":1200,"title":"Smoke manual"}'
```

PATCH Activity:

```bash
curl -sS -X PATCH \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Content-Type: application/json' \
  https://stridebr.com.br/api/v1/activities/<ID> \
  -d '{"if_version":"<VERSION>","title":"Smoke editado"}'
```

Profile:

```bash
curl -sS -X PATCH \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Content-Type: application/json' \
  https://stridebr.com.br/api/v1/me \
  -d '{"bio":"Smoke API"}'
```

Append set:

```bash
curl -sS -X POST \
  -H 'Authorization: Bearer <TOKEN>' \
  -H 'Idempotency-Key: smoke-extra-set-0001' \
  https://stridebr.com.br/api/v1/workout-sessions/<SESSION>/exercises/<EXERCISE>/sets
```

## Backward compatibility

- `POST /activities` continua sendo o contrato GPS anterior.
- PATCH de Workout Session continua aceitando reps/load e `propagate_load`; duration/distance são adicionais.
- GET `/me` preserva campos anteriores e apenas adiciona dados de perfil.
- Rotas existentes de Workout, Progress, Streams, Splits, Laps e Analysis permanecem.

## Rollback

Não há migration desta rodada. Rollback de aplicação pode voltar o código anterior sem rollback de schema. Activities, equipamentos e sessões criados pelas novas APIs usam tabelas/domínios já existentes e permanecem dados válidos do Core.

## Após deploy

- Conferir `/meta` e build esperado.
- Confirmir login/refresh com APK development.
- Rodar Progress com `sport=corrida` e `sport=musculacao`.
- Repetir uma manual Activity com mesma Idempotency-Key e confirmar mesmo ID.
- Validar PATCH stale retornando `409 state_conflict`.
- Validar append set com replay da mesma key.
- Validar que `DELETE /activities/{id}` remove a Activity de list/Progress sem hard delete.
