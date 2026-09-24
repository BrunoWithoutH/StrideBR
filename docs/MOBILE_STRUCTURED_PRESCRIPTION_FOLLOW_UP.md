# Mobile Structured Prescription Follow-up

MOBILE STRUCTURED PRESCRIPTION FOLLOW-UP REQUIRED

O Core passou a expor structured prescription de forma aditiva. O repositório Android não foi alterado nesta rodada.

## Capability

Workout Session inclui:

```json
{
  "structured_prescription": {
    "version": 1,
    "present": true,
    "client_capability": "structured_prescription_v1"
  }
}
```

Quando `present=false`, o cliente pode continuar com o fluxo atual.

Quando `present=true`, o Android atual pode consumir os campos legacy/materializados para um fallback seguro, mas não deve apresentar que possui suporte completo a Cluster, Drop set ou grupos enquanto não implementar `structured_prescription_v1`.

## Workout exercise

Campos opcionais novos:

```json
{
  "prescription_method": "standard|cluster|drop_set",
  "prescription": {},
  "group": null
}
```

`group`, quando presente, representa Superset/Circuit e não deve ser convertido em método do exercício.

## Session exercise

Campos opcionais novos:

```json
{
  "prescription_method": "cluster",
  "prescription": {},
  "group": {},
  "sets": []
}
```

Cada set pode incluir:

```json
{
  "rep_target": {},
  "segment": {
    "type": "set|cluster|drop_stage",
    "block_index": 1,
    "stage_index": 1,
    "rest_after_s": 20
  }
}
```

Os campos actual existentes continuam sendo a verdade do realizado. Targets planejados não devem ser copiados para actuals pelo cliente.

## Rep targets

Modes estruturados:

- `fixed`;
- `range`;
- `amrap`;
- `failure`.

AMRAP/failure não significam zero reps. O usuário registra as repetições efetivamente realizadas.

## Cluster

O Mobile com capability completa deve representar blocos e microclusters usando os índices/targets dos segmentos, incluindo pausas intra-cluster e entre blocos.

## Drop set

O Mobile com capability completa deve representar os `drop_stage` em ordem, mantendo carga/target planejados separados de actual load/reps.

## Superset/Circuit

O Mobile com capability completa deve usar `group` para apresentar exercícios relacionados, rounds e descansos. Não inferir agrupamento pelo campo legacy `bloco`.

## Fallback atual

Fallback permitido para cliente antigo:

- manter campos legacy;
- mostrar séries materializadas;
- permitir execução básica sem fabricar actuals;
- ignorar campos estruturados desconhecidos.

Fallback proibido:

- transformar AMRAP em `0`;
- achatar Drop set e depois afirmar que stages foram preservados;
- inferir Superset/Circuit apenas por `bloco`;
- converter target em realizado.

## Implementação futura no Android

Quando o Android implementar `structured_prescription_v1`, alinhar:

- summaries;
- Cluster execution;
- Drop set execution;
- group containers;
- timers/rest;
- planned × actual;
- Activity history estruturado.

O contrato canônico é `docs/api/openapi.yaml` do Core desta rodada.

## Training Platform Consistency V1

Workout Session também expõe `execution_sequence` de forma aditiva.

Para `kind=group`, a sequência contém `group_type`, `declared_rounds` e `rounds`. Cada volta lista os IDs dos exercícios da sessão e os `set_ids` que pertencem àquela volta, além dos descansos entre exercícios e após a volta.

O Android com suporte completo a grupos deve usar `execution_sequence` como ordem de execução. Não deve inferir Superset/Circuit percorrendo `exercises` de forma linear, porque isso produziria A1/A2/A3/B1/B2/B3 em vez de A1/B1/A2/B2/A3/B3.

Clientes antigos podem ignorar `execution_sequence`; os campos existentes permanecem no payload. Quando `structured_prescription.present=true` e o cliente não entende grupos, o fallback não deve afirmar suporte completo à ordem estruturada.
