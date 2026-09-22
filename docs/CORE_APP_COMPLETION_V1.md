# Core App Completion V1

Esta rodada fecha no Core os contratos necessários para o Android operar como cliente principal sem criar um segundo domínio Mobile. Código real e tabelas existentes continuam sendo a fonte de verdade.

## Progress por modalidade

Endpoints de Progress que aceitam `sport` resolvem modalidade por ID ou slug canônico, somente entre modalidades ativas e visíveis ao owner. Modalidade custom de outro usuário não pode ser usada como filtro.

Modalidade válida sem dados retorna `200` com shape canônico vazio. Métrica indisponível ou não aplicável continua `null`; zero é reservado para ausência real de ocorrência/quantidade somável.

`/progress/dashboard` expõe o bloco compatível com a família da modalidade e mantém o bloco não aplicável como `null`.

## Catálogo de modalidades

`GET /sports` é o catálogo para criar/editar experiências de registro. Ele não deve ser substituído por `/progress/sports`, que continua sendo um agregado das modalidades presentes nas Activities do período.

O catálogo retorna modalidades globais ativas e modalidades custom owner-scoped, com `id`, `slug`, `name`, `family` e `route_capable`. Filtros opcionais: `q` e `family`.

## Activity manual e detalhe strength

`POST /activities/manual` exige `Idempotency-Key`, não exige GPS e reutiliza `registros_atividade`, valores do modelo e `series_exercicio_atividade`.

Strength manual recebe `strength_exercises` e persiste séries pelo domínio `atividadeForcaPersistirSeriesManuais()`. O sucesso retorna Activity Detail completo.

`GET /activities/{id}` para strength expõe `strength_exercises` a partir do realizado. Targets planejados não são convertidos em actuals. Ausência de rep/carga/duração/distância continua `null`.

## Editar e excluir Activity

Activity Detail expõe `version`, `updated_at` e `capabilities`.

`PATCH /activities/{id}` exige `if_version` e usa optimistic concurrency; PATCH sem versão é rejeitado para não existir last-write-wins silencioso. Campos gerais incluem título, notas, esforço, visibilidade e equipamentos. Data/duração, distância manual e `strength_exercises` só são editáveis quando as capabilities estruturais permitem: Activity manual/API, sem rota e sem Workout Session.

Activity GPS não recebe distância arbitrária por PATCH. Activity derivada de Workout Session não permite sobrescrever séries realizadas.

`DELETE /activities/{id}` usa o soft delete existente, owner-scoped e retry-safe. Activities excluídas deixam as consultas normais de Activities/Progress.

## Workout preview Web

`public/api/cronograma-treino-preview.php` resolve a ocorrência em três modos:

- `planned`: ocorrência futura/atual ainda não realizada, mostra prescription;
- `performed`: Activity concluída e validamente vinculada ao workout/owner, mostra actuals;
- `missed`: ocorrência passada sem Activity concluída, mostra prescription identificada como não realizada.

Em strength performed, somente exercícios/séries realmente presentes na Activity são retornados. Exercício planejado completamente ignorado não aparece como realizado.

O `activity_id` recebido como contexto nunca é confiado isoladamente: Activity precisa pertencer ao usuário, estar concluída, não excluída e corresponder ao workout/ocorrência.

## Execution summary Mobile

`GET /workout-sessions/by-workout/{workoutId}` expõe execução real de sessão concluída com `started_at`, `ended_at`, `activity_id`, Activity e exercícios/sets realizados. Sets carregam `actual_repetitions`, `actual_load`, `actual_duration_s`, `actual_distance_m` e `completed`.

Quick Register retorna `execution_mode=quick_register`, `available=false`, Activity quando disponível e `exercises=[]`. O Core não fabrica execução série por série.

## Série extra

`POST /workout-sessions/{sessionId}/exercises/{exerciseId}/sets` exige `Idempotency-Key`. O Core gera ID, usa `MAX(numero)+1` e inicia actuals como `null`. Retry da mesma operação não cria outra série.

A série extra usa o mesmo SET_STATE, Finish e persistência em `series_exercicio_atividade` das séries planejadas.

## Perfil

`PATCH /me` permite os campos simples do perfil Web: `name`, `username`, `bio`, `phone` e `birth_date`. Email, senha e exclusão de conta permanecem fora do endpoint.

Username reutiliza normalização, formato, palavras reservadas e unicidade do Core Web.

## Pessoas

A API `/people/*` reutiliza `amizades`, notificações e o domínio de `vinculos_treinador_atleta`. O DTO público não transporta email, telefone ou nascimento. Privacidade, discoverability, ownership e `friends.enabled`/`trainer.enabled` são aplicados no Core.

Contrato completo: [`PEOPLE_API_V1.md`](PEOPLE_API_V1.md).

## Contratos Mobile

Visão operacional: [`MOBILE_API_COMPLETION_V1.md`](MOBILE_API_COMPLETION_V1.md).

Progress: [`MOBILE_PROGRESS_API.md`](MOBILE_PROGRESS_API.md).

OpenAPI: [`api/openapi.yaml`](api/openapi.yaml).

## Fora desta rodada

FIT/GPX/TCX import no Mobile, route trim, feed social, likes/kudos, chat, ranking, Goals/PBs novos, GPS, Maps, Wear, iOS e Teams não fazem parte deste contrato.

## Contract Freeze V1.1

Core App Completion V1.1 congela este contrato para o Android sem adicionar uma nova feature de produto. O contrato final está em [`MOBILE_CONTRACT_FREEZE_V1.md`](MOBILE_CONTRACT_FREEZE_V1.md).

Pontos congelados que merecem atenção de cliente:

- Activity strength `duration_s` é JSON `number|null`;
- PATCH de Workout Session usa `actual_repetitions`, `actual_load`, `actual_duration_s` e `actual_distance_m` como campos canônicos;
- Append Set não exige body e retorna `data.session`, `data.set_id` e `data.reused`;
- execution summary mantém `actual_repetitions`/`actual_load` como `string|null` e os aliases nunca recebem prescription;
- Person usa `display_name`;
- Coaching expõe `trainer` e `athlete` separadamente.
