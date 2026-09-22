# MOBILE CONTRACT FREEZE V1

Este documento congela o contrato Core consumido pelo Android após Core App Completion V1.1. Código real e `docs/api/openapi.yaml` são as referências executáveis. Mudanças incompatíveis exigem uma nova versão de contrato.

Base: `/api/v1`.

Salvo endpoints públicos de autenticação/meta, as rotas abaixo exigem `Authorization: Bearer <token>`.

## Modalidades

### GET `/sports`

Catálogo para criação/edição de Activity. Não usar `/progress/sports` como catálogo completo.

Retorna modalidades globais ativas e modalidades custom do usuário autenticado. Modalidades inativas e custom de outro owner não aparecem.

Filtros opcionais: `q` e `family`.

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

## Activity manual

### POST `/activities/manual`

Header obrigatório:

```text
Idempotency-Key: <8-128 caracteres>
```

Body suportado:

```json
{
  "sport": "corrida",
  "title": "Corrida manual",
  "notes": null,
  "started_at": "2026-09-21T07:30:00-03:00",
  "ended_at": null,
  "duration_s": 1800,
  "distance_m": 5000,
  "visibility": "privado",
  "perceived_effort": 6,
  "equipment_ids": [],
  "workout_id": null,
  "strength_exercises": []
}
```

`sport` e `started_at` são obrigatórios. GPS não é obrigatório. `workout_id` é aceito quando existe vínculo válido com um workout do próprio usuário.

Activity nova: HTTP `201`. Retry com a mesma key e mesmo payload: HTTP `200`. Mesma key com payload incompatível: `409 idempotency_conflict`.

A resposta sempre contém Activity Detail completo:

```json
{
  "data": {},
  "reused": false
}
```

`Location` aponta para `/api/v1/activities/{id}`.

## Strength Activity

`GET /activities/{id}` para modalidade strength acrescenta `strength_exercises` com dados realizados:

```json
{
  "strength_exercises": [
    {
      "exercise_id": "...",
      "name": "Supino reto",
      "sets": [
        {
          "number": 1,
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
}
```

`duration_s` é JSON `number` quando presente. O cliente não deve exigir representação inteira. `repetitions`, `load_kg`, `duration_s`, `distance_m`, `rir`, `rpe` e `notes` podem ser `null`. Ausência não deve ser convertida para zero.

## Activity Detail e capabilities

Activity Detail mantém:

- `version`;
- `updated_at`;
- `capabilities.can_edit`;
- `capabilities.can_delete`;
- `capabilities.can_edit_title`;
- `capabilities.can_edit_notes`;
- `capabilities.can_edit_effort`;
- `capabilities.can_edit_visibility`;
- `capabilities.can_edit_datetime`;
- `capabilities.can_edit_sport`;
- `capabilities.can_edit_equipment`;
- `capabilities.can_edit_metrics`;
- `capabilities.can_edit_strength`;
- `capabilities.can_trim_route`.

O Android deve usar essas capabilities em vez de inferir permissão por modalidade/origem.

### PATCH `/activities/{id}`

`if_version` é obrigatório. Versão stale retorna `409 state_conflict`. Activity de outro owner retorna `404`.

Campos são aceitos conforme capabilities. GPS não permite distância arbitrária. Activity derivada de Workout Session não permite sobrescrever performed sets.

### DELETE `/activities/{id}`

Soft delete owner-scoped. Replay seguro retorna `reused=true`. Activity excluída deixa listagens e Progress normais.

## Workout execution summary

### GET `/workout-sessions/by-workout/{workoutId}`

Sessão real concluída:

```json
{
  "data": {
    "available": true,
    "execution_mode": "session",
    "workout_id": "...",
    "session_id": "...",
    "started_at": "...",
    "ended_at": "...",
    "activity_id": "...",
    "activity": {"id": "..."},
    "exercises": [
      {
        "id": "...",
        "exercise_id": "...",
        "name": "Supino reto",
        "completed": true,
        "sets": [
          {
            "id": "...",
            "number": 1,
            "actual_repetitions": "12",
            "actual_load": "40 kg",
            "actual_duration_s": null,
            "actual_distance_m": null,
            "repetitions": "12",
            "load": "40 kg",
            "duration_s": null,
            "distance_m": null,
            "completed": true
          }
        ]
      }
    ]
  }
}
```

`actual_repetitions` e `actual_load` são `string|null` no domínio Workout Session. Não converter o contrato do Core para número. `actual_duration_s` é `integer|null`; `actual_distance_m` é `number|null`.

`repetitions`, `load`, `duration_s` e `distance_m` são aliases dos valores realizados. Eles nunca recebem target/prescription como fallback.

Marcar série/exercício/sessão como concluído não transforma targets em actuals.

Quick Register:

```json
{
  "data": {
    "available": false,
    "execution_mode": "quick_register",
    "workout_id": "...",
    "activity_id": "...",
    "activity": {"id": "..."},
    "exercises": []
  }
}
```

Nenhuma série planejada é fabricada como realizada.

## Série extra

### POST `/workout-sessions/{sessionId}/exercises/{exerciseId}/sets`

Exige `Idempotency-Key`. Não exige body.

Nova série: HTTP `201`.

```json
{
  "data": {
    "session": {},
    "set_id": "...",
    "reused": false
  }
}
```

Retry da mesma operação: HTTP `200`, mesmo `set_id`, `reused=true`.

A série inicia com actuals `null`. O Core gera o ID e `number=max+1`.

## Atualização de série

### PATCH `/workout-sessions/{sessionId}/sets/{setId}`

Campos canônicos para o Android:

```json
{
  "actual_repetitions": "12",
  "actual_load": "40 kg",
  "actual_duration_s": 90,
  "actual_distance_m": 600,
  "edited_field": "reps",
  "propagate_load": false
}
```

`edited_field`: `load`, `reps`, `duration` ou `distance`.

Os aliases legados `repetitions`, `load`, `duration_s` e `distance_m` continuam aceitos na entrada por compatibilidade, mas clientes novos devem enviar `actual_*`.

## Perfil

### GET `/me`

Além dos campos históricos de identidade, retorna:

- `bio`;
- `phone`;
- `birth_date`;
- `profile_visibility`;
- `discoverable`.

### PATCH `/me`

Campos editáveis:

- `name`;
- `username`;
- `bio`;
- `phone`;
- `birth_date`.

Email, senha e exclusão de conta não fazem parte deste endpoint. A resposta usa o mesmo Self Profile de `GET /me`.

## Pessoas

### Person DTO

```json
{
  "id": "...",
  "username": "bruno",
  "display_name": "Bruno",
  "avatar_url": null,
  "bio": null,
  "trainer_mode": true
}
```

O campo público é `display_name`, não `name`. Person nunca transporta email, telefone ou nascimento.

### GET `/people/search?q=...&type=all|friend|trainer`

```json
{
  "data": {
    "items": [],
    "capabilities": {
      "friends_enabled": true,
      "trainer_enabled": true
    }
  }
}
```

Cada item também pode conter `friendship_status` e `coaching_status`.

### GET `/people/{id}`

Retorna Person + `friendship_status` + `coaching_status` + `capabilities`, além de:

```json
{
  "people_capabilities": {
    "friends_enabled": true,
    "trainer_enabled": true
  }
}
```

Privacidade continua aplicada pelo Core. Não há vazamento de email/phone/birth_date.

### Friendship DTO

```json
{
  "id": "...",
  "status": "accepted",
  "person": {},
  "capabilities": {
    "can_accept": false,
    "can_reject": false,
    "can_delete": true
  }
}
```

`GET /people/friends` retorna:

```json
{
  "data": {
    "friends": [],
    "incoming": [],
    "outgoing": []
  }
}
```

### Coaching DTO

```json
{
  "id": "...",
  "status": "pending",
  "trainer": {},
  "athlete": {},
  "requested_by": "trainer",
  "permissions": {
    "can_prescribe": true,
    "can_view_schedule": true,
    "can_view_activities": true,
    "can_view_feedback": false
  },
  "capabilities": {
    "can_accept": false,
    "can_reject": false,
    "can_cancel": true,
    "can_end": false,
    "can_edit_permissions": false
  }
}
```

Estados públicos: `pending`, `accepted`, `rejected`, `ended`.

`GET /people/coaching` retorna `trainers`, `athletes`, `incoming` e `outgoing`.

Somente o atleta de um vínculo aceito pode alterar permissões. O treinador não concede permissão a si mesmo.

As migrations normais definem `friends.enabled=true` e `trainer.enabled=true`.

## Progress filtrado

As rotas filtráveis aceitam `sport=<id ou slug>` e resolvem somente modalidade ativa global ou custom do owner.

Cobertura congelada:

- `/progress/dashboard`;
- `/progress/cardio`;
- `/progress/strength`;
- `/progress/exercises`;
- `/progress/exercises/{id}`;
- `/progress/timeseries`;
- `/progress/calendar`;
- `/progress/adherence`.

Modalidade válida sem Activity retorna HTTP `200` com o shape canônico vazio. Campo não aplicável usa vazio/`null` conforme schema, nunca erro por ausência de dados.

## Workout preview Web

`public/api/cronograma-treino-preview.php` mantém três modos:

- `planned`: futuro/atual sem execução, usa prescription;
- `performed`: concluído com Activity válida, usa realizado;
- `missed`: passado sem execução, identifica o planejado como não realizado.

Em `performed`, exercício planejado que não foi realizado não aparece como realizado.

## Compatibilidade

Este freeze descreve o contrato que o Android deve consumir. Correções V1.1 preservam aliases legados de PATCH Set apenas para compatibilidade; eles não são o contrato preferencial para cliente novo.

Não tratar `/progress/sports` como catálogo, prescription como actual, `name` como Person public name, nem Coaching como um único `person + role`.
