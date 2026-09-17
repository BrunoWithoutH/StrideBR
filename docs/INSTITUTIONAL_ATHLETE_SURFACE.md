# Institutional Athlete Surface v1

## Papel do Core

O StrideBR Teams é a fonte institucional de verdade. O Core é a superfície principal do atleta. Esta integração não copia o banco do Teams e não escolhe ainda o transporte futuro entre os produtos.

A fronteira está em `src/function/teams_surface_provider.php`. Views, agenda, competitions e APIs consomem essa interface lógica em vez de conhecer banco, arquivos ou entidades internas do Teams.

## Feature flags

O estado padrão é:

```text
STRIDEBR_TEAMS_ENABLED=false
STRIDEBR_TEAMS_SURFACE_MODE=disabled
```

`STRIDEBR_TEAMS_ENABLED` controla se qualquer experiência institucional existe no Core. Quando false:

- não há item Minhas equipes;
- páginas exclusivamente institucionais respondem 404;
- Home não mostra contexto institucional;
- Agenda não compõe prescriptions Teams;
- competições institucionais não aparecem;
- `/api/v1/workouts/schedule` retorna somente fontes Core;
- IDs `teams:*` não resolvem detalhes;
- o provider institucional não é consultado.

`STRIDEBR_TEAMS_SURFACE_MODE` controla a origem da projection. A v1 implementa `disabled` e `fixture`. `remote` é reservado e permanece indisponível.

Fixture só é aceita em `development`. `production + fixture` e `staging + fixture` falham fechados e não expõem dados demonstrativos.

## Provider e identity_ref

A interface trabalha com `identity_ref` conceitual. O fixture possui um mapeamento explícito e exato de desenvolvimento do username `brunowithouth` para a referência demonstrativa de Bruno Evaristo; nome de exibição não concede vínculo institucional. Username, slug e `idusuario` Core não são declarados como chave cross-service definitiva.

Operations atuais:

- athlete context;
- my teams;
- athlete-safe roster;
- assigned published trainings;
- training detail autorizado;
- athlete competitions;
- institutional competition detail do próprio atleta.

Falha do provider produz `[]`, `null` ou availability state. Ela não derruba Home, Agenda ou o restante do Core.

## Privacy allowlist

O adapter do Core normaliza as respostas do provider por allowlist antes de entregá-las às views/API. Assim, mesmo um provider futuro que envie campos extras por engano não os propaga automaticamente.

A projection de roster permite somente:

- `person_ref` opaco;
- `display_name`;
- `roles`;
- `group` quando aplicável.

A surface não transporta email, telefone, endereço, nascimento completo, emergência, Membership internals, Workspace Grant, availability, lesão, dor, fadiga, sono, readiness, notas privadas, execução de colegas, Activities privadas, GPS, route ou heart rate.

Estar na mesma Team não autoriza enriquecimento silencioso com dados pessoais existentes no Core e não cria Friendship.

## Web surfaces

Quando a feature está ativa:

- `/user/equipes.php` — Minhas equipes;
- `/user/equipe.php?team=<opaque>&season=<opaque>` — visão geral, meu papel/grupo, roster athlete-safe, trainings e competitions relevantes;
- `/user/treino-institucional.php?id=<opaque>` — prescription read-only e execução quando permitida;
- `/user/competicao-institucional.php?id=<opaque>` — delegação, minhas inscrições e meus resultados;
- Home — no máximo um bloco compacto da minha equipe;
- Agenda mensal — prescriptions institucionais no calendário existente;
- Competições — pessoais e institucionais continuam fontes distintas.

Não existe navegação top-level Teams.

## Workouts

O contrato do Core acrescenta:

```text
source = teams
kind = institutional
```

O ID do workout é opaco. `teams:` é namespace interno da v1; tudo depois do prefixo é tratado como opaque ref.

O schedule solicita ao provider apenas prescriptions publicadas destinadas ao atleta dentro de `from..to` e as ordena junto com workouts Core.

`institutional_context` pode carregar organização, equipe, temporada, `training_ref`, planning block e competição. O objeto é aditivo.

Prescriptions Teams são read-only. Capabilities não são inferidas pelas regras de workout pessoal. Na v1 somente `can_start_session` pode ser habilitada explicitamente pelo provider. GPS, Quick Register e conclusão manual ficam bloqueados.

## Workout Session e Activity

Fluxo:

```text
Teams Published Training
→ Core WorkoutSession
→ Core Activity
```

Ao iniciar, o Core salva apenas o snapshot necessário à execução:

- título;
- modalidade resolvida quando possível;
- data/hora planejadas;
- estrutura tipada;
- external training ref;
- external recipient ref;
- contexto institucional mínimo.

Exercícios externos podem usar `nome_snapshot` com `idexercicio=null`; não precisam virar catálogo pessoal.

A Session permanece executável mesmo se a projection externa mudar depois do start. A Activity final continua em `registros_atividade`, Core-owned. O elo institucional fica pela Workout Session.

## Execution acknowledgement

`stridebr_teams_execution_ack_payload()` constrói um payload futuro com:

- `training_ref`;
- `recipient_ref`;
- `status`;
- `started_at`;
- `completed_at`;
- `activity_ref`.

A v1 não envia HTTP, webhook, evento ou fila. O payload não inclui GPS, route, heart rate nem notas privadas. `activity_ref` não é autorização para abrir a Activity.

## Competitions

`competicoes_usuario` continua o domínio pessoal e editável do Core. Competition institucional é uma projection read-only e não é salva automaticamente nessa tabela.

A surface institucional mostra apenas informações do próprio atleta: delegação, inscrições e resultados associados a ele. Structured Result não cria PB, SB, record, benchmark ou conclusão de meta automaticamente.

## Limitações atuais

- nenhum transporte `remote` implementado;
- nenhuma sincronização de acknowledgement;
- nenhum cache cross-service definitivo;
- GPS start de institutional workout fica false;
- Quick Register e complete manually ficam false;
- Mobile ainda não renderiza `institutional_context`;
- canonical identity key entre produtos continua aberta.

## Estratégia de lançamento

Código integrado não significa feature lançada.

```text
Fase 1: produção atual — flag OFF
Fase 2: desenvolvimento interno — flag ON + fixture
Fase 3: piloto — flag ON + provider real para elegíveis
Fase 4: rollout mais amplo
```

A flag global é a primeira barreira. Entitlements, pilot allowlist e elegibilidade poderão ser acrescentados no futuro.

## Mobile API read surface

A mesma projection athlete-safe também está disponível para clientes Mobile pela API v1 do Core:

```text
GET /api/v1/institutional/context
GET /api/v1/institutional/teams/{team_ref}/roster?season={season_ref}
GET /api/v1/institutional/competitions
GET /api/v1/institutional/competitions/{competition_ref}
```

A fronteira continua em camadas:

```text
Teams source
→ Teams Surface Provider
→ Core projection normalizer
→ API serializer allowlist
→ Mobile
```

O serializer da API repete a allowlist de forma explícita. Arrays do provider nunca são devolvidos crus. O context não expõe `person_ref`, availability state, provider mode ou identity mapping interno; roster e competitions preservam somente os campos athlete-safe descritos neste documento.

`STRIDEBR_TEAMS_ENABLED=false` torna essas rotas inexistentes (`404`) antes de consultar o provider. Com a feature ligada e usuário sem vínculo institucional, `GET /institutional/context` responde `200` com `my_teams=[]`. Team/Season ou Competition não visível ao viewer responde `404` para não revelar existência.

A Season do roster é obrigatória e continua opaca. O cliente não deve inferir 2027, reutilizar roster de outra Season nem desmontar `team_ref`, `season_ref` ou `competition_ref`.

Falha/indisponibilidade do provider com a feature ligada falha fechada: context/list podem permanecer vazios e roster/detail não retornam projection parcial. Nenhuma exception interna é exposta ao cliente.

Same Team continua não equivalendo a acesso ao perfil Core completo, e membership de Organization não concede visibilidade cross-Team.
