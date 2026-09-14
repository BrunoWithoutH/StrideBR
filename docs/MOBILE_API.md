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

As rotas são JSON-only. Sucesso usa `{ "data": ... }`; falhas usam
`{ "error": { "code", "message", "fields"? } }`.

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
`usuario`, `treinador` ou `cronograma`.

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

## Transporte e segurança

Produção é `https://stridebr.com.br/api/v1`. Android nativo não depende de CORS de
browser e o Core não abre CORS global só para o app. A API nunca devolve stack trace em
produção e nenhum client secret, senha de banco, token OAuth de provider ou segredo de
webhook pertence ao APK.

OAuth de Strava e demais provedores continua no Core. Fluxos mobile de OAuth serão
contratos próprios quando forem expostos.
