# Routes — Web V1

`Minhas Rotas` transforma um track de Activity em um recurso reutilizável sem duplicar a Activity inteira.

## Modelo

A migration `20260915_web_product_expansion_v1.sql` cria:

- `rotas_salvas`;
- `rotas_salvas_atividades`.

Uma Route pode armazenar:

- owner;
- nome;
- modalidade compatível;
- Activity de origem;
- coordenadas do percurso;
- distância;
- ganho/perda de elevação;
- perfil de elevação quando disponível;
- privacidade;
- Pacer Plan opcional;
- último uso;
- estado arquivado.

A Route não copia duração, HR, cadence, séries, título ou outros dados pessoais irrelevantes da Activity.

## Salvar da Activity

No Activity Detail V3, `Salvar rota` chama `/api/rota-salvar.php`.

`routeSavedCreateFromActivity()`:

- valida ownership;
- exige rota GPS válida;
- reutiliza uma Route já ligada à Activity em vez de duplicá-la;
- registra a Activity de origem.

## Lista e detalhe

`/user/rotas.php` oferece:

- lista owner-scoped;
- mini mapa lazy;
- mapa grande no detalhe;
- distância e elevação;
- modalidade;
- Activity de origem;
- histórico explícito de Activities que usaram a Route;
- Pacer associado;
- arquivamento;
- ação `Usar em treino`.

Mapas usam `public/assets/js/web-map.js` com Leaflet/OpenStreetMap, a mesma infraestrutura do Activity Detail.

## Route + Workout

Workouts podem persistir `route_id`. O Core valida owner, estado arquivado e compatibilidade de modalidade por `routeSavedValidateForWorkout()`.

Ao concluir uma Activity vinculada a um Workout com Route, `routeSavedLinkActivityFromWorkout()` registra explicitamente a relação Activity ↔ Route e atualiza o último uso.

Isso permite comparação de Activities da mesma Route sem reconhecimento geográfico fuzzy.

## Route + Pacer

Uma Route pode referenciar um Pacer Plan compatível. Nesta versão isso é somente vínculo de produto; não existe ajuste automático de pace por terreno.

## Privacidade

Routes pertencem ao usuário e não criam exposição pública nova. A UI e os serviços Web continuam owner-scoped.
