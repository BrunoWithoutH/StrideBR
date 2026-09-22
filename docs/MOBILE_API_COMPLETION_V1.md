# Mobile API Completion V1

Base: `/api/v1`. Todas as rotas abaixo, exceto `/meta`, exigem `Authorization: Bearer <token>` e retornam o envelope JSON da API v1.

## Workout Execution V2

### PATCH `/workout-sessions/{sessionId}/sets/{setId}`

Request parcial:

```json
{
  "actual_repetitions": "12",
  "actual_load": "40 kg",
  "actual_duration_s": 1500,
  "actual_distance_m": 600,
  "edited_field": "duration",
  "propagate_load": false
}
```

`edited_field` aceita `load`, `reps`, `duration` e `distance`. Campos podem ser `null`. Os campos canônicos de entrada são `actual_repetitions`, `actual_load`, `actual_duration_s` e `actual_distance_m`. Os aliases legados `repetitions`, `load`, `duration_s` e `distance_m` continuam aceitos para compatibilidade. O Core aplica a propagação V2 do domínio e retorna a sessão completa.

Cada set retorna `actual_repetitions`, `actual_load`, `actual_duration_s` e `actual_distance_m`. Ausência é `null`, nunca zero fabricado. O histórico usa `duration_s`/`distance_m` realizados.

### POST `/workout-sessions/{sessionId}/exercises/{exerciseId}/sets`

Header obrigatório: `Idempotency-Key` de 8–128 caracteres ASCII seguros.

Sem body obrigatório. O Core cria `numero=max(numero)+1`, com actuals inicialmente `null`. Série extra nova responde `201`; retry responde `200` com o mesmo `set_id`.

```json
{
  "data": {
    "session": {},
    "set_id": "...",
    "reused": false
  }
}
```

Retry com a mesma key não cria outra série; mesma key para operação incompatível retorna `409 idempotency_conflict`.

### GET `/workout-sessions/by-workout/{workoutId}`

Retorna execução realizada de sessão concluída. `execution_mode=session` contém somente exercícios/sets realizados, timestamps e Activity. Quick Register retorna `available=false` e `execution_mode=quick_register`; não fabrica sets.

## Progress filtrado

Todas as rotas Progress continuam aceitando `sport=<id ou slug canônico>` quando aplicável. O Core resolve apenas modalidades ativas, globais ou custom do próprio usuário. Dashboard com modalidade válida e zero dados retorna `200` com shape canônico vazio. `/progress/cardio` é usado para modalidades cardio; `/progress/strength` e `/progress/exercises` para strength. No dashboard filtrado, o bloco de família não aplicável é `null`, não erro nem objeto fabricado.

Exemplos:

```text
GET /progress/dashboard?from=2026-08-01&to=2026-09-01&sport=corrida
GET /progress/cardio?from=2026-08-01&to=2026-09-01&sport=corrida&bucket=week
GET /progress/strength?from=2026-08-01&to=2026-09-01&sport=musculacao
GET /progress/exercises?from=2026-08-01&to=2026-09-01&sport=musculacao
```


## Catálogo de modalidades

### GET `/sports`

Este é o catálogo para registro de Activity. Não usar `/progress/sports` como catálogo completo: `/progress/sports` continua representando somente modalidades presentes nas Activities do período.

`/sports` retorna modalidades globais ativas e custom owner-scoped, ordenadas para UI:

```json
{
  "data": [
    {
      "id": "...",
      "slug": "corrida",
      "name": "Corrida",
      "family": "cardio",
      "route_capable": true
    }
  ]
}
```

Filtros opcionais: `q` e `family`.

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

Sucesso novo retorna HTTP `201`; retry idempotente retorna HTTP `200`. Ambos retornam Activity Detail completo em `data`, `reused` no envelope e header `Location: /api/v1/activities/{id}`. Mesma key + mesmo payload retorna a mesma Activity com `reused=true`; mesma key + payload diferente retorna `409 idempotency_conflict`.

No Activity Detail strength, `duration_s` é `number|null`, não um contrato restrito a inteiro.

## Activity mutations

Activity Detail inclui `version`, `updated_at` e `capabilities`.

### PATCH `/activities/{id}`

Campos V1 suportados: `title`, `notes`, `perceived_effort`, `visibility`, `equipment_ids` e, quando `can_edit_datetime=true`, `started_at`, `ended_at` e `duration_s`. Quando `can_edit_metrics=true`, `distance_m` também pode ser alterado. Quando `can_edit_strength=true`, `strength_exercises` substitui o conjunto manual realizado usando a mesma estrutura canônica do POST manual. `if_version` é obrigatório em todo PATCH e deve usar a versão recebida no detail.

```json
{
  "if_version": "opaque-version",
  "title": "Treino editado",
  "visibility": "amigos"
}
```

Versão stale retorna `409 state_conflict`. Recurso de outro owner retorna `404`. `sport` e trim não fazem parte desta V1. Distância/strength só podem ser alterados quando as respectivas capabilities permitem: Activity manual/API, sem rota e sem Workout Session. GPS não recebe distância calculada pela rota como número arbitrário e Workout Session não permite sobrescrever performed sets. `can_trim_route=false` até contrato dedicado.

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


## Pessoas

A superfície de Pessoas é separada de Conexões/Integrações.

Rotas:

```text
GET    /people/search?q=...&type=all|friend|trainer
GET    /people/{id}
GET    /people/friends
POST   /people/friendships
POST   /people/friendships/{id}/accept
POST   /people/friendships/{id}/reject
DELETE /people/friendships/{id}
GET    /people/coaching
POST   /people/coaching
POST   /people/coaching/{id}/accept
POST   /people/coaching/{id}/reject
DELETE /people/coaching/{id}
PATCH  /people/coaching/{id}/permissions
```

O DTO público contém somente `id`, `username`, `display_name`, `avatar_url`, `bio` quando permitido e `trainer_mode`. Email, telefone e nascimento nunca são transportados. Busca respeita usuário ativo e `discoverable`; detalhe respeita privacidade/relação. Amigos reutilizam `amizades` e notificações existentes. Coaching reutiliza `vinculos_treinador_atleta` e o domínio Web.

Somente o atleta de um vínculo aceito pode editar `can_prescribe`, `can_view_schedule`, `can_view_activities` e `can_view_feedback`; treinador não concede permissão a si mesmo.

Contrato completo: [`PEOPLE_API_V1.md`](PEOPLE_API_V1.md).

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


## Contract Freeze V1.1

O contrato final consumido pelo Android está em [`MOBILE_CONTRACT_FREEZE_V1.md`](MOBILE_CONTRACT_FREEZE_V1.md). Em caso de divergência com exemplos históricos deste documento, o freeze V1.1 e o OpenAPI atual prevalecem.
