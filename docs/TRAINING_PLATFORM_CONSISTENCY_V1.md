# Training Platform Consistency V1

## Primitives canônicas

`src/function/workout_definition.php` é a representação canônica de treino para leitura, comparação semântica, apresentação e materialização.

Adaptadores atuais:

- Schedule: `workoutDefinitionFromSchedule()`
- Template: `workoutDefinitionFromTemplate()`
- Scheduled: `workoutDefinitionFromScheduled()`
- Session snapshot: `workoutDefinitionFromSessionSnapshot()`

Materialização de treino agendado deve usar `workoutDefinitionMaterializeScheduled()` ou `workoutDefinitionWriteScheduledItemsBatch()`. Novas superfícies não devem implementar cópia parcial de exercícios com SQL próprio.

## Snapshot

A plataforma segue fonte → materialização → snapshot. Alterar a fonte não modifica silenciosamente treino agendado existente, treino publicado ao atleta, sessão ativa ou Activity histórica.

Propagação de Library para treinos vinculados é explícita e inicia desativada.

## Builder compartilhado

Schedule, Template, Draft e Coach usam a primitive do Workout Builder V2. O estado estruturado usa o mesmo payload `rows[...]`, com `serialize()`/`hydrate()` para contextos temporários como Draft.

## Serialização

StrideBR JSON e Shared Schedule usam o mesmo snapshot estruturado version 2. Import continua aceitando versão legacy e prefere `definition` quando disponível.

CSV permanece formato tabular e não é o formato de backup full-fidelity para métodos estruturados.

## Preview

`workoutDefinitionPresentation()` é o presentation model canônico. Preview inicial e preview dinâmico consomem a mesma semântica de grupos, métodos e capabilities.

## Quick completion

`workoutDefinitionQuickCompleteMode()` classifica `exact`, `partial`, `ambiguous` ou `unsupported`.

Quick Register não inventa valores realizados. Targets range, AMRAP, failure e demais valores ambíguos permanecem sem actuals. A Web apresenta confirmação explícita para conclusão sem detalhes.

## Execução de grupos

`sessaoExecutionSequence()` é a ordem canônica de execução. Superset/Circuit são representados por volta, preservando actuals por set individual.

Configuração nova de grupo exige quantidade lógica de voltas compatível entre membros. Configurações legacy inconsistentes continuam executáveis de forma conservadora e recebem `legacy_round_mismatch`.

A API de Workout Session expõe `execution_sequence` de forma aditiva.

## Compatibilidade

Campos legacy e DTOs existentes não foram removidos. Structured prescription, presentation e execution sequence são aditivos.

## Deferred

- Workout Builder V3 set types
- edição estrutural durante sessão
- modelo avançado de carga/resistência
- targets por lado
- Endurance Builder V2
- auto-rest global
- atualização explícita da fonte após Finish
- polish visual adicional
