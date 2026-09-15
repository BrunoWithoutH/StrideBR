# Structured Endurance Workouts — Web V1

O Web usa o mesmo domínio de Workout da Training Platform. Corrida, ciclismo e outros treinos de endurance não possuem um motor paralelo.

## Passos

Um passo pode usar `step_type`:

- `exercise`;
- `warmup`;
- `work`;
- `recovery`;
- `cooldown`;
- `interval_group`.

Passos de academia continuam usando `exercise_id`. Passos de endurance podem existir sem exercício de academia.

Campos canônicos suportados pelo domínio compartilhado:

- duração planejada;
- distância planejada;
- `repeat_count`;
- target;
- recovery.

Targets suportados:

- `pace` em `s_per_km`;
- `speed` em `km_h`;
- `heart_rate` em `bpm`;
- `rpe` em `rpe_1_10`;
- `duration` em `s`;
- `distance` em `m`.

O editor Web formata unidades para leitura esportiva, mas persiste valores canônicos. Pace de `285..295 s/km`, por exemplo, aparece como `4:45–4:55/km`.

## Intervalos

Um passo pode definir repetição e recuperação. Isso representa estruturas como:

```text
10 min aquecimento

5 ×
1 km · 4:45–4:55/km
2 min recuperação

10 min desaquecimento
```

O Core expande a repetição para análise planejado × realizado sem obrigar o usuário a cadastrar manualmente todos os blocos.

## Pacer e Route

Um Workout route-capable pode referenciar:

- `pacer_plan_id`;
- `route_id`.

Owner, modalidade e estado são validados no Core. Recorrências divididas com `scope=future` preservam Pacer e Route na série futura.

## Planned × actual

`src/function/endurance_workout_analysis.php` compara o Workout com a Activity vinculada usando Activity Streams.

A correspondência é sequencial:

- passo por distância avança por `distance_m`;
- passo por duração avança por `moving_ms`.

Para cada bloco comparável o Core pode retornar métricas realizadas, target e `within_target`.

Se o passo não possui informação suficiente para delimitar um trecho real, o planejado continua visível e `actual` permanece `null`. O Web não inventa correspondência.

## Fonte de verdade

O Web não recalcula targets nem métricas da Activity. Ele reutiliza:

- Training Platform;
- cronograma;
- Activity Streams;
- Activity Analysis;
- Pacer Platform.

A estrutura permanece compatível com o Mobile, que recebe os mesmos passos canônicos pela API v1.
