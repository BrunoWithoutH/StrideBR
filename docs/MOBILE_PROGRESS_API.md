# Mobile Progress Platform API v1

A aba Progresso do StrideBR Mobile usa exclusivamente a API v1 do Core. O cliente não recalcula regras de analytics, aderência, melhor carga, carga de treino ou agregações por modalidade.

## Base e autenticação

Base: `/api/v1`.

Todos os endpoints deste documento exigem `Authorization: Bearer <access_token>`, são restritos ao usuário autenticado e retornam `Cache-Control: no-store`. Nenhum aceita `user_id`.

Timezone canônico desta versão: `America/Sao_Paulo`.

## Range

Todos os endpoints analíticos aceitam `from=YYYY-MM-DD` e `to=YYYY-MM-DD`. Os dois parâmetros precisam ser enviados juntos. Sem ambos, o Core usa os últimos 28 dias incluindo hoje.

`from` e `to` são inclusivos e representam datas civis de `America/Sao_Paulo`. O limite é 1827 dias. Intervalos maiores retornam `422 validation_error`.

O período anterior possui exatamente a mesma quantidade de dias e termina no dia anterior a `from`.

## Null e zero

`0` significa que o intervalo/bucket é válido e não houve ocorrência para uma métrica somável ou contável.

`null` significa que a métrica é indisponível ou não aplicável aos dados existentes. Uma Activity sem distância não recebe distância zero. Uma sessão de força sem carga numérica não recebe volume inventado.

## Unidades

| Campo | Unidade |
| --- | --- |
| duração | `s` |
| distância | `m` |
| elevação | `m` |
| carga | `kg` |
| volume de força | `kg` (`reps × load_kg`) |
| pace | `s_per_km` |
| pace de natação | `s_per_100m` |
| velocidade | `km_h` |
| esforço percebido | `rpe_1_10` |
| training load | `session_rpe_au` |
| contagens | `count` |

## Training load

O Core reutiliza a definição já usada no Sport Hub Web:

`training_load = duration_minutes × perceived_effort`

Somente Activities com duração positiva e RPE entre 1 e 10 participam. Se há Activities no intervalo mas nenhuma possui os dados necessários, o valor é `null`. Nenhum TSS, TRIMP ou fórmula nova foi criado.

## Activity source

Os agregados partem de `registros_atividade` concluídos e não excluídos. Mobile GPS, Web, Strava/import, Workout Session e Quick Register convergem para a mesma Activity canônica e cada `idregistro` é contado uma vez.

Séries de força históricas vêm de `series_exercicio_atividade`. Uma Workout Session concluída não é consultada como segunda fonte de histórico.

## GET `/progress/overview`

Query:

- `from` opcional com `to`;
- `to` opcional com `from`;
- `sport` opcional, slug canônico de modalidade.

Retorna panorama atual, comparação com o período anterior e aderência ao planejamento.

### Exemplo A — overview de 28 dias

```json
{
  "data": {
    "range": {"from":"2026-08-19","to":"2026-09-15","days":28,"timezone":"America/Sao_Paulo"},
    "previous_range": {"from":"2026-07-22","to":"2026-08-18","timezone":"America/Sao_Paulo"},
    "sport": null,
    "summary": {
      "activities_count": 16,
      "active_days": 12,
      "total_duration_s": 38240,
      "total_distance_m": 128500,
      "elevation_gain_m": 1240,
      "total_training_load": 2418.5,
      "perceived_effort": {"count":13,"average":6.15,"min":3,"max":9},
      "total_workouts": 10,
      "planned_workouts": 9,
      "completed_workouts": 7,
      "consistency": {
        "active_days": 12,
        "weeks_with_activity": 4,
        "weeks_in_range": 5,
        "planned_completion_rate": 0.875
      }
    },
    "previous_period": {
      "total_distance_m": {"current":128500,"previous":104200,"delta":24300,"change_percent":23.320537428}
    }
  }
}
```

Comparações usam `{current, previous, delta, change_percent}`. Quando `previous` é zero, `change_percent` é `null`. Se uma das métricas é indisponível, `delta` e `change_percent` também são `null`.

## GET `/progress/timeseries`

Query:

- range padrão;
- `sport` opcional;
- `metric` obrigatório: `activities`, `duration`, `distance`, `elevation_gain`, `training_load`, `perceived_effort`, `strength_volume`;
- `bucket`: `day`, `week`, `month`.

Semanas seguem segunda a domingo. O primeiro e último bucket podem ser parciais em relação ao range solicitado.

### Exemplo G — série semanal

```json
{
  "data": {
    "metric": "distance",
    "bucket": "week",
    "unit": "m",
    "data": [
      {"start":"2026-08-31","end":"2026-09-06","value":25100},
      {"start":"2026-09-07","end":"2026-09-13","value":31800}
    ]
  }
}
```

`perceived_effort` é média dos RPEs válidos. `strength_volume` soma apenas séries concluídas com reps e carga numéricas.

## GET `/progress/sports`

Query: range padrão.

Retorna uma linha por modalidade canônica com metadata de `sport` (`id`, `slug`, `name`, `family`, `route_capable`, `derived_metric`, `behavior`) e `activities_count`, `duration_s`, `distance_m`, `elevation_gain_m`, `activities_percentage`. Percentual é calculado pela quantidade de Activities. `behavior` é derivado de `metrica_derivada` no Core, nunca inferido pelo cliente.

## GET `/progress/calendar`

Query: range padrão e `sport` opcional.

Retorna um ponto leve para cada dia civil. Não inclui Activities completas nem pontos GPS.

### Exemplo F — heatmap

```json
{
  "data": {
    "data": [
      {"date":"2026-09-01","activities_count":1,"duration_s":1800,"distance_m":5000,"training_load":210},
      {"date":"2026-09-02","activities_count":0,"duration_s":0,"distance_m":0,"training_load":0}
    ]
  }
}
```

## GET `/progress/cardio`

Query:

- range padrão;
- `sport` obrigatório;
- `bucket` opcional: `day`, `week`, `month`.

O Core resolve o comportamento pela `metrica_derivada` canônica da modalidade, a mesma metadata usada pelo domínio de Activities: `pace_km` → `pace`, `velocidade_kmh` → `speed`, `pace_100m` → `pace_100m`. Modalidades cardio sem uma métrica derivada específica continuam com distância/duração sem inventar uma métrica de performance.

Pace e velocidade são derivados do total pareado de duração/distância, e não da média simples dos paces individuais. O cliente não deve inferir comportamento a partir do nome ou slug da modalidade.

### Exemplo B — corrida

```json
{
  "data": {
    "sport": {"slug":"corrida","family":"cardio","derived_metric":"pace_km","behavior":"pace"},
    "behavior": "pace",
    "current": {
      "activities_count": 8,
      "active_days": 7,
      "duration_s": 18200,
      "distance_m": 52100,
      "elevation_gain_m": 510,
      "longest_distance_m": 12000,
      "longest_duration_s": 4300,
      "average_pace_s_per_km": 349.33,
      "average_pace_s_per_100m": null,
      "average_speed_kmh": null,
      "paired_activities_count": 8
    },
    "trends": {
      "distance": {"current":52100,"previous":48000,"delta":4100,"change_percent":8.54},
      "performance": {"current":349.33,"previous":354.17,"delta":-4.84,"change_percent":-1.37}
    }
  }
}
```

Para pace, valor menor é mais rápido; a API retorna valores numéricos, não frases de interpretação.

### Exemplo C — ciclismo

```json
{
  "data": {
    "sport": {"slug":"ciclismo","family":"cardio","derived_metric":"velocidade_kmh","behavior":"speed"},
    "behavior": "speed",
    "current": {
      "activities_count": 4,
      "duration_s": 14400,
      "distance_m": 108000,
      "elevation_gain_m": 860,
      "average_pace_s_per_km": null,
      "average_speed_kmh": 27
    }
  }
}
```

Ciclismo nunca recebe pace min/km deste endpoint.

## GET `/progress/strength`

Query: range padrão e `sport` opcional. Se `sport` for enviado, precisa pertencer à família de força.

Retorna sessões canônicas de força, dias ativos, séries, reps, volume, exercícios, duração e distribuições.

Volume usa exatamente:

`volume_load_kg = Σ(completed_set.repetitions × completed_set.load_kg)`

Séries sem reps ou sem carga numérica não entram no volume. Strings planejadas como `70 kg` não são reinterpretadas como execução histórica.

### Exemplo D — força

```json
{
  "data": {
    "sessions": 8,
    "active_days": 8,
    "total_sets": 96,
    "completed_sets": 91,
    "total_reps": 824,
    "volume_load_kg": 58240,
    "exercises_count": 14,
    "duration_s": 28800,
    "distribution_by_exercise": [
      {"exercise_id":"exercise-id","name":"Supino reto","sets_count":12,"reps":104,"volume_load_kg":7420}
    ],
    "distribution_by_primary_muscle": [
      {"name":"Peitoral","completed_sets":22}
    ]
  }
}
```

Não há e1RM/1RM estimado no contrato de Progress v1.

## GET `/progress/exercises`

Query:

- range padrão;
- `q` opcional;
- `page`, padrão `1`;
- `limit`, padrão `30`, máximo `100`.

Lista somente exercícios com execução canônica do usuário no range.

```json
{
  "data": {
    "data": [
      {
        "exercise_id":"exercise-id",
        "name":"Supino reto",
        "sessions_count":4,
        "sets_count":16,
        "last_performed_at":"2026-09-12T18:30:00-03:00",
        "best_load_kg":82.5,
        "recent_load_kg":80,
        "recent_volume_load_kg":3040
      }
    ],
    "meta":{"page":1,"limit":30,"has_more":false}
  }
}
```

`best_load_kg` é all-time e preserva a definição já usada pela Training Platform: `MAX(series_exercicio_atividade.carga_kg)` entre séries concluídas de Activities concluídas do usuário.

## GET `/progress/exercises/{id}`

Query: range padrão.

### Exemplo E — evolução do exercício

```json
{
  "data": {
    "exercise":{"id":"exercise-id","name":"Supino reto"},
    "sessions_count":3,
    "sets_count":12,
    "best_load_kg":82.5,
    "recent_load_kg":80,
    "recent_volume_load_kg":3040,
    "data":[
      {
        "activity_id":"activity-id",
        "date":"2026-09-10",
        "title":"Treino A",
        "sets_count":4,
        "completed_sets":4,
        "total_reps":40,
        "best_load_kg":70,
        "volume_load_kg":2800,
        "sets":[
          {"number":1,"repetitions":10,"load_kg":70,"rir":2,"rpe":8,"completed":true}
        ]
      }
    ]
  }
}
```

Um exercício sem histórico pertencente ao usuário retorna `404 not_found`.

## GET `/progress/adherence`

Query: range padrão e `sport` opcional.

A recorrência de cronogramas usa a mesma engine e reconciliação do Core Web. `planejamentoEstado()` define concluído, deslocado, perdido, pendente e cancelado.

Treino futuro não é falha. Treino passado não vira concluído automaticamente.

`completion_rate = completed_count / due_count`

`due_count = completed_count + past_due_count`

Se `due_count` for zero, `completion_rate` é `null`.

### Exemplo H — planejado × realizado

```json
{
  "data": {
    "total_count": 5,
    "planned_count": 4,
    "completed_count": 2,
    "cancelled_count": 1,
    "pending_count": 1,
    "past_due_count": 1,
    "due_count": 3,
    "completion_rate": 0.6666666667,
    "planned_vs_executed": {
      "duration": {
        "planned_duration_s": 4200,
        "actual_duration_s": 4260,
        "comparable_completed_count": 2
      },
      "distance": {
        "planned_distance_m": 5000,
        "actual_distance_m": 5100,
        "comparable_completed_count": 1
      }
    }
  }
}
```

Distância só é comparada quando o workout possui distância planejada, Activity executada possui distância e a modalidade é route-capable. Academia não é comparada em metros.

## GET `/progress/dashboard`

Query: range padrão e `sport` opcional.

Resposta composta para primeira carga da aba:

- `overview`;
- timeseries pequena de `activities`;
- `sports`;
- `adherence`;
- `strength` quando aplicável.

A timeseries usa `week` até 92 dias e `month` acima disso. O calendário diário não entra no dashboard para evitar payload grande.

## Exemplo I — usuário sem dados

```json
{
  "data": {
    "summary": {
      "activities_count": 0,
      "active_days": 0,
      "total_duration_s": 0,
      "total_distance_m": 0,
      "elevation_gain_m": 0,
      "total_training_load": 0,
      "perceived_effort": null,
      "total_workouts": 0,
      "planned_workouts": 0,
      "completed_workouts": 0
    }
  }
}
```

## Exemplo J — período anterior zero

```json
{
  "current": 10000,
  "previous": 0,
  "delta": 10000,
  "change_percent": null
}
```

A API nunca retorna `Infinity`, `-Infinity` ou `NaN`.

## Erros

```json
{
  "error": {
    "code": "validation_error",
    "message": "O intervalo de progresso deve ter no máximo 1827 dias."
  }
}
```

Códigos principais:

- `401 authentication_required` ou token expirado/revogado;
- `404 not_found` para exercício sem histórico ownership-scoped;
- `422 validation_error` para range, `sport`, `metric` ou `bucket` inválido.

A API nunca devolve HTML nesses endpoints.

## Itens fora da v1

Não fazem parte desta rodada: nova engine PB/SB, e1RM novo, VO2max, Training Readiness, recovery score, TSS/TRIMP novo, zonas de FC novas, ranking social, Teams analytics, AI coach, previsão de performance, Health Connect/Google Fit e Wear.
