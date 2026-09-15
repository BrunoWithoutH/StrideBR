# MOBILE TRAINING PLATFORM V1 CONTRACT

## Base e autenticação

Base URL: `/api/v1`.

Todos os recursos privados usam `Authorization: Bearer <access_token>` e o mesmo ciclo de autenticação/rotação da API v1. IDs de usuário nunca são aceitos como autoridade no payload.

Timezone de calendário e horários locais nesta versão: `America/Sao_Paulo`.

`date` é data civil `YYYY-MM-DD`. `time` é horário local `HH:MM` e pode ser `null` em workouts avulsos. `null` não equivale a `00:00`. Timestamps absolutos usam ISO 8601 com offset.

## IDs e fonte de verdade

Workouts usam IDs opacos. O cliente deve armazenar e reenviar o valor completo sem desmontá-lo.

- `scheduled:<id>`: ocorrência concreta em `treinos_agendados`.
- `recurring:<id>:<YYYY-MM-DD>`: ocorrência de `treinos_cronograma` materializada para uma data original.

O mesmo domínio é usado pelo Web e pelo Mobile. Templates usam `treinos_modelo`, exercícios usam `exercicios`, execução usa `sessoes_treino`, e Activities continuam em `registros_atividade`.

## Status e origem

Status de workout expostos nesta v1: `rascunho`, `publicado`, `concluido`, `cancelado`.

Uma data passada não muda automaticamente o status para concluído.

Origens de workout: `usuario`, `treinador`, `cronograma`. Sessões usam `scheduled`, `recurring` ou `manual` como origem de execução.

## Calendário Dia / Semana / Mês

### GET `/workouts/schedule?from=YYYY-MM-DD&to=YYYY-MM-DD`

Intervalo inclusivo de até 94 dias. O mesmo endpoint atende dia, semana e mês visível. A resposta é leve: não carrega a árvore de exercícios, séries executadas, histórico ou GPS.

Exemplo de corrida agendada:

```json
{
  "id": "scheduled:abc123",
  "kind": "scheduled",
  "title": "Rodagem leve",
  "sport": {"id":"run","slug":"corrida","name":"Corrida","family":"running","route_capable":true},
  "date": "2026-09-15",
  "time": "07:00",
  "planned_duration_s": 2700,
  "planned_distance_m": 7000,
  "status": "publicado",
  "source": "usuario",
  "template_id": null,
  "has_structure": false,
  "exercise_count": 0,
  "activity": null,
  "capabilities": {
    "can_edit": true,
    "can_reschedule": true,
    "can_cancel": true,
    "can_delete": true,
    "can_complete_manually": true,
    "can_start_session": false,
    "can_resume_session": false,
    "can_quick_register": true,
    "can_start_gps": true,
    "preferred_execution": "gps"
  }
}
```

Workout sem horário retorna `"time": null`.

## Workout detail

### GET `/workouts/{id}`

Retorna metadados, autoria, template, cronograma, recorrência, estrutura planejada, sessão/Activity vinculadas, permissões, capabilities e `version` quando disponível.

Exemplo de academia:

```json
{
  "id": "scheduled:strength123",
  "kind": "scheduled",
  "title": "Treino A",
  "date": "2026-09-15",
  "time": "18:00",
  "status": "publicado",
  "source": "usuario",
  "template_id": "template123",
  "structure": {
    "exercise_count": 2,
    "blocks": [
      {
        "label": "A",
        "exercises": [
          {"id":"supino","name":"Supino reto","sets":4,"repetitions":"10","load":"70 kg","rest_s":90,"block":"A","order":1}
        ]
      }
    ],
    "exercises": [
      {"id":"supino","name":"Supino reto","sets":4,"repetitions":"10","load":"70 kg","rest_s":90,"block":"A","order":1}
    ]
  },
  "activity": null,
  "session": null,
  "version": "opaque-version",
  "capabilities": {
    "can_edit": true,
    "can_reschedule": true,
    "can_cancel": true,
    "can_delete": true,
    "can_complete_manually": true,
    "can_start_session": true,
    "can_resume_session": false,
    "can_quick_register": true,
    "can_start_gps": false,
    "preferred_execution": "workout_session"
  }
}
```

## Criar workout

### POST `/workouts`

Cria workout pessoal avulso, a partir de template, ou recorrente quando `recurrence` é fornecido. `Idempotency-Key` é opcional; quando enviado, retry com o mesmo payload retorna o mesmo workout e payload diferente com a mesma chave retorna `409 idempotency_conflict`.

Exemplo de treino de academia criado diretamente no calendário:

```json
{
  "title": "Treino A",
  "sport": "musculacao",
  "date": "2026-09-15",
  "time": "18:00",
  "planned_duration_s": 3600,
  "objective": "Força",
  "structure": {
    "exercises": [
      {"exercise_id":"supino","sets":4,"repetitions":"10","load":"70 kg","rest_s":90,"block":"A"},
      {"exercise_id":"agachamento","sets":4,"repetitions":"8","load":"100 kg","rest_s":120,"block":"A"}
    ]
  }
}
```

`template_id` pode substituir título/modalidade/estrutura quando o template fornece esses dados. Se `structure` também for enviado, a estrutura recebida no POST prevalece no snapshot da ocorrência.

## Editor de workout

### PATCH `/workouts/{id}`

Workouts pessoais avulsos aceitam atualização de título, modalidade, data, horário, duração, distância, objetivo, notas, intensidade e estrutura. O salvamento de metadados + estrutura é transacional.

`structure.exercises` é a lista ordenada completa. Salvar a estrutura substitui atomicamente a estrutura planejada anterior.

Cada exercício aceita os campos suportados pelo domínio Web:

```json
{
  "exercise_id": "exercise-id",
  "sets": 4,
  "repetitions": "10",
  "load": "70 kg",
  "rest_s": 90,
  "block": "A",
  "cluster": "4+4",
  "duration_s": null,
  "distance_m": null,
  "intensity": null,
  "rpe": 7,
  "rir": 2,
  "tempo": "3-1-1",
  "cadence": null,
  "notes": null
}
```

Nesta versão, séries planejadas são representadas pela quantidade `sets` e pelos alvos comuns do exercício. Séries individuais com valores realizados são materializadas em `sessoes_treino_series` quando a sessão começa.

O cliente pode enviar `if_version` obtido no detalhe para detectar edição concorrente. Versão desatualizada retorna `409 state_conflict`.

DELETE `/workouts/{id}` possui semântica de cancelamento lógico, não remoção destrutiva do histórico.

## Recorrência

O domínio atual suporta recorrência semanal por uma linha de `treinos_cronograma`.

Criar uma série:

```json
{
  "title": "Academia terça",
  "sport": "musculacao",
  "date": "2026-09-15",
  "time": "18:00",
  "planned_duration_s": 3600,
  "structure": {"exercises": []},
  "recurrence": {
    "frequency": "weekly",
    "interval": 1,
    "schedule_id": "schedule-id",
    "start_date": "2026-09-15",
    "end_date": "2026-12-15"
  }
}
```

PATCH de uma ocorrência recorrente aceita `scope` igual a `this`, `future` ou `all`. Alteração isolada de estrutura em `scope=this` não existe no domínio Web atual; estrutura pode mudar em `future` ou `all`. Para uma ocorrência com estrutura completamente distinta, o cliente pode cancelar somente aquela ocorrência e criar um workout avulso.

O Core não implementa RRULE nem agrupa terça+quinta em uma única série. Dois dias semanais são duas definições recorrentes dentro do mesmo cronograma.

## Cronogramas

### GET `/workout-schedules`
### POST `/workout-schedules`

Lista ou cria contêineres de cronograma pessoais utilizados pela engine Web de recorrência.

## Templates / biblioteca

### GET `/workout-templates?page=1&limit=25&q=...&sport=...`
### POST `/workout-templates`
### GET `/workout-templates/{id}`
### PATCH `/workout-templates/{id}`
### DELETE `/workout-templates/{id}`

DELETE arquiva o template. POST/PATCH aceitam a mesma `structure` do editor de workout e salvam a estrutura atomicamente.

Exemplo de template:

```json
{
  "title": "Upper A",
  "sport": "musculacao",
  "objective": "Hipertrofia",
  "structure": {
    "exercises": [
      {"exercise_id":"supino","sets":4,"repetitions":"8-10","load":"70 kg","rest_s":120,"block":"Peito"}
    ]
  }
}
```

Os aliases antigos GET `/workouts/templates` e GET `/workouts/templates/{id}` permanecem disponíveis por compatibilidade.

## Catálogo de exercícios

### GET `/exercises`

Parâmetros: `q`, `page`, `limit`, `sport`, `category`, `muscle`.

Retorna exercícios do sistema + exercícios pessoais do usuário. Exercícios de outro usuário nunca aparecem.

### GET `/exercises/{id}`
### POST `/exercises`
### PATCH `/exercises/{id}`
### DELETE `/exercises/{id}`

POST cria exercício personalizado usando o domínio Web. PATCH/DELETE só podem alterar exercício personalizado do próprio usuário. DELETE arquiva.

O domínio atual possui nome, descrição, categorias, modalidades, músculos primários/secundários e URLs de mídia. Não há relação canônica exercício↔equipamento nem aliases estruturados nesta versão; por isso a API não fabrica esses campos/filtros.

## Histórico de exercício

### GET `/exercises/{id}/history?limit=5`

Retorna últimas execuções reais obtidas de `series_exercicio_atividade` + `registros_atividade` e `best_load_kg` quando existe.

```json
{
  "data": [
    {
      "activity_id": "activity-id",
      "date": "2026-09-12",
      "title": "Treino A",
      "sets": [
        {"number":1,"repetitions":10,"load_kg":67.5,"rir":2,"rpe":8,"completed":true}
      ]
    }
  ],
  "meta": {"limit":5,"best_load_kg":75}
}
```

## Sessão ativa

### GET `/workout-sessions/current`

Sem sessão: `{"data":null}`.

### POST `/workouts/{id}/start`

O Core resolve `scheduled` ou `recurring`. Uma conta só pode ter uma sessão ativa.

Sessão ativa:

```json
{
  "id": "session-id",
  "workout_id": "scheduled:strength123",
  "title": "Treino A",
  "source": "scheduled",
  "status": "ativo",
  "progress": {"exercises_completed":0,"exercises_total":1,"sets_completed":1,"sets_total":4},
  "exercises": [
    {
      "id": "session-exercise-id",
      "exercise_id": "supino",
      "name": "Supino reto",
      "block": "A",
      "planned": {"sets":4,"repetitions":"10","load":"70 kg","rest_s":90},
      "sets": [
        {"id":"set-id","number":1,"planned_repetitions":"10","planned_load":"70 kg","actual_repetitions":"10","actual_load":"70 kg","completed":true}
      ],
      "history": {"last":{"date":"2026-09-10","sets_total":4},"best_load":"75 kg"}
    }
  ]
}
```

## Séries e exercícios da sessão

### PATCH `/workout-sessions/{session_id}/sets/{set_id}`

```json
{"repetitions":"10","load":"72.5 kg","propagate_load":true,"edited_field":"load"}
```

### POST `/workout-sessions/{session_id}/sets/{set_id}/toggle`
### POST `/workout-sessions/{session_id}/exercises/{exercise_id}/toggle`
### POST `/workout-sessions/{session_id}/mark-all`

A propagação de carga, conclusão de exercício e locks são os mesmos da execução Web.

Timer de descanso é responsabilidade de UI do cliente; o Core fornece `planned.rest_s`. Não existe timer persistido no servidor.

## Quick register

### POST `/workouts/{id}/quick-register`

Requer `Idempotency-Key` entre 8 e 128 caracteres.

```json
{
  "performed_date": "2026-09-15",
  "start_time": "18:00",
  "duration_min": 45,
  "intensity": "moderado",
  "feeling": 4,
  "notes": "Treino concluído"
}
```

Cria a Activity canônica no Core e vincula o workout. A chave idempotente e o lock da própria ocorrência protegem retries e concorrência entre dispositivos.

## Finish e cancel da sessão

### POST `/workout-sessions/{session_id}/finish`

```json
{
  "started_at_local": "2026-09-15T18:02",
  "ended_at_local": "2026-09-15T19:05",
  "intensity": "moderado",
  "feeling": 4,
  "notes": "Boa execução"
}
```

Finish usa transaction/row lock e estado da sessão. Retry após conclusão retorna a mesma Activity em vez de criar outra.

### POST `/workout-sessions/{session_id}/cancel`

Cancela a execução em andamento. Isso é diferente de cancelar o workout planejado.

## Workout ↔ Activity e GPS

`POST /activities` aceita `workout_id` opcional. O Core valida ownership, ocorrência e modalidade. O ID opaco não é token efêmero e pode ser persistido pelo Android para publicação posterior via WorkManager.

Fluxo GPS:

```json
{
  "workout_id": "scheduled:run123",
  "sport": "corrida",
  "started_at": "2026-09-15T07:00:00-03:00",
  "ended_at": "2026-09-15T07:45:00-03:00",
  "gps": {"points": []}
}
```

Após publicação, Workout detail aponta para `activity.id` e Activity detail devolve a referência de workout quando existe.

Sessões de força não criam GPS falso. Finish persiste exercícios/séries na Activity canônica.

## Capabilities

O cliente deve usar capabilities do Core em vez de inferir experiência pela modalidade:

- `can_edit`
- `can_reschedule`
- `can_cancel`
- `can_delete`
- `can_complete_manually`
- `can_start_session`
- `can_resume_session`
- `can_quick_register`
- `can_start_gps`
- `preferred_execution`: `workout_session`, `gps`, `quick_register` ou `null`

## Erros

A API sempre retorna JSON. Códigos relevantes:

- `authentication_required` / respostas 401 da autenticação v1;
- `forbidden` 403 para recurso visível mas não alterável;
- `not_found` 404 para recurso inexistente ou não acessível;
- `validation_error` 422;
- `state_conflict` 409 para `if_version` obsoleto;
- `active_session_exists` 409;
- `invalid_state` 409;
- `idempotency_conflict` 409.

## Cache, paginação e offline

Calendário usa range inclusivo e limite máximo de 94 dias em vez de paginação. Templates e catálogo de exercícios são paginados. Workout/template/exercise editáveis expõem `updated_at` e `version` quando disponíveis.

IDs de workout são estáveis para fila offline. Criação de workout aceita Idempotency-Key opcional; quick register exige Idempotency-Key; Activities mantêm idempotência própria; finish é idempotente pelo estado/lock da sessão.

## Limitações reais do domínio v1

- Recorrência é semanal com intervalo 1 por definição de treino; não há RRULE genérico.
- Terça+quinta são duas definições dentro de um cronograma, não uma única regra multiweekday.
- Uma ocorrência recorrente isolada pode alterar metadados via `scope=this`, mas não possui snapshot estrutural próprio; mudança de estrutura usa `future/all`.
- Planejamento de séries usa quantidade + alvos comuns por exercício. Não há alvos diferentes para cada série planejada antes de iniciar a sessão.
- O catálogo não possui relação estruturada exercício↔equipamento nem aliases canônicos.
- O timezone individual por usuário ainda não existe; o Core usa `America/Sao_Paulo`.

## Institutional Athlete Surface v1

O Core pode compor prescriptions institucionais na mesma API de workouts. Essa extensão é aditiva e fica completamente escondida enquanto `STRIDEBR_TEAMS_ENABLED=false`.

Novos valores possíveis:

- `source = teams`
- `kind = institutional`
- Workout Session iniciada por prescription institucional: `source = teams`

O ID continua opaco. A implementação inicial pode retornar `teams:<training_ref>`, mas o cliente deve armazenar e reenviar o valor completo sem interpretar o conteúdo depois de `teams:`.

Um item institucional pode incluir `institutional_context` com referências e nomes athlete-safe de organização, equipe, temporada, training, planning block e competição. Esse objeto é opcional e clientes que ainda não o modelam podem ignorá-lo.

Treinos institucionais são prescriptions read-only no Core. Na v1:

- `can_edit = false`
- `can_reschedule = false`
- `can_cancel = false`
- `can_delete = false`
- `can_start_gps = false`
- `can_quick_register = false`
- `can_complete_manually = false`
- `can_start_session` depende explicitamente da capability publicada pela projection institucional.

Quando `can_start_session=true`, `POST /workouts/{id}/start` cria uma Workout Session local do Core. A execução, séries realizadas e Activity resultante pertencem ao Core e ao usuário. O Core preserva apenas referências externas e um snapshot mínimo necessário para a execução. Nenhuma Activity é criada no domínio institucional.

Ao concluir uma sessão institucional, o Core consegue construir um acknowledgement lógico com `training_ref`, `recipient_ref`, `status`, timestamps e `activity_ref`. A v1 não envia esse payload para serviço externo. `activity_ref` é uma referência opaca e não concede permissão de leitura da Activity.

A feature está desligada por padrão. Com `STRIDEBR_TEAMS_ENABLED=false`, calendário, detalhe e Workout Session mantêm o comportamento anterior e o provider institucional não é consultado.
