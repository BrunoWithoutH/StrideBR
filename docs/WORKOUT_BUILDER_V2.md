# Workout Builder V2

## Escopo

O Workout Builder V2 separa cinco conceitos que antes apareciam misturados na edição de exercícios:

1. `tracking_mode`: o que o exercício mede por padrão;
2. `prescription_method`: como o exercício é prescrito;
3. rep target: qual é a meta de repetições da série/etapa;
4. group: relação entre exercícios;
5. endurance step: estrutura temporal existente do treino.

A implementação é aditiva. Campos legacy continuam disponíveis e históricos concluídos não são reinterpretados.

## Tracking mode

A Exercise Library V2 continua sendo a fonte dos defaults de UI:

- `load_reps`;
- `reps`;
- `duration`;
- `distance`;
- `duration_distance`.

Tracking mode não representa método de treino.

## Prescription method

Métodos estruturados V2:

- `standard`;
- `cluster`;
- `drop_set`.

Exercícios sem método explícito são tratados como `standard`.

A configuração estruturada é versionada e validada por método em `workout_prescription_v2.php`. Propriedades arbitrárias não fazem parte do contrato.

## Rep target

Targets suportados:

- `fixed`;
- `range`;
- `amrap`;
- `failure`.

AMRAP e failure são metas, não métodos de exercício. O actual continua sendo o que foi realizado; AMRAP não é materializado como `0`.

## Cluster

Cluster guarda explicitamente:

- quantidade de blocos;
- microclusters em ordem;
- carga opcional;
- pausa intra-cluster;
- descanso entre blocos.

Clusters irregulares, como `4+4+3`, permanecem estruturados. Na criação da sessão cada microcluster vira um segmento executável com `block_index`, `stage_index` e target planejado próprios.

## Drop set

Drop set guarda explicitamente:

- rodadas;
- stages ordenados;
- carga opcional por stage;
- rep target por stage;
- descanso entre stages;
- descanso entre rodadas.

Na sessão cada queda vira um segmento `drop_stage`. Planned e actual permanecem separados.

## Groups

Superset e Circuit são entidades de grupo, não métodos do exercício.

`grupos_prescricao` possui parent único entre treino, modelo, agendamento ou sessão e armazena:

- `tipo` (`superset|circuit`);
- `voltas`;
- descanso entre exercícios;
- descanso após volta;
- ordem.

Um grupo com menos de dois exercícios é dissolvido pelo Builder. Para retirar um exercício existe uma ação explícita de remover do grupo; o drag dentro do grupo reordena os membros e o grupo inteiro pode ser reordenado como unidade.

## Builder Web

Cronograma e Workout Model usam `src/layout/workout_builder.php` e `public/assets/js/workout-builder.js`.

O editor usa cards compactos e abre os controles somente quando necessário. O `tracking_mode` controla os campos principais. Campos raros e custom fields ficam em progressive disclosure.

O picker consulta `/api/exercicio-resolver.php`; o catálogo completo não é carregado como um select gigante.

Reorder possui:

- drag pela handle;
- Mover para cima/baixo;
- teclado com Alt+Seta;
- manutenção de foco;
- anúncio por `aria-live`.

## Distance units

Armazenamento estruturado de distância é normalizado para metros. Unidade de display é uma camada separada.

Defaults contextuais incluem:

- corrida longa: km;
- intervalo de pista: m;
- natação: m;
- lançamentos/arremessos/saltos: m.

Uma marca de dardo de `54,73 m` permanece `54,73 m`; não é apresentada obrigatoriamente como km.

## Session snapshot

Ao iniciar uma Workout Session, método, configuração e grupo são copiados para o snapshot da sessão. Alterações posteriores no treino fonte não modificam a sessão ativa.

As séries materializadas mantêm metadata de segmento e targets planejados. Completion state não copia target para actual.

## Activity history

Ao finalizar a sessão, os actuals continuam nos campos de realizado e metadata estruturada é preservada em `series_exercicio_atividade` para diferenciar cluster/drop stages no histórico.

## Copy paths

Structured prescription deve sobreviver aos seguintes caminhos, cobertos pela suíte PostgreSQL V2:

- schedule → duplicate schedule;
- schedule → model;
- model → schedule;
- coach → athlete;
- scheduled → session snapshot;
- export → import.

IDs de grupo são remapeados ao copiar entre parents; não se reutiliza FK do objeto fonte.

## Backward compatibility

DTOs existentes permanecem aditivos. Clientes antigos continuam recebendo os campos legacy. Campos estruturados são opcionais.

Para sessões, `structured_prescription.client_capability = structured_prescription_v1` sinaliza suporte necessário para uma UX completa de Cluster/Drop/Groups.

Clientes sem essa capability podem continuar lendo séries materializadas, mas não devem afirmar que oferecem edição/execução estruturada completa.
