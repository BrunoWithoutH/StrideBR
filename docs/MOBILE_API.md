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
    "equipment": []
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

## Transporte e segurança

Produção é `https://stridebr.com.br/api/v1`. Android nativo não depende de CORS de
browser e o Core não abre CORS global só para o app. A API nunca devolve stack trace em
produção e nenhum client secret, senha de banco, token OAuth de provider ou segredo de
webhook pertence ao APK.

OAuth de Strava e demais provedores continua no Core. Fluxos mobile de OAuth serão
contratos próprios quando forem expostos.
