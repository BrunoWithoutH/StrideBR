# Activity Analysis v1

## Princípio

Activity Analysis transforma Activity + Streams em métricas determinísticas, explicáveis e versionadas. Não utiliza LLM, IA generativa ou diagnóstico médico.

Endpoint:

```text
GET /api/v1/activities/{id}/analysis
GET /api/v1/activities/{id}/analysis?recompute=1
```

Versão atual:

```text
analysis_version = 1
```

O resultado pode ser recalculado em versões futuras sem reescrever Streams brutos/canônicos.

## Cache

A análise usa cache materializado em `activity_analysis_cache`.

O fingerprint inclui:

- versão do algoritmo;
- Activity;
- hash/atualização do bundle de Streams;
- perfis default de zonas aplicáveis.

Alterar Streams ou zones invalida o cache relevante.

`recompute=1` força recálculo da versão atual.

## Dados parciais

Cada bloco é opcional.

Activity GPS sem HR pode ter pacing/elevation e `heart_rate=null`.

Activity com HR sem cadence mantém `cadence=null`.

Activity antiga sem timeline pode retornar `has_streams=false` e apenas os blocos realmente reconstruíveis.

Nenhuma ausência é preenchida com zero fictício.

## Comportamento esportivo

A regra vem de `modalidades.metrica_derivada`.

```text
pace_km        -> pace
velocidade_kmh -> speed
pace_100m      -> pace_100m
outros         -> distance_time
```

Ciclismo não recebe min/km apenas porque possui distância e duração.

## Pace/speed summary

Para modalidades suportadas, `pacing` pode retornar:

```text
average
moving_average
median
p10
p50
p90
standard_deviation
median_absolute_deviation
variability_percent
first_half
second_half
difference
difference_percent
pattern
start.first_10_percent
middle.middle_80_percent
finish.last_10_percent
finish.previous_20_percent
finish.difference_percent
```

Pausas não contam como movimento. Intervalos inválidos ou gaps excessivos são ignorados pelas agregações de movimento.

### First half × second half

A divisão é feita por distância da Activity.

```text
difference_percent =
(second_half - first_half) / first_half * 100
```

Para pace, número menor é mais rápido.

Para speed, número maior é mais rápido.

### Pacing pattern

Threshold explícito:

```text
even_threshold = ±2%
```

Pace:

```text
-2% ou menor -> negative_split
entre -2% e +2% -> even
+2% ou maior -> positive_split
```

Speed inverte o sinal de melhoria porque maior velocidade é melhor.

Se faltarem dados comparáveis:

```text
insufficient_data
```

## Variabilidade

O Core retorna métricas, não score 0–100.

`variability_percent` é coefficient of variation:

```text
standard_deviation / mean * 100
```

Também são expostos MAD e percentis.

Finding `pace_variability_high` usa threshold documentado de 10% de coefficient of variation. Isso não classifica o treino como bom ou ruim; intervalados podem naturalmente ter alta variabilidade.

## Start / middle / finish

A Activity é dividida por distância em:

```text
first 10%
middle 80%
last 10%
```

O bloco de finish também compara:

```text
last 10%
vs
20% imediatamente anteriores
```

Finding `strong_finish` exige melhoria de pelo menos 3% conforme a direção da métrica.

## Rolling metrics

V1 calcula melhores janelas internas de:

```text
30 s
60 s
```

Essas janelas pertencem somente à Activity atual.

## Activity-local best efforts

Quando a Activity possui distância suficiente:

```text
400 m
500 m
1 km
1 mile
5 km
```

A resposta contém tempo, início/fim da janela, pace e speed.

Esses valores não são PB/SB e não alteram o domínio histórico de marcas/testes.

## Heart rate

Se HR existir, o bloco pode conter:

```text
average_bpm
max_bpm
min_bpm
coverage_percent
observed_time_s
first_half_bpm
second_half_bpm
trend_bpm
zones
decoupling
```

Coverage é calculada somente sobre intervalos de movimento considerados válidos. A frequência de amostragem, por si só, não invalida um intervalo: 31 s, 40 s ou 60 s entre samples continuam válidos quando `elapsed_ms`/`moving_ms` são coerentes. Se o sample atual possui `gap_before_ms > 0`, o par anterior → atual é excluído das agregações contínuas e não é interpolado. Na ausência de metadata explícita de gap, `elapsed_delta > 120 s` permanece como fallback defensivo para evitar ligar trechos longos não observados de streams/imports legados.

## HR zones

Não existe `220 - idade` automático.

Zones são explicitamente configuradas pelo usuário via Zone Profiles.

```text
GET    /api/v1/zone-profiles
POST   /api/v1/zone-profiles
GET    /api/v1/zone-profiles/{id}
PATCH  /api/v1/zone-profiles/{id}
DELETE /api/v1/zone-profiles/{id}
```

Profile types:

```text
heart_rate -> bpm
pace       -> s_per_km
```

Um perfil pode ser global ou específico da modalidade. A análise usa apenas perfil marcado `is_default=true`, preferindo o perfil específico do esporte ao global.

Exemplo:

```json
{
  "profile_type": "heart_rate",
  "name": "Zonas corrida",
  "sport": "corrida",
  "is_default": true,
  "zones": [
    {"code": "Z1", "max": 140},
    {"code": "Z2", "min": 140, "max": 155},
    {"code": "Z3", "min": 155, "max": 168},
    {"code": "Z4", "min": 168, "max": 180},
    {"code": "Z5", "min": 180}
  ]
}
```

A distribuição retorna tempo, distância observada e porcentagem por zone. Porcentagens usam somente o período com medição válida e classificada.

## Pace zones

Pace zones usam o mesmo domínio de Zone Profiles, com unidade `s_per_km`.

Não existe threshold pace inventado pelo Core. Sem profile default, `pace_zones=null`.

## Cardiac drift / aerobic decoupling

É uma métrica esportiva, não diagnóstico médico.

V1 só calcula decoupling quando todos os requisitos abaixo são satisfeitos:

```text
HR coverage >= 80%
moving duration >= 1200 s
distance >= 2000 m
modalidade pace-based
dados úteis nas duas metades
```

A Activity é dividida por distância.

Para cada metade:

```text
efficiency = speed_mps / average_heart_rate_bpm
```

Depois:

```text
decoupling_percent =
(first_half_efficiency - second_half_efficiency)
/ first_half_efficiency * 100
```

Finding `heart_rate_drift_detected` é emitido quando `decoupling_percent >= 5%`, sempre acompanhado de fórmula, thresholds e supporting values.

Coverage insuficiente produz `decoupling=null`.

## Elevation

A análise de altitude usa mediana móvel de 5 samples.

Ganho/perda ignora oscilações menores que 0.8 m após suavização.

Retorna:

```text
min_m
max_m
gain_m
loss_m
grade
smoothing metadata
```

## Grade

Grade é derivado sobre janela de distância, não entre pontos quase coincidentes.

Configuração v1:

```text
minimum distance window: 20 m
target distance window: 30 m
```

O Core não implementa Grade Adjusted Pace nesta rodada.

## Cadence

Quando há cadence:

```text
average
max
coverage_percent
first_half
second_half
```

Unidade:

```text
corrida/caminhada -> spm
ciclismo -> rpm
```

Não existe comparação direta entre essas duas semânticas.

## Power

Se potência real existir, Analysis aceita:

```text
average_w
max_w
coverage_percent
```

O Core não calcula Running Power por fórmula própria.

## Findings

V1 pode retornar:

```text
negative_split
even
positive_split
strong_finish
pace_variability_high
heart_rate_drift_detected
```

Cada finding contém:

```text
code
analysis_version
formula
thresholds
supporting_values
```

Nenhum finding gera aconselhamento médico ou frase subjetiva de coach.

## Exemplo

```json
{
  "data": {
    "activity_id": "activity-id",
    "analysis_version": 1,
    "pacing": {
      "behavior": "pace",
      "unit": "s_per_km",
      "average": 331,
      "first_half": 339,
      "second_half": 323,
      "difference_percent": -4.72,
      "pattern": "negative_split",
      "variability_percent": 3.8,
      "pattern_even_threshold_percent": 2,
      "finish": {
        "last_10_percent": 306,
        "previous_20_percent": 323,
        "difference_percent": -5.26
      }
    },
    "heart_rate": {
      "average_bpm": 158,
      "max_bpm": 176,
      "coverage_percent": 98.1,
      "decoupling": {
        "first_half_efficiency_mps_per_bpm": 0.0192,
        "second_half_efficiency_mps_per_bpm": 0.0184,
        "decoupling_percent": 4.17,
        "method": "speed_mps_per_bpm_halves_by_distance"
      }
    },
    "cadence": {
      "unit": "spm",
      "average": 173.2,
      "coverage_percent": 96.4
    }
  }
}
```
