# Mobile API Completion V1

Base: `/api/v1`. Todas as rotas abaixo, exceto `/meta`, exigem `Authorization: Bearer <token>` e retornam o envelope JSON da API v1.

## Workout Execution V2

### PATCH `/workout-sessions/{sessionId}/sets/{setId}`

Request parcial:

```json
{
  "repetitions": "12",
  "load": "40 kg",
  "duration_s": 1500,
  "distance_m": 600,
  "edited_field": "duration",
  "propagate_load": false
}
```

`edited_field` aceita `load`, `reps`, `duration` e `distance`. Campos podem ser `null`. O Core aplica a propagação V2 do domínio e retorna a sessão completa. Clientes antigos que enviam somente reps/load continuam compatíveis.

Cada set retorna `actual_repetitions`, `actual_load`, `actual_duration_s` e `actual_distance_m`. Ausência é `null`, nunca zero fabricado. O histórico usa `duration_s`/`distance_m` realizados.

### POST `/workout-sessions/{sessionId}/exercises/{exerciseId}/sets`

Header obrigatório: `Idempotency-Key` de 8–128 caracteres ASCII seguros.

Sem body obrigatório. O Core cria `numero=max(numero)+1`, com actuals inicialmente `null`, e devolve a sessão atualizada mais `set_id` e `reused`. Retry com a mesma key não cria outra série; mesma key para operação incompatível retorna `409 idempotency_conflict`.

### GET `/workout-sessions/by-workout/{workoutId}`

Retorna execução realizada de sessão concluída. `execution_mode=session` contém somente exercícios/sets realizados, timestamps e Activity. Quick Register retorna `available=false` e `execution_mode=quick_register`; não fabrica sets.

## Progress filtrado

Todas as rotas Progress continuam aceitando `sport=<slug real>` quando aplicável. Dashboard com modalidade válida e zero dados retorna `200` com shape canônico vazio. `/progress/cardio` é usado para modalidades cardio; `/progress/strength` e `/progress/exercises` para strength.

Exemplos:

```text
GET /progress/dashboard?from=2026-08-01&to=2026-09-01&sport=corrida
GET /progress/cardio?from=2026-08-01&to=2026-09-01&sport=corrida&bucket=week
GET /progress/strength?from=2026-08-01&to=2026-09-01&sport=musculacao
GET /progress/exercises?from=2026-08-01&to=2026-09-01&sport=musculacao
```

## Activity manual

### POST `/activities/manual`

Header obrigatório: `Idempotency-Key`.

```json
{
  "sport": "corrida",
  "title": "Corrida manual",
  "notes": "Esteira",
  "started_at": "2026-09-19T07:30:00-03:00",
  "duration_s": 1800,
  "distance_m": 5000,
  "visibility": "privado",
  "perceived_effort": 6,
  "equipment_ids": []
}
```

`started_at` e `ended_at`, quando presentes, usam ISO 8601 com offset. É permitido enviar `ended_at`, `duration_s` ou ambos; quando ambos existem precisam ser consistentes. GPS não é exigido. O Core salva `origin=manual` reutilizando o domínio Web.

Para strength, `strength_exercises` aceita:

```json
[
  {
    "exercise_id": "...",
    "name": "Supino reto",
    "sets": [
      {
        "type": "trabalho",
        "repetitions": 12,
        "load_kg": 40,
        "duration_s": null,
        "distance_m": null,
        "rir": 2,
        "rpe": 8,
        "completed": true,
        "notes": null
      }
    ]
  }
]
```

Sucesso retorna Activity Detail completo. Mesma key + mesmo payload retorna a mesma Activity com `reused=true`; mesma key + payload diferente retorna `409 idempotency_conflict`.

## Activity mutations

Activity Detail inclui `version`, `updated_at` e `capabilities`.

### PATCH `/activities/{id}`

Campos V1 suportados: `title`, `notes`, `perceived_effort`, `visibility`, `equipment_ids` e, quando `can_edit_datetime=true`, `started_at`, `ended_at` e `duration_s`. Envie `if_version` com a versão recebida no detail.

```json
{
  "if_version": "opaque-version",
  "title": "Treino editado",
  "visibility": "amigos"
}
```

Versão stale retorna `409 state_conflict`. Recurso de outro owner retorna `404`. Alteração de sport, distância derivada, estrutura de strength e trim não fazem parte desta V1. `can_trim_route=false` até contrato dedicado.

### DELETE `/activities/{id}`

Soft delete via domínio Core. Replay para Activity já excluída do mesmo owner é seguro e retorna `reused=true`. Activity excluída deixa de aparecer em list/Progress. ID alheio retorna `404`.

## Perfil

### PATCH `/me`

Campos suportados: `name`, `username`, `bio`, `phone`, `birth_date`. Retorna o mesmo payload canônico de GET `/me`, agora também com `bio`, `phone`, `birth_date`, `profile_visibility` e `discoverable`.

Email, senha e exclusão de conta são rejeitados neste endpoint. Username reutiliza regras de normalização, formato, palavras reservadas e unicidade do Core.

## Privacy

### GET `/me/privacy`
### PATCH `/me/privacy`

Campos:

```json
{
  "default_activity_visibility": "privado",
  "hide_route_start_m": 0,
  "hide_route_end_m": 0,
  "profile_visibility": "privado",
  "discoverable": true
}
```

PATCH é parcial e preserva preferências não relacionadas no JSON do usuário.

## Equipment

### GET `/equipment`
### POST `/equipment`
### PATCH `/equipment/{id}`
### DELETE `/equipment/{id}`

Campos de mutation: `name`, `category`, `brand`, `model`, `started_on`, `retired_on`, `initial_distance_km`, `distance_alert_km`, `notes`, `active`. Recursos são owner-scoped. DELETE arquiva (`active=false`).

## Erros

- `401 authentication_required` / `token_expired`
- `404 not_found` para recurso ausente ou de outro owner
- `409 idempotency_conflict`
- `409 state_conflict`
- `409 invalid_state` para sessão/workout em estado incompatível
- `422 validation_error`

Formato:

```json
{
  "error": {
    "code": "validation_error",
    "message": "...",
    "fields": {}
  }
}
```

## Offline e retry

Use uma `Idempotency-Key` estável por operação lógica em manual Activity e append set. Reenvie a mesma key após timeout sem gerar uma nova. Em PATCH de Activity, guarde `version`; em `state_conflict`, descarte a base stale, faça GET do detail e peça resolução ao usuário antes de reenviar.

Não inferir permissões pela origem, modalidade ou presença de rota: use `capabilities` do Activity Detail.
