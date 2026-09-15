# Stride Pacer v1

## Objetivo

Stride Pacer é uma fundação determinística de planejamento e guidance de pace. Não utiliza IA generativa e não exige conexão durante a atividade.

O Core é responsável por:

- validar o plano;
- gerar segmentos determinísticos;
- armazenar planos;
- fornecer regras de guidance;
- fornecer um evaluator de referência para teste/simulação;
- integrar Pacer Plans ao domínio de Workouts.

O Mobile futuro é responsável por executar essas mesmas regras localmente durante a corrida, usando estado local e sensores disponíveis.

## Modalidades v1

Pacer v1 aceita modalidades cujo `metrica_derivada` seja:

```text
pace_km
```

Não há estratégia automática de pace para ciclismo ou modalidades sem pace canônico nesta versão.

## Plan types

```text
even
negative_split
positive_split
custom
```

`positive_split` é representável, mas não é recomendação automática do produto.

## APIs

```text
GET    /api/v1/pacer-plans
POST   /api/v1/pacer-plans
GET    /api/v1/pacer-plans/{id}
PATCH  /api/v1/pacer-plans/{id}
DELETE /api/v1/pacer-plans/{id}
POST   /api/v1/pacer-plans/generate
POST   /api/v1/pacer-plans/{id}/evaluate
```

DELETE arquiva o plano; não faz hard-delete da história referenciada.

Todas as rotas são owner-scoped.

## Generate

`POST /pacer-plans/generate` gera blueprint sem persistir.

Exemplo even:

```json
{
  "sport": "corrida",
  "target_distance_m": 5000,
  "target_time_s": 1500,
  "strategy": "even",
  "tolerance_s_per_km": 10
}
```

O target médio é:

```text
1500 / 5 = 300 s/km
```

## Negative split

Exemplo:

```json
{
  "sport": "corrida",
  "target_distance_m": 10000,
  "target_time_s": 3120,
  "strategy": "negative_split",
  "tolerance_s_per_km": 10,
  "constraints": {
    "progression_percent": 8,
    "segment_distance_m": 2000
  }
}
```

A geração cria targets progressivamente mais rápidos e aplica normalização temporal para que:

```text
sum(segment_distance_km × target_pace_s_per_km)
=
target_time_s
```

Não há tabela hardcoded de paces para 5 km ou 10 km.

## Custom

Custom permite segmentos explícitos:

```json
{
  "sport": "corrida",
  "target_distance_m": 5000,
  "target_time_s": 1530,
  "strategy": "custom",
  "segments": [
    {
      "start_distance_m": 0,
      "end_distance_m": 2000,
      "target_pace_s_per_km": 315,
      "tolerance_s_per_km": 10
    },
    {
      "start_distance_m": 2000,
      "end_distance_m": 5000,
      "target_pace_s_per_km": 300,
      "tolerance_s_per_km": 10
    }
  ]
}
```

Validação rejeita:

- target <= 0;
- distância > 500 km;
- tempo > 7 dias;
- pace estrutural fora de 60..3600 s/km;
- gaps entre segmentos;
- sobreposição;
- segmento que não cobre o target final;
- mais de 200 segmentos;
- HR floor/ceiling inválidos.

## Segment model

V1 executa segmentos por distância:

```text
basis=distance
start_distance_m
end_distance_m
target_pace_s_per_km
tolerance_s_per_km
heart_rate_floor_bpm optional
heart_rate_ceiling_bpm optional
instruction_metadata
```

O schema de banco é future-ready para basis `time`, mas a API Pacer v1 deliberadamente não aceita esse modo ainda. Workouts já possuem estrutura temporal própria; duplicar blocks temporais antes de uma integração explícita criaria dois domínios concorrentes.

## Tolerance

Cada segmento possui banda de tolerância.

Exemplo:

```text
target: 300 s/km
tolerance: 10 s/km
band: 290..310 s/km
```

Dentro da banda, o resultado normal é `on_target`.

## Guidance rules v1

Defaults:

```json
{
  "rules_version": 1,
  "persistence_s": 15,
  "hysteresis_s_per_km": 3,
  "cooldown_s": 20,
  "heart_rate_persistence_s": 15,
  "final_push": {
    "enabled": false,
    "remaining_distance_m": 1000,
    "max_behind_s": 0
  }
}
```

### Persistence

Pace fora da tolerance não gera correção instantânea. O mesmo candidato precisa persistir por `persistence_s`.

### Hysteresis

Depois de uma correção, cruzar apenas a borda da tolerance não basta para mandar correção oposta. É necessário ultrapassar também `hysteresis_s_per_km`.

### Cooldown

Correções diferentes respeitam `cooldown_s`, reduzindo spam de guidance.

Essas regras são parte do contrato e devem ser reproduzidas no Android.

## Guidance codes

```text
on_target
speed_up
slow_down
ease
segment_change
final_push_available
finished
```

O Core não retorna a frase final de UX. Android pode mapear códigos para texto, áudio ou haptic.

## Reference evaluator

```text
POST /api/v1/pacer-plans/{id}/evaluate
```

Este endpoint é para testes, simulações e verificação semântica. Não é uma API que o Mobile deve chamar a cada segundo durante uma corrida.

Input:

```json
{
  "distance_m": 2100,
  "elapsed_s": 665,
  "moving_time_s": 650,
  "recent_pace_s_per_km": 324,
  "average_pace_s_per_km": 316,
  "heart_rate_bpm": 158,
  "guidance_state": {
    "segment_order": 2,
    "candidate_code": "speed_up",
    "candidate_since_s": 635,
    "last_guidance_code": "on_target"
  }
}
```

Output reduzido:

```json
{
  "data": {
    "code": "speed_up",
    "segment": {
      "order": 2,
      "start_distance_m": 2000,
      "end_distance_m": 4000,
      "progress_percent": 5
    },
    "target": {
      "pace_s_per_km": 310,
      "tolerance_s_per_km": 10,
      "lower_pace_s_per_km": 300,
      "upper_pace_s_per_km": 320,
      "heart_rate_ceiling_bpm": 165
    },
    "ahead_behind_s": 9.3,
    "target_elapsed_s": 655.7,
    "remaining_distance_m": 7900,
    "projected_finish": {
      "at_current_average_s": 3161,
      "if_plan_followed_s": 3129.3
    },
    "heart_rate_constraint": {
      "available": true,
      "status": "within_constraint",
      "current_bpm": 158,
      "ceiling_bpm": 165
    },
    "next_state": {}
  }
}
```

## Ahead / behind

Convenção:

```text
ahead_behind_s = actual_elapsed - target_elapsed_at_current_distance
```

Logo:

```text
positivo = atrasado
negativo = adiantado
zero = exatamente no target acumulado
```

## Projected finish

O evaluator devolve duas projeções factuais quando calculáveis:

```text
at_current_average_s
if_plan_followed_s
```

`at_current_average_s` prolonga o pace médio atual para a distância restante.

`if_plan_followed_s` soma o tempo já gasto ao target restante do plano.

Nenhuma delas é previsão fisiológica.

## HR-aware rule

Um segmento pode ter HR floor/ceiling explícitos.

Quando HR está acima do ceiling por `heart_rate_persistence_s`, o evaluator pode retornar:

```text
ease
```

Isso executa uma constraint configurada pelo usuário/treinador e não é recomendação médica.

Se HR desaparecer:

```text
heart_rate_constraint.status=unavailable
```

O Pacer continua usando pace.

## Final push

É opt-in.

O code `final_push_available` só pode aparecer quando:

- `final_push.enabled=true`;
- distância restante <= `remaining_distance_m` configurado;
- atraso acumulado <= `max_behind_s`;
- se houver HR ceiling, HR está disponível e dentro do ceiling.

O code significa somente que as regras configuradas permitem mudar o estado. Não afirma segurança fisiológica.

## Offline requirement

Durante uma Activity, o Mobile precisa ter localmente:

- Pacer Plan;
- segments;
- guidance rules;
- current `next_state`;
- distance;
- elapsed/moving time;
- recent smoothed pace;
- average pace;
- HR opcional.

Nenhuma request é necessária para avaliar guidance.

O Core evaluator existe para garantir que implementação Android e Core produzam o mesmo resultado em fixtures iguais.

## Workout integration

Workouts pessoais podem referenciar:

```text
pacer_plan_id
```

O Core valida:

- ownership;
- plano ativo;
- mesma modalidade do Workout.

Treino recorrente mantém o vínculo ao dividir `scope=future`.

Em `scope=this`, trocar apenas Pacer não é suportado nesta v1 porque a tabela atual de exceções de recorrência não possui campo próprio de Pacer; o Core rejeita a alteração em vez de fingir que persistiu.

## Course-aware future

Todo Pacer v1 usa:

```text
terrain_adjustment_mode=none
```

A arquitetura possui campo para evolução, mas não existe ajuste automático por elevação/GAP nesta rodada.

## Privacy

Pacer Plans são privados e owner-scoped. HR constraints também são dados privados.

Não existe endpoint social/ranking nem processamento por IA.
