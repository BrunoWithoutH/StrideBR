# API mobile do StrideBR Core

O aplicativo StrideBR conversa exclusivamente com a API HTTP versionada do Core:
`https://stridebr.com.br/api/v1`. Ele nunca acessa PostgreSQL, secrets de integrações
ou sessões PHP do site.

## Endpoints da primeira Alpha externa

- `GET /health`
- `GET /meta`
- `POST /auth/login`
- `POST /auth/refresh`
- `POST /auth/logout`
- `GET /me`
- `GET /activities`
- `GET /activities/{id}`
- `POST /activities`
- `GET /workouts/schedule`
- `POST /workouts`
- `GET /workouts/{id}`
- `PATCH /workouts/{id}`
- `POST /workouts/{id}/complete`
- `POST /workouts/{id}/cancel`
- `GET /workouts/templates`
- `GET /workouts/templates/{id}`
- `GET /workout-sessions/current`
- `POST /workouts/{id}/start`
- `POST /workouts/{id}/quick-register`
- `PATCH /workout-sessions/{session_id}/sets/{set_id}`
- `POST /workout-sessions/{session_id}/sets/{set_id}/toggle`
- `POST /workout-sessions/{session_id}/exercises/{exercise_id}/toggle`
- `POST /workout-sessions/{session_id}/mark-all`
- `POST /workout-sessions/{session_id}/finish`
- `POST /workout-sessions/{session_id}/cancel`

As rotas são JSON-only. Sucesso usa `{ "data": ... }`; falhas usam
`{ "error": { "code", "message", "fields"? } }`.

### Diagnóstico de build

`GET /meta` responde sem depender do PostgreSQL e inclui `api_version`, `server_time` e
`build`. O campo `build` vem exclusivamente de `STRIDEBR_BUILD` definido pelo deploy;
quando a variável não está configurada, o valor é `null`. O Core não executa Git em
runtime para descobrir commit. O Mobile pode exibir esse campo em telas de diagnóstico.

## Autenticação

Use `Authorization: Bearer <access_token>` nas rotas autenticadas. Access tokens
expiram em 15 minutos. Refresh tokens expiram em 30 dias, são armazenados somente
como SHA-256 no servidor e são rotacionados a cada uso. A sessão anterior é revogada
quando o refresh é aceito. Alteração de senha, desativação da conta ou incremento de
`sessao_versao` invalida as sessões da API.

O app deve guardar tokens somente no armazenamento seguro da plataforma (Android
Keystore / iOS Keychain), nunca em logs ou analytics. A API não depende da sessão PHP
do site e não usa CSRF para requisições autenticadas por Bearer token.

## Perfil

`GET /me` retorna a mesma identidade usada no Core web. O aplicativo não possui uma
conta paralela.

## Atividades

`GET /activities` aceita:

- `page`: inteiro >= 1;
- `limit`: 1–100, padrão 25;
- `sport`: slug da modalidade;
- `from`: ISO 8601;
- `to`: ISO 8601, limite superior exclusivo;
- `q`: busca pelo título.

A listagem retorna somente o resumo. Ela não carrega nem serializa o track GPS. Campos
sem dado real permanecem `null`; a API não fabrica distância, duração, elevação, ritmo
ou velocidade para modalidades/origens que não possuam essas métricas.

Exemplo de item de `GET /activities`:

```json
{
  "id": "01J...",
  "title": "Corrida noturna",
  "sport": { "id": "m_corrida", "slug": "corrida", "name": "Corrida" },
  "started_at": "2026-09-11T18:03:12-03:00",
  "ended_at": "2026-09-11T18:34:08-03:00",
  "status": "concluido",
  "visibility": "privado",
  "origin": "gps",
  "origin_provider": "stridebr_android",
  "perceived_effort": 7,
  "distance_m": 5012.4,
  "duration_s": 1856.0,
  "elevation_gain_m": 42.0,
  "average_speed_mps": 2.700647
}
```

`average_speed_mps` é derivada somente quando distância e duração confiáveis estão
disponíveis. Pace continua sendo responsabilidade do cliente. A resposta inclui
`pagination`.

`GET /activities/{id}` exige ownership neste contrato autenticado: trocar o ID por uma
atividade privada/de outro usuário retorna `404`. O detalhe retorna o mesmo resumo e,
quando disponíveis, `notes`, métricas adicionais, segmentos, equipamentos, privacidade
de rota, rota e metadados da gravação GPS.

Exemplo resumido de detalhe:

```json
{
  "data": {
    "id": "01J...",
    "title": "Corrida noturna",
    "sport": { "id": "m_corrida", "slug": "corrida", "name": "Corrida" },
    "started_at": "2026-09-11T18:03:12-03:00",
    "ended_at": "2026-09-11T18:34:08-03:00",
    "status": "concluido",
    "visibility": "privado",
    "origin": "gps",
    "origin_provider": "stridebr_android",
    "perceived_effort": 7,
    "distance_m": 5012.4,
    "duration_s": 1856.0,
    "elevation_gain_m": 42.0,
    "elevation_min_m": 482.1,
    "elevation_max_m": 513.7,
    "average_speed_mps": 2.700647,
    "route_privacy": { "hide_start_m": 0, "hide_end_m": 0 },
    "route": {
      "mode": "gps",
      "points": [
        {
          "lat": -27.3581,
          "lon": -53.3942,
          "altitude_m": 491.2,
          "accuracy_m": 4.8,
          "timestamp_ms": 1789160592000
        }
      ],
      "distance_m": 5012.4,
      "elevation_gain_m": 42.0,
      "elevation_loss_m": null,
      "elevation_min_m": 482.1,
      "elevation_max_m": 513.7,
      "elevation_source": "gps_app_dispositivo"
    },
    "gps": {
      "measured_distance_m": 5008.9,
      "points_received": 680,
      "points_accepted": 668,
      "points_rejected": 12,
      "accuracy_avg_m": 7.4,
      "accuracy_best_m": 3.1,
      "accuracy_worst_m": 28.0,
      "visibility_gaps": 0,
      "user_adjusted": false
    },
    "segments": [],
    "equipment": [],
    "workout": null
  }
}
```

`route` pode ser `null` para atividades manuais, antigas, importadas ou qualquer
atividade sem track persistido. Os campos opcionais de cada ponto também podem ser
`null`. O Core não infere altitude, precisão ou timestamp por ponto quando esses dados
não existirem.

A rota completa pertence somente ao detalhe. O contrato não muda as regras de
compartilhamento do Core: endpoints autenticados pessoais continuam ownership-scoped e
os fluxos públicos/compartilhados mantêm sua própria sanitização de rota, inclusive
ocultação de início/fim. `route_privacy` informa ao dono a configuração persistida sem
enfraquecer essa política.

Atividades podem vir de `gps`, `manual`, `importacao` ou `api`, com
`origin_provider` identificando o provedor quando conhecido. O Mobile deve aceitar
métricas e track ausentes em qualquer origem. Datas são ISO 8601 com offset e o cliente
deve preservar o offset recebido.

## Publicar uma gravação GPS

`POST /activities` publica uma gravação GPS salva localmente no Android como uma
atividade normal do Core.

Headers obrigatórios:

```text
Authorization: Bearer <access_token>
Content-Type: application/json
Idempotency-Key: <chave-estável-da-gravação-local>
```

`Idempotency-Key` deve ter entre 8 e 128 caracteres usando letras, números, `.`, `_`,
`:`, ou `-`. O app deve manter a mesma chave ao repetir o envio da mesma gravação.
A primeira criação retorna `201`; uma repetição já salva retorna `200` com
`reused: true` e o mesmo ID remoto.

Payload:

```json
{
  "workout_id": null,
  "sport": "corrida",
  "title": "Corrida noturna",
  "notes": "Opcional",
  "visibility": "privado",
  "perceived_effort": 7,
  "started_at": "2026-09-11T18:03:12-03:00",
  "ended_at": "2026-09-11T18:34:08-03:00",
  "metrics": {
    "distance_m": 5012.4,
    "duration_s": 1856.0,
    "elevation_gain_m": 42.0,
    "elevation_min_m": 482.1,
    "elevation_max_m": 513.7
  },
  "gps": {
    "points": [
      { "lat": -27.3581, "lon": -53.3942, "altitude_m": 491.2, "accuracy_m": 4.8, "timestamp_ms": 1789160592000 },
      { "lat": -27.3582, "lon": -53.3940, "altitude_m": 491.5, "accuracy_m": 4.2, "timestamp_ms": 1789160594000 }
    ],
    "measured_distance_m": 5008.9,
    "points_received": 680,
    "points_rejected": 12,
    "accuracy_avg_m": 7.4,
    "accuracy_best_m": 3.1,
    "accuracy_worst_m": 28.0,
    "visibility_gaps": 0
  },
  "privacy": {
    "hide_route_start_m": 0,
    "hide_route_end_m": 0
  },
  "segments": []
}
```

Obrigatórios:

- `sport`: slug ou ID de modalidade ativa que permita rota;
- `started_at`: ISO 8601 com offset;
- `ended_at`: ISO 8601 com offset e não anterior ao início;
- `gps.points`: entre 2 e 2000 pontos válidos, cada um com `lat` e `lon`.

Opcionais:

- `workout_id` — ID opaco retornado pela API de Treinos; quando presente, o Core valida ownership/modalidade e vincula a Activity ao planejamento;
- `title` — quando vazio o Core usa o nome da modalidade;
- `notes`;
- `visibility` — `privado`, `amigos` ou `publico`; quando omitido usa o padrão da conta;
- `perceived_effort` — inteiro de 1 a 10;
- `metrics.distance_m` — se omitida, o Core calcula pela rota;
- `metrics.duration_s` — se omitida, usa a diferença entre os timestamps;
- métricas de elevação;
- `gps.points[].altitude_m`, `accuracy_m` e `timestamp_ms` — metadata opcional por ponto, persistida alinhada ao track;
- métricas de qualidade em `gps`;
- `privacy.hide_route_start_m` / `hide_route_end_m` — 0 a 10000 m;
- `segments` — trechos da gravação, quando o app tiver essa informação.

A atividade é persistida pelo mesmo domínio usado pelo Core, com `origem = gps`. Não
existe tabela de “atividade mobile”. Ela aparece no site e nas leituras posteriores da
API normalmente.

Resposta de criação:

```json
{
  "data": {
    "id": "ID_DA_ATIVIDADE",
    "activity": { "id": "ID_DA_ATIVIDADE" },
    "reused": false
  }
}
```

Quando a mesma `Idempotency-Key` já foi salva para o usuário, o Core retorna a mesma
atividade com `reused: true` e não cria duplicata.

## MOBILE WORKOUTS V1 CONTRACT

A API de Treinos não cria um domínio paralelo para o aplicativo. Ela expõe conceitos já
existentes no Core:

- `treinos_cronograma`: definição recorrente pertencente a um cronograma pessoal;
- `treinos_modelo`: biblioteca/template reutilizável;
- `treinos_agendados`: ocorrência concreta em uma data, incluindo treino pessoal ou prescrição;
- `sessoes_treino`: execução de treino e ponte canônica para `registros_atividade`.

Os IDs de workout retornados pela API são opacos. O cliente deve armazená-los e reenviá-los
sem tentar desmontar prefixos ou reconstruí-los.

### Calendário Dia/Semana/Mês

`GET /workouts/schedule?from=YYYY-MM-DD&to=YYYY-MM-DD` usa intervalo inclusivo e aceita até
94 dias. O mesmo endpoint atende dia (`from == to`), semana e o intervalo visível de um mês.
Não há paginação nesse contrato de calendário porque o range já é limitado.

Exemplo:

```json
{
  "data": [
    {
      "id": "scheduled:AbCdEf123456789012345",
      "kind": "scheduled",
      "title": "Intervalado 5 × 1 km",
      "sport": {
        "id": "m_corrida",
        "slug": "corrida",
        "name": "Corrida",
        "family": "endurance",
        "route_capable": true
      },
      "date": "2026-09-17",
      "time": "17:30",
      "planned_duration_s": 3600,
      "planned_distance_m": 8000,
      "status": "publicado",
      "source": "usuario",
      "template_id": "TEMPLATE_ID",
      "updated_at": "2026-09-14T16:05:00-03:00",
      "has_structure": true,
      "exercise_count": 5,
      "activity": null
    },
    {
      "id": "scheduled:GhIjKl123456789012345",
      "kind": "scheduled",
      "title": "Mobilidade",
      "sport": { "id": "m_geral", "slug": "geral", "name": "Geral" },
      "date": "2026-09-18",
      "time": null,
      "planned_duration_s": 1800,
      "planned_distance_m": null,
      "status": "publicado",
      "source": "treinador",
      "template_id": null,
      "updated_at": "2026-09-14T15:00:00-03:00",
      "has_structure": false,
      "exercise_count": 0,
      "activity": null
    }
  ],
  "meta": {
    "from": "2026-09-14",
    "to": "2026-09-20",
    "timezone": "America/Sao_Paulo",
    "count": 2
  }
}
```

A listagem é deliberadamente leve. Ela não devolve a árvore completa de exercícios.
`template_id` aparece quando a ocorrência deriva de um modelo e `updated_at` usa ISO 8601
quando o domínio possui timestamp de atualização. Um horário ausente é `null`; a API nunca
usa `00:00` como substituto. A data e o horário são
wall-clock values no timezone atual do ecossistema, `America/Sao_Paulo`, portanto um treino
`2026-09-17` às `23:30` continua pertencendo ao dia 17. O Core ainda não possui timezone
individual por usuário; isso é uma limitação explícita desta versão.

Os status expostos são os status reais do domínio: `rascunho`, `publicado`, `concluido` e
`cancelado`. Uma data passada não muda o status para concluído. `source` identifica
`usuario`, `treinador`, `cronograma` ou, quando a Institutional Athlete Surface estiver habilitada, `teams`. Workouts `source=teams` usam `kind=institutional`, são read-only e podem trazer `institutional_context` opcional. Com `STRIDEBR_TEAMS_ENABLED=false`, a API continua retornando somente as fontes Core anteriores.

### Detalhe

`GET /workouts/{id}` retorna a ocorrência ownership-scoped e, quando existem, objetivo,
notas, métricas planejadas, autoria, template de origem, cronograma, exercícios e Activity
realizada.

```json
{
  "data": {
    "id": "scheduled:AbCdEf123456789012345",
    "kind": "scheduled",
    "title": "Intervalado 5 × 1 km",
    "sport": { "id": "m_corrida", "slug": "corrida", "name": "Corrida" },
    "date": "2026-09-17",
    "time": "17:30",
    "timezone": "America/Sao_Paulo",
    "notes": "Recuperação leve entre os tiros",
    "objective": "Velocidade",
    "planned_duration_s": 3600,
    "planned_distance_m": 8000,
    "intensity": "forte controlado",
    "status": "publicado",
    "source": "usuario",
    "author": { "id": "USER_ID", "name": "Bruno", "username": "bruno" },
    "template_id": "TEMPLATE_ID",
    "schedule_id": null,
    "schedule_workout_id": null,
    "structure": {
      "exercise_count": 1,
      "exercises": [
        {
          "id": null,
          "name": "Tiro de 1 km",
          "sets": 5,
          "repetitions": "1 km",
          "rest": "2 min",
          "order": 1
        }
      ]
    },
    "session": null,
    "activity": null,
    "created_at": "2026-09-14T15:58:00-03:00",
    "updated_at": "2026-09-14T16:05:00-03:00",
    "permissions": {
      "can_edit": true,
      "can_reschedule": true,
      "can_cancel": true,
      "can_complete": true
    }
  }
}
```

Campos não existentes no domínio permanecem `null`/ausentes; a API não fabrica blocos,
séries, targets ou métricas. `session` referencia `sessoes_treino` somente quando existe uma
execução persistida; `activity` continua sendo apenas a referência para Activities v2.
`created_at` e `updated_at` são timestamps absolutos ISO 8601 quando a entidade persistida
possui esses valores.

### Criar e editar treino pessoal

`POST /workouts` cria uma ocorrência pessoal em `treinos_agendados`.

```json
{
  "title": "Rodagem leve",
  "sport": "corrida",
  "date": "2026-09-17",
  "time": "17:30",
  "planned_duration_s": 3600,
  "planned_distance_m": 8000,
  "objective": "Base aeróbica",
  "intensity": "leve",
  "notes": "Opcional"
}
```

`time`, duração, distância, objetivo, intensidade e notas são opcionais. A implementação v1
reutiliza `duracao_prevista_min` do Core; por isso `planned_duration_s` precisa ser múltiplo
de 60 nesta versão. Distância é canônica em metros.

Também é possível agendar a partir de um template pessoal:

```json
{
  "template_id": "TEMPLATE_ID",
  "date": "2026-09-17",
  "time": null
}
```

Nesse caso título, modalidade e estrutura podem ser herdados da biblioteca. A ocorrência
preserva `template_id`, mas possui snapshots dos exercícios para manter histórico. A listagem
de templates também retorna `updated_at`, permitindo ao cliente invalidar cache sem abrir
cada modelo.

`PATCH /workouts/{id}` edita somente treino pessoal criado pelo próprio usuário e ainda em
estado editável. Pode alterar `title`, `sport`, `date`, `time`, `planned_duration_s`,
`planned_distance_m`, `notes`, `objective` e `intensity`. Enviar `null` em campo opcional
compatível remove o valor. Prescrições de treinador e ocorrências recorrentes não são
mutáveis por esse endpoint; `permissions` no detalhe informa o que o cliente pode oferecer.

### Concluir e cancelar

`POST /workouts/{id}/complete` marca um `treinos_agendados` publicado como `concluido`.
Isso atende treino não-GPS e não cria uma Activity artificial.

`POST /workouts/{id}/cancel` faz cancelamento lógico. Treinos concluídos não perdem vínculo
histórico. Para ocorrências recorrentes, a API reutiliza a exceção de ocorrência do cronograma.

### Activity linking

`POST /activities` aceita `workout_id` opcional. O Android pode abrir um workout, guardar seu
ID localmente e continuar gravando offline; o recorder GPS não depende de manter conexão com
a API. Na publicação posterior, o mesmo ID é enviado junto com a Activity:

```json
{
  "workout_id": "scheduled:AbCdEf123456789012345",
  "sport": "corrida",
  "started_at": "2026-09-17T17:32:00-03:00",
  "ended_at": "2026-09-17T18:20:00-03:00",
  "gps": {
    "points": [
      { "lat": -27.3581, "lon": -53.3942 },
      { "lat": -27.3582, "lon": -53.3940 }
    ]
  }
}
```

O Core valida ownership e modalidade. Para `scheduled`, a ligação usa `sessoes_treino` com
`idagendamento_origem`; para `recurring`, usa os campos já existentes de cronograma em
`registros_atividade`. O treino passa a aparecer como concluído e seu detalhe devolve
`activity.id`. O detalhe da Activity também devolve `workout` quando houver vínculo. A
Idempotency-Key da Activity continua valendo e reenvio não duplica a ponte.

`POST /workouts` não possui Idempotency-Key própria nesta v1. O Core hoje só tem
idempotência canônica para publicação de Activities/GPS; não foi criado um segundo mecanismo
genérico apenas para Workouts. O cliente deve tratar timeout de criação consultando novamente
a janela do calendário antes de repetir uma criação incerta. IDs remotos e `updated_at`
continuam estáveis para leituras e cache local.

### Biblioteca/templates

`GET /workouts/templates?page=1&limit=25&q=intervalado` lista de forma paginada somente a
biblioteca pessoal ativa. `GET /workouts/templates/{id}` retorna estrutura/exercícios. Um
usuário nunca abre template de outra conta. A criação com `template_id` é a operação v1 de
“usar/agendar” um template.

### Recorrência

O Core já possui recorrência semanal por `treinos_cronograma`, período de vigência e exceções
de ocorrência. `GET /workouts/schedule` materializa essas ocorrências junto com
`treinos_agendados`; não existe RRULE novo nem engine Mobile paralela. A API v1 ainda não
cria/edita regras recorrentes. Isso permanece no cronograma Web e deixa espaço para um
contrato futuro sem alterar os IDs atuais.

### Permissões e limitações

Todas as leituras e comandos são autenticados por Bearer token e ownership-scoped. Treinos
prescritos preservam `author`/`source` e não podem ser editados pelo atleta. Teams e calendário
institucional não participam deste contrato.

Limitações explícitas da v1:

- timezone único do ecossistema: `America/Sao_Paulo`;
- duração planejada pessoal com precisão de minutos;
- recorrência é somente leitura/cancelamento de ocorrência pela API, sem criação de regra;
- não há sync Google Calendar/iCal;
- não há push, Teams, ranking ou IA;
- `POST /workouts` ainda não possui Idempotency-Key própria; o cliente deve reconciliar a
  janela de calendário após timeout antes de repetir uma criação;
- a API não cria uma Activity ao concluir manualmente um treino não-GPS.

## MOBILE WORKOUT SESSION V1

A execução nativa reutiliza a mesma engine usada pelo Web. O transporte Web continua usando
sessão PHP/CSRF; o aplicativo usa Bearer Token, mas ambos chamam o mesmo serviço de domínio
para snapshot, séries, histórico, conclusão e criação da Activity canônica.

### Sessão ativa

`GET /workout-sessions/current` retorna `{ "data": null }` quando não existe treino em
andamento. Com sessão ativa, `data` contém o ID da sessão, `workout_id` opaco quando a sessão
veio de calendário, origem, timestamps, ocorrência planejada, progresso, exercícios, séries e
histórico de exercício. `?history=0` permite omitir a busca histórica quando o cliente não
precisar dela.

Exemplo resumido:

```json
{
  "data": {
    "id": "SESSION_ID",
    "workout_id": "scheduled:WORKOUT_ID",
    "title": "Treino A",
    "source": "scheduled",
    "status": "ativo",
    "started_at": "2026-09-14T18:02:00-03:00",
    "activity": null,
    "planned_occurrence": {
      "original_date": null,
      "date": "2026-09-14",
      "time": "18:00",
      "timezone": "America/Sao_Paulo"
    },
    "progress": {
      "exercises_completed": 0,
      "exercises_total": 1,
      "sets_completed": 1,
      "sets_total": 4
    },
    "exercises": [
      {
        "id": "SESSION_EXERCISE_ID",
        "exercise_id": null,
        "name": "Supino reto",
        "order": 1,
        "block": "A",
        "cluster": null,
        "completed": false,
        "planned": {
          "sets": 4,
          "repetitions": "10",
          "load": "70 kg",
          "rest": "90 s"
        },
        "sets": [
          {
            "id": "SET_ID",
            "number": 1,
            "completed": true,
            "repetitions": "10",
            "load": "67.5 kg",
            "completed_at": "2026-09-14T18:08:00-03:00"
          }
        ],
        "history": {
          "last": {
            "date": "2026-09-10",
            "sets_completed": 4,
            "sets_total": 4,
            "repetitions": "10",
            "load": "67.5 kg",
            "sets": []
          },
          "best_load": "75 kg"
        }
      }
    ]
  }
}
```

`planned` é a prescrição congelada no início da sessão. `sets[].repetitions` e
`sets[].load` são valores realizados. Quantidade de práticas anteriores não é convertida em
score; o histórico serve apenas como referência factual.

### Iniciar

`POST /workouts/{id}/start` recebe o mesmo ID opaco `scheduled:...` ou
`recurring:...:YYYY-MM-DD` retornado pelo calendário. O Core resolve internamente qual regra
Web usar. O cliente não chama `start_scheduled` nem precisa conhecer tabelas internas.

Só uma sessão `ativo` pode existir por usuário. Uma segunda tentativa retorna `409` com
`active_session_exists` e referência à sessão que já está em andamento. O snapshot inclui
exercícios, ordem, bloco, cluster, séries, reps, carga, descanso, duração, distância,
intensidade, RPE, RIR, tempo e cadência quando esses dados existem na prescrição.

`GET /workouts/{id}` inclui `capabilities` para a UI não inferir o fluxo pelo nome do esporte:

```json
{
  "capabilities": {
    "can_start_session": true,
    "can_resume_session": false,
    "can_quick_register": true,
    "can_start_gps": false,
    "preferred_execution": "workout_session"
  }
}
```

`route_capable` e a família canônica do catálogo definem a preferência. Modalidades
`strength` com estrutura preferem `workout_session`; modalidades com rota podem preferir
`gps`; estrutura existente continua habilitando sessão mesmo fora de strength.

### Atualizar e concluir séries

`PATCH /workout-sessions/{session_id}/sets/{set_id}` aceita:

```json
{
  "repetitions": "10",
  "load": "67.5 kg",
  "propagate_load": true,
  "edited_field": "load"
}
```

`propagate_load=true` reutiliza a mesma regra Web de propagação para séries posteriores não
concluídas. `edited_field` pode ser `load`, `reps` ou omitido.

`POST /workout-sessions/{session_id}/sets/{set_id}/toggle` usa
`{ "completed": true }`. Concluir a série grava `completed_at`. Desmarcar limpa a conclusão.

`POST /workout-sessions/{session_id}/exercises/{exercise_id}/toggle` marca/desmarca o
exercício e suas séries. `POST /workout-sessions/{session_id}/mark-all` evita que o cliente
precise emitir uma request por série.

Todos os IDs são ownership-scoped. Série/exercício de outra sessão ou usuário não pode ser
alterado.

### Registro rápido

`POST /workouts/{id}/quick-register` representa o mesmo fluxo Web de Registrar rapidamente.
Ele exige `Idempotency-Key` para tornar retry de rede seguro.

```json
{
  "performed_date": "2026-09-14",
  "start_time": "18:00",
  "duration_min": 45,
  "intensity": "moderado",
  "feeling": 4,
  "notes": "Treino concluído sem acompanhamento série a série"
}
```

`performed_date` pode ser omitido e, nesse caso, usa a data planejada do workout.
`start_time` pode ser omitido quando o workout já possui horário planejado, reutilizando esse
horário. Quando o workout não possui horário, o cliente precisa informar `start_time`; o Core
não inventa `00:00` nem usa a hora atual silenciosamente.

A primeira chamada retorna `201`. Repetir a mesma chave e mesmo payload retorna `200` com a
mesma Activity e `reused=true`. Reutilizar a chave com payload diferente retorna
`409 idempotency_conflict`. Além do lock da chave, o Core bloqueia a própria ocorrência
planejada durante a criação; duas chaves diferentes concorrendo pelo mesmo workout não podem
gerar duas Activities. Se ele já estiver realizado, a Activity existente é reutilizada. A tabela
de idempotência pertence ao usuário e não representa um segundo domínio de treino.

O registro rápido cria a Activity no Core, preserva ocorrência/cronograma/agendamento e
concilia o workout. O cliente nunca cria uma Activity de força localmente.

### Finalizar

`POST /workout-sessions/{session_id}/finish` aceita campos opcionais:

```json
{
  "started_at_local": "2026-09-14T18:02",
  "ended_at_local": "2026-09-14T19:05",
  "intensity": "moderado",
  "feeling": 4,
  "notes": "Boa execução"
}
```

Os horários são wall-clock values de `America/Sao_Paulo`. A engine Web continua validando
mínimo de 1 minuto, máximo de 24 horas e término futuro. Ao finalizar, o Core cria uma
`registros_atividade` canônica, persiste `series_exercicio_atividade`, relaciona a sessão,
conclui o agendamento quando aplicável e retorna o `activity.id`.

Finish é idempotente por estado da sessão + row lock transacional: depois de a sessão estar
`concluido` com `idregistro_atividade`, um retry devolve a mesma Activity com `reused=true`.
Não existe janela para criar uma segunda Activity pela mesma sessão.

### Cancelar

`POST /workout-sessions/{session_id}/cancel` muda uma sessão ativa para `cancelado`. Não
apaga registros históricos nem cancela automaticamente o workout planejado. Sessão de outro
usuário não pode ser cancelada.

### Erros

Além de `401`, os endpoints usam `422 validation_error` para payload inválido, `409
invalid_state` para transições incompatíveis, `409 active_session_exists` quando já existe
sessão ativa, `409 idempotency_conflict` para retry conflitante e `503 feature_disabled`
quando a execução de treinos estiver desativada no Core.

### Concorrência e offline

A unicidade parcial de sessão ativa no PostgreSQL continua sendo a autoridade contra dois
dispositivos iniciando ao mesmo tempo. Updates de série validam sessão ativa e ownership;
finish bloqueia a linha com `FOR UPDATE`. IDs de sessão/série são estáveis durante a execução.
O cliente pode guardar estado local para tolerar perda de rede, mas a reconciliação deve
sempre usar `GET /workout-sessions/current` antes de retomar mutações remotas.

## MOBILE WORKOUT EDITOR API

O editor Mobile de estrutura foi incorporado ao contrato consolidado `MOBILE TRAINING PLATFORM V1`.
O Android pode buscar/criar exercícios pessoais, criar/editar templates e salvar a estrutura completa
de workouts pessoais usando os mesmos campos e tabelas do editor Web. O contrato canônico e as
limitações reais da modelagem atual estão em [`MOBILE_TRAINING_API.md`](MOBILE_TRAINING_API.md).

## Transporte e segurança

Produção é `https://stridebr.com.br/api/v1`. Android nativo não depende de CORS de
browser e o Core não abre CORS global só para o app. A API nunca devolve stack trace em
produção e nenhum client secret, senha de banco, token OAuth de provider ou segredo de
webhook pertence ao APK.

OAuth de Strava e demais provedores continua no Core. Fluxos mobile de OAuth serão
contratos próprios quando forem expostos.

## MOBILE TRAINING PLATFORM V1

O contrato consolidado de calendário, editor, templates, catálogo de exercícios, sessões, séries, histórico, quick register e vínculo Workout ↔ Activity está em [`MOBILE_TRAINING_API.md`](MOBILE_TRAINING_API.md).

Esse contrato substitui, para implementação nova no Android, a necessidade de combinar manualmente as seções antigas de Mobile Workouts v1 e Mobile Workout Session v1. Os endpoints antigos compatíveis continuam válidos.
## MOBILE PROGRESS PLATFORM API V1

A API de Progresso Mobile reutiliza o mesmo domínio analítico do Core Web para Activities, modalidades, carga de treino, planejamento e séries executadas. O contrato canônico está em [`MOBILE_PROGRESS_API.md`](MOBILE_PROGRESS_API.md).

Ela expõe overview, séries temporais, distribuição por modalidade, calendário/heatmap, cardio por modalidade, força, evolução por exercício, aderência planejado × realizado e dashboard composto. Os endpoints trabalham por `from`/`to`, respeitam `America/Sao_Paulo`, mantêm `null` para métrica indisponível e `0` apenas para ausência real de ocorrência.
## ACTIVITY STREAMS + ACTIVITY ANALYSIS + PACER V1

Activities podem opcionalmente possuir timeline esportiva canônica para pace/velocidade, FC, altitude/grade, cadência, potência e temperatura quando os dados existirem. Latitude/longitude continuam pertencendo à rota; Streams não duplicam o track.

O contrato de ingestão/leitura, downsampling, splits e manual laps está em [`MOBILE_ACTIVITY_STREAMS_API.md`](MOBILE_ACTIVITY_STREAMS_API.md). A análise determinística versionada está em [`ACTIVITY_ANALYSIS.md`](ACTIVITY_ANALYSIS.md), e a fundação offline do Stride Pacer está em [`PACER.md`](PACER.md).

A Activity Detail expõe apenas `stream_capabilities`; gráficos usam `/activities/{id}/streams`, splits usam `/splits`, manual laps usam `/laps` e análise usa `/analysis`. Workouts podem referenciar `pacer_plan_id`. Streams continuam opcionais, portanto Activities antigas, Quick Register e Workout Session permanecem compatíveis.
