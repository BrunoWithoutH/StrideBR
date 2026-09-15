# Mobile Activity Streams API v1

## Escopo

Activity Streams adiciona uma timeline esportiva canônica às Activities sem substituir o domínio existente de Activity ou rota GPS. O contrato foi desenhado para gráficos de pace, velocidade, frequência cardíaca, altitude, grade, cadência, potência futura e temperatura quando esses dados realmente existirem.

Todas as rotas são privadas, Bearer-only e ownership-scoped. Não existe endpoint público de Streams.

Timezone civil continua sendo `America/Sao_Paulo`, mas a ordenação da timeline não depende de horário civil. O eixo canônico é relativo à própria Activity.

## Modelo de dados

`registros_atividade` continua sendo a Activity canônica.

`rotas_atividade` continua sendo a fonte do track geográfico. Activity Streams não repete latitude/longitude. Quando uma amostra corresponde a um ponto da rota, `route_point_index` pode apontar para a posição correspondente.

A timeline persistida usa:

```text
elapsed_ms
moving_ms
distance_m
speed_mps
heart_rate_bpm
altitude_m
grade_pct
cadence
power_w
temperature_c
```

Metadata GPS esportivamente útil pode acompanhar a amostra:

```text
horizontal_accuracy_m
vertical_accuracy_m
speed_accuracy_mps
bearing_deg
gap_before_ms
route_point_index
source
```

Dados de diagnóstico GNSS de desenvolvimento não fazem parte desta timeline permanente.

## Raw, canonical e derived

O bundle recebido é normalizado pelo Core. O Core preserva medições reais e deriva somente o que possui base suficiente.

`pace` não é armazenado como verdade independente. Na leitura ele é derivado de `speed_mps`:

```text
pace_s_per_km = 1000 / speed_mps
```

Quando `speed_mps` não veio da fonte, o Core pode derivá-lo de distância e moving time monotônicos.

`grade_pct` é derivado de altitude suavizada e janela de distância. Não é calculado usando inclinação ponto-a-ponto em deslocamentos minúsculos.

O Core não recalcula o track geográfico ignorando a rota aceita pela pipeline GPS existente.

## Stream types

V1 reconhece:

```text
elapsed_time
moving_time
distance
speed
pace
heart_rate
altitude
elevation
grade
cadence
power
temperature
```

`elevation` na timeline é alias de altitude para consumo de gráfico. Ganho/perda de elevação são derivados pela análise.

`power` e `temperature` são future-ready. Ausência de dado permanece ausência de dado; nenhum valor zero é fabricado.

Cadência depende da modalidade:

```text
corrida/caminhada: spm
ciclismo: rpm
```

## Publicação junto com Activity

`POST /api/v1/activities` continua aceitando Activities sem streams.

Uma gravação rica pode enviar `streams` e `laps` junto com a Activity. A criação de Activity, rota, Streams e laps ocorre dentro da mesma transação do fluxo de publicação.

Exemplo reduzido:

```json
{
  "sport": "corrida",
  "started_at": "2026-09-15T06:00:00-03:00",
  "ended_at": "2026-09-15T06:52:10-03:00",
  "metrics": {
    "distance_m": 10000,
    "duration_s": 3130
  },
  "gps": {
    "points": [
      {"lat": -27.36, "lon": -53.39, "timestamp_ms": 1789452000000},
      {"lat": -27.3599, "lon": -53.3898, "timestamp_ms": 1789452005000}
    ]
  },
  "streams": {
    "schema_version": 1,
    "source": "stridebr_android",
    "samples": [
      {
        "elapsed_ms": 0,
        "moving_ms": 0,
        "distance_m": 0,
        "heart_rate_bpm": 132,
        "cadence": 166,
        "altitude_m": 482.1,
        "route_point_index": 0
      },
      {
        "elapsed_ms": 5000,
        "moving_ms": 5000,
        "distance_m": 15.7,
        "heart_rate_bpm": 136,
        "cadence": 169,
        "altitude_m": 482.6,
        "route_point_index": 1
      }
    ]
  },
  "laps": [
    {
      "start_elapsed_ms": 0,
      "end_elapsed_ms": 312000,
      "start_distance_m": 0,
      "end_distance_m": 1000
    }
  ]
}
```

O POST continua usando o contrato de idempotência da Activity.

## Sincronização separada

```text
PUT /api/v1/activities/{id}/streams
Idempotency-Key: <opaque key>
```

Payload:

```json
{
  "schema_version": 1,
  "source": "wear_os",
  "source_metadata": {
    "device": "watch"
  },
  "samples": [
    {
      "elapsed_ms": 1422000,
      "moving_ms": 1397000,
      "distance_m": 3920.4,
      "heart_rate_bpm": 164,
      "cadence": 174,
      "altitude_m": 487.2
    }
  ]
}
```

Limites:

```text
schema_version: 1
samples: máximo 50000
request JSON: máximo 16 MiB
```

Mesmo `Idempotency-Key` + mesmo conteúdo retorna `200` com `reused=true`.

Mesmo `Idempotency-Key` + conteúdo diferente retorna:

```text
409 idempotency_conflict
```

Uma nova chave substitui atomicamente o bundle canônico daquela Activity. Não há estado parcial entre bundle e suas samples.

## Source

`source` identifica a origem quando conhecida. Exemplos válidos de convenção de produto:

```text
stridebr_android
wear_os
bluetooth_hr
strava
import
route_backfill
```

O Core não exige enum fechado porque novas fontes podem existir. Um `source` comum pode ficar no bundle; `sample.source` existe somente para casos onde a origem varia dentro da mesma timeline.

## Eixos monotônicos

`elapsed_ms` é obrigatório e monotônico.

`moving_ms`, quando presente, também é monotônico e nunca pode ultrapassar `elapsed_ms`.

`distance_m`, quando presente, é monotônico com tolerância apenas para ruído numérico mínimo de validação.

Pausas são representadas pela diferença entre elapsed e moving time. `gap_before_ms` marca lacunas conhecidas da aquisição.

## Leitura

```text
GET /api/v1/activities/{id}/streams
```

Query:

```text
streams=pace,heart_rate,altitude,cadence
axis=time|distance
resolution=raw|high|medium|low
max_points=50..5000
```

Defaults de resolução:

```text
high   2000 pontos
medium  800 pontos
low     300 pontos
```

`raw` sem `max_points` retorna o bundle integral, até 50000 samples.

### Axis time

`axis=time` usa `elapsed_ms` como `x`. `moving_ms` continua disponível para identificar pausa.

### Axis distance

`axis=distance` usa `distance_m` como `x` e remove samples sem distância conhecida.

Isso permite gráficos como:

```text
pace × km
HR × km
elevação × km
```

## Samples alinhados

A resposta não devolve arrays independentes que o Mobile precise juntar. Cada item está alinhado no mesmo eixo:

```json
{
  "x": 1422000,
  "elapsed_ms": 1422000,
  "moving_ms": 1397000,
  "distance_m": 3920.4,
  "pace": 318.0,
  "heart_rate": 164,
  "altitude": 487.2,
  "cadence": 174
}
```

Portanto o tooltip de gráfico pode apresentar diretamente:

```text
23:42
3.92 km
5:18/km
164 bpm
487 m
174 spm
```

sem joins de timestamps incompatíveis no Android.

## Units

A resposta inclui metadata de unidade. V1 usa:

```text
elapsed_time ms
moving_time  ms
distance     m
speed        m_s
pace         s_per_km
heart_rate   bpm
altitude     m
elevation    m
grade        percent
cadence      spm ou rpm
power        W
temperature  celsius
```

## Downsampling

Leituras reduzidas usam Largest-Triangle-Three-Buckets, combinando pontos importantes dos sinais solicitados e preservando extremos relevantes.

Isso é preferível a selecionar simplesmente cada N-ésima amostra, porque mantém melhor a forma de intervalos, picos de FC e vales/picos de pace.

`raw` continua disponível ao owner.

O downsampling altera apenas a resposta. O bundle persistido não perde resolução.

Quando `pace` é solicitado, cada sample retorna `pace` e o alias explícito `pace_s_per_km`, ambos em segundos por quilômetro. O alias é aditivo e facilita clientes que preferem nomes com unidade sem remover o campo `pace` existente.

## Activity Detail

`GET /api/v1/activities/{id}` não embute timeline.

Ele adiciona somente:

```json
{
  "stream_capabilities": {
    "has_streams": true,
    "available_streams": ["elapsed_time", "moving_time", "distance", "speed", "pace", "heart_rate", "altitude", "grade", "cadence"],
    "has_analysis": true,
    "has_heart_rate": true,
    "has_cadence": true,
    "has_laps": true
  }
}
```

Activities antigas sem streams continuam válidas.

## Automatic splits

```text
GET /api/v1/activities/{id}/splits?distance_m=1000
```

`distance_m` aceita 100 a 100000.

Splits são derivados e não persistidos como manual laps.

Exemplo:

```json
{
  "data": {
    "activity_id": "activity-id",
    "split_distance_m": 1000,
    "data": [
      {
        "index": 1,
        "partial": false,
        "start_distance_m": 0,
        "end_distance_m": 1000,
        "distance_m": 1000,
        "elapsed_duration_s": 326,
        "moving_duration_s": 319,
        "pace_s_per_km": 319,
        "speed_kmh": 11.285,
        "heart_rate_avg_bpm": 156,
        "heart_rate_max_bpm": 164,
        "elevation_gain_m": 12.4,
        "elevation_loss_m": 4.2,
        "cadence_avg": 172,
        "power_avg_w": null
      }
    ]
  }
}
```

Para 5.4 km com split de 1 km, o sexto item é `partial=true` e representa os 400 m finais.

## Manual laps

Manual lap é ação do usuário e possui domínio próprio.

```text
GET /api/v1/activities/{id}/laps
PUT /api/v1/activities/{id}/laps
```

PUT substitui atomicamente o conjunto de laps `origin=manual`. Como a operação representa o estado inteiro desejado, o mesmo payload é naturalmente idempotente.

```json
{
  "source": "stridebr_android",
  "laps": [
    {
      "start_elapsed_ms": 0,
      "end_elapsed_ms": 312000,
      "start_moving_ms": 0,
      "end_moving_ms": 305000,
      "start_distance_m": 0,
      "end_distance_m": 1000
    }
  ]
}
```

Importações podem criar `origin=import`; o endpoint Mobile de PUT não converte automaticamente import laps em manual laps.

## Imports

GPX/TCX/FIT já analisados pelo Core podem conter séries temporais. Ao confirmar uma importação rica, o Core promove os dados disponíveis para Activity Streams dentro da transação da importação.

Não são criados dados retroativos inexistentes.

## Backfill

O script:

```text
scripts/backfill_activity_streams.php
```

pode materializar incrementalmente Activities antigas que tenham fonte real reconstruível.

Exemplos:

```bash
php scripts/backfill_activity_streams.php --limit=200
php scripts/backfill_activity_streams.php --after=<activity-id> --limit=200
php scripts/backfill_activity_streams.php --user=<user-id> --limit=200 --analysis
```

Ele não roda durante migration nem automaticamente em produção.

Uma rota antiga sem timestamps reais não ganha segundos fictícios entre pontos.

## Deletion, export e privacy

Streams, laps e cache de análise pertencem à Activity.

Hard-delete da Activity usa cascade para remover esses dados. Soft-delete continua permitindo restore, mas os endpoints owner-scoped deixam de enxergar a Activity enquanto ela estiver excluída.

Exportação de conta inclui bundles, samples, laps e cache de análise.

GPS, HR e demais streams são privados. Não existe cache público ou endpoint público para esse domínio.

## Erros principais

```text
401 authentication_required
404 not_found
409 idempotency_conflict
422 validation_error
```

A API retorna JSON, nunca HTML.
