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

A resposta inclui `pagination`. `GET /activities/{id}` exige ownership: trocar o ID
por uma atividade de outro usuário retorna `404`.

Datas são ISO 8601 com offset. Consumidores devem preservar o offset recebido.

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
      { "lat": -27.3581, "lon": -53.3942 },
      { "lat": -27.3582, "lon": -53.3940 }
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
