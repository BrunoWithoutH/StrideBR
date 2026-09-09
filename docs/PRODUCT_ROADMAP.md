# StrideBR — roadmap de produto

Última organização: 2026-09-08.

Este documento concentra o backlog de produto e a direção das próximas versões. Nem todo item possui versão fechada. A prioridade é publicar e estabilizar a Web 1.0 antes de abrir frentes grandes em paralelo.

A visão de longo prazo está em `PRODUCT_VISION.md`.

## Legenda

- **NOW** — prioridade de release atual.
- **NEXT** — candidato às primeiras versões após a 1.0.
- **PLANNED** — planejado, sem versão fechada.
- **2027** — frente estratégica com alvo de trabalho em 2027.
- **LAB** — ideia de longo prazo ou que exige validação técnica/dados.

## Ciclo central

```text
planejar → executar → registrar → analisar → evoluir
```

Com treinador/equipe:

```text
treinador planeja → atleta executa → StrideBR registra → treinador acompanha → próximo treino
```

# 1. Web 1.0 — fechar antes de expandir

## NOW — release e confiabilidade

- Concluir smoke real e checklist da Web 1.0.
- Corrigir apenas bugs bloqueadores, regressões e problemas de publicação.
- Manter migrations, autenticação, permissões, importações e integrações estáveis.
- Validar rotas, GPS Web, compartilhamento, atividades, cronogramas, metas, eventos e equipamentos em ambiente real.
- Não puxar Teams, analytics avançado ou novas áreas grandes para dentro da 1.0.

## Pós-release imediato

- Corrigir problemas observados com usuários reais.
- Melhorar desempenho de páginas lentas e estados de loading.
- Refinar edição de sessões e recuperação após fechamento/reload.
- Fechar diferenças entre planejado e realizado que já possam ser resolvidas com a estrutura atual.

# 2. Activity Analytics V2

## NEXT — Timeline da atividade

Criar uma timeline temporal/distância que represente a atividade inteira e permita combinar streams.

Streams candidatos:

- ritmo;
- velocidade;
- frequência cardíaca;
- cadência;
- potência;
- elevação;
- temperatura;
- pausas;
- voltas;
- trechos;
- equipamento;
- eventos/marcadores;
- música quando disponível.

A timeline deve poder alternar eixo por tempo ou distância.

## NEXT — análise por janelas

Permitir agregação por:

- 1 min;
- 5 min;
- 10 min;
- 1 km;
- volta;
- trecho;
- intervalo personalizado.

## PLANNED — eficiência e deriva

- Relação entre performance e esforço ao longo da atividade.
- Evolução de pace/velocidade para frequência cardíaca semelhante.
- Comparação início × meio × fim.
- Cardiac drift/decoupling quando os dados suportarem.
- Métricas devem explicar o cálculo e evitar interpretações clínicas.

## NEXT — comparação de atividades

- A/B com gráficos sobrepostos.
- Comparar ritmo, FC, elevação, cadência, potência e zonas.
- Destacar mesma rota ou percurso semelhante.
- Comparar voltas e trechos equivalentes.

## PLANNED — comparação de períodos

Exemplos:

- últimas 4 semanas × 4 anteriores;
- mês atual × anterior;
- temporada atual × passada.

Métricas:

- volume;
- duração;
- número de atividades;
- intensidade;
- pace/velocidade;
- FC;
- eficiência;
- elevação;
- esporte.

# 3. Activity Data V2 e importação enriquecida

## NEXT — preservar streams

A importação FIT/TCX/GPX e integrações futuras devem preservar, quando disponíveis:

- timestamps;
- latitude/longitude;
- elevação;
- velocidade;
- frequência cardíaca;
- cadência;
- potência;
- temperatura;
- laps/voltas;
- pausas;
- eventos;
- sensores;
- dispositivo de origem;
- campos específicos do formato.

Evitar reduzir arquivos ricos a apenas distância, duração e geometria.

## PLANNED — Smart Activity Merge

### Mesclar gravações interrompidas

Unir duas ou mais atividades que pertencem à mesma sessão sem perder o original/histórico de edição.

### Separar e corrigir

- dividir uma atividade;
- ajustar começo/fim;
- corrigir associação de trechos;
- desfazer merge quando possível.

### Jornadas/sessões compostas

Agrupar atividades relacionadas de vários dias ou modalidades sem obrigatoriamente apagar os registros individuais.

### Multi-device merge

Combinar streams sincronizados por timestamp de dispositivos diferentes.

Exemplo:

```text
Dispositivo A → GPS + cadência + potência
Dispositivo B → frequência cardíaca
StrideBR → atividade combinada
```

Permitir escolher qual fonte tem prioridade para cada stream quando houver conflito.

# 4. Voltas, Trechos e Segmentos

## NEXT — Voltas

Volta é uma marcação sequencial instantânea durante a atividade. Ao marcar uma volta, a anterior termina e a próxima começa imediatamente.

No app mobile, deve existir um controle rápido de `Volta`.

## PLANNED — Trechos editáveis

Trecho é um intervalo arbitrário dentro de uma atividade.

O usuário poderá:

- iniciar trecho;
- finalizar trecho;
- nomear;
- mover começo/fim;
- dividir;
- juntar;
- excluir;
- classificar.

Trechos continuam dentro da mesma atividade.

## PLANNED — detecção automática de trechos/intervalos

Analisar a atividade e sugerir blocos como:

- aquecimento;
- tiros;
- recuperação;
- subida;
- desaquecimento.

A sugestão nunca deve alterar o registro sem confirmação.

## LAB — Segmentos geográficos

Segmento é um percurso geográfico persistente reutilizado entre atividades.

Se implementado, evitar a poluição comum em plataformas com excesso de segmentos:

- distância mínima configurável;
- ocultar;
- favoritos;
- relevância;
- filtros;
- segmentos privados/pessoais antes de ranking público.

Não é prioridade de curto prazo.

# 5. Treinos estruturados

## NEXT

Criar editor estruturado para corrida e esportes compatíveis com blocos como:

- aquecimento;
- esforço;
- recuperação;
- repetição;
- desaquecimento;
- alvo por tempo;
- alvo por distância;
- alvo por ritmo/velocidade;
- alvo por FC/potência quando aplicável.

## PLANNED — planejado × realizado

Comparar cada bloco da prescrição com o resultado executado.

Essa frente deve reutilizar Activity Timeline, voltas e trechos, em vez de criar um modelo paralelo.

# 6. Mobile

## PLANNED — aplicativo dedicado

Prioridades do app nativo:

- GPS confiável;
- gravação em segundo plano;
- funcionamento offline;
- persistência local;
- recuperação de atividade;
- tela focada durante exercício;
- sincronização posterior com o servidor.

## Controles durante a atividade

- Volta;
- Iniciar trecho;
- Finalizar trecho;
- Pausar/retomar;
- marcadores rápidos configuráveis.

Marcadores futuros podem representar água, terreno, desconforto, troca de equipamento ou observação rápida.

# 7. Gear V2

## NEXT — equipamentos com análise real

Por equipamento:

- distância;
- duração;
- atividades;
- primeiro e último uso;
- terreno;
- elevação;
- modalidades;
- histórico;
- tendência de uso;
- recordes/contextos associados.

## PLANNED — filtros por equipamento

Filtrar atividades por tênis, bicicleta ou outro equipamento e permitir comparação entre equipamentos.

Comparações devem considerar contexto para não concluir que um equipamento é melhor apenas porque foi usado em treinos mais rápidos.

## PLANNED — equipamento por trecho

Permitir associar equipamentos diferentes a trechos da mesma atividade.

Exemplo:

```text
Aquecimento → tênis A
Tiros → tênis B
Desaquecimento → tênis A
```

A quilometragem de cada equipamento deve ser calculada automaticamente.

# 8. Music & Performance

## PLANNED — trilha sonora da atividade

Registrar metadados do que estava tocando ao longo da atividade, sem hospedar arquivos de áudio.

Dados possíveis:

- timestamp;
- faixa;
- artista;
- álbum;
- serviço;
- identificador externo.

## Integrações futuras

- Spotify;
- Apple Music;
- YouTube Music;
- Last.fm;
- metadados locais do dispositivo quando tecnicamente possível.

## Analytics

Relacionar música com a timeline:

- música durante melhor km;
- pace/velocidade média por faixa;
- FC média;
- músicas recorrentes em PRs;
- playlist da atividade;
- trilha sonora de prova;
- share card com música.

Esta frente é considerada um possível diferencial de identidade do StrideBR.

# 9. Routes V2

## PLANNED — mapa de atividades e rotas

- visualizar atividades/rotas em um mapa agregado;
- filtros por esporte, período e tipo;
- rotas salvas;
- sobreposição de percursos;
- identificar caminhos já percorridos.

Exploração/gamification é secundária e não deve dirigir o produto.

## PLANNED — comparar repetições de rota

Quando o usuário repete um percurso:

- comparar tempo;
- pace/velocidade;
- FC;
- elevação;
- eficiência;
- condições disponíveis.

## PLANNED — gerador inteligente de rotas

Entrada possível:

- ponto de partida;
- distância-alvo e tolerância;
- ganho de elevação desejado;
- superfície;
- circuito / ida-e-volta / A→B;
- inclinação máxima;
- passar por determinado local;
- evitar determinada área;
- evitar vias principais;
- priorizar áreas ainda não percorridas.

O StrideBR deverá gerar múltiplos candidatos e pontuar as opções por aderência ao pedido.

Dados de segurança/iluminação só devem ser usados quando houver fonte confiável. Não inferir segurança sem base suficiente.

# 10. Histórico, filtros e estatísticas

## NEXT — filtros avançados

Histórico por:

- esporte;
- período;
- distância;
- duração;
- equipamento;
- rota;
- treino;
- PR;
- origem/fonte;
- com GPS;
- com FC;
- com música;
- outras propriedades relevantes.

## NEXT — excluir atividade de estatísticas

Permitir manter uma atividade no histórico sem deixá-la afetar:

- recordes;
- PRs;
- médias;
- progressão;
- resumos e tendências.

A ação deve ser reversível e claramente indicada.

# 11. Events V2

## NEXT/PLANNED

Evoluir o módulo existente para:

- próximos eventos no perfil;
- countdown;
- participação do usuário;
- prova/modalidade;
- resultado;
- tempo/marca;
- colocação;
- recorde pessoal;
- histórico de eventos;
- comparação entre participações.

Quando necessário, Events poderá apoiar contextos institucionais por contratos explícitos com Teams; detalhes, ownership e implementação pertencem à documentação canônica do Teams.

# 12. Visões específicas por esporte

## PLANNED

Evitar uma única tela de analytics para todos os esportes.

Exemplos:

### Corrida

- pace;
- FC;
- cadência;
- splits;
- elevação.

### Ciclismo

- velocidade;
- potência;
- cadência;
- elevação.

### Musculação

- exercício;
- carga;
- reps;
- volume;
- progressão.

### Esportes de equipe/racquete/combate

Usar métricas e estruturas compatíveis com cada modalidade, sem forçar distância/pace onde não fazem sentido.

# 13. Colaboração entre usuários

## PLANNED — programas e cronogramas compartilhados

Evoluir o compartilhamento atual para permissões explícitas:

- `owner`;
- `editor`;
- `viewer`.

Possibilidades:

- um usuário cria e outro visualiza;
- um usuário cria e ambos editam;
- múltiplos editores;
- histórico de alterações importantes;
- revogação de acesso;
- execução individual por participante.

## PLANNED — atividades realizadas em conjunto

Permitir associar dois ou mais usuários à mesma sessão/atividade social sem obrigar que exista uma equipe formal.

A colaboração entre amigos deve continuar útil mesmo fora do StrideBR Teams.

# 14. Personalização da interface

## PLANNED

À medida que o produto crescer:

- ocultar módulos não utilizados;
- fixar módulos importantes;
- ordenar áreas do dashboard/home;
- manter navegação principal previsível.

Evitar transformar a interface em um construtor de dashboard complexo.

# 15. Relação futura com StrideBR Teams

## 2027 — integração de produto, não backlog duplicado

O [StrideBR Teams](https://github.com/BrunoWithoutH/StrideBR-Teams) é o produto institucional pago do ecossistema, com repositório, roadmap, releases e deploy próprios. O piloto desejado para 2027 é IFFar — Campus Frederico Westphalen; seu domínio planejado é `teams.stridebr.com.br`.

O Core permanece gratuito e open source. O Trainer individual do Core também permanece gratuito: vínculo treinador-atleta, acompanhamento autorizado, prescrição, compartilhamento e planejamento individual não dependem de Teams.

Este roadmap mantém somente capacidades do Core que possuem valor próprio e podem futuramente apoiar Teams, como treinos estruturados, planejado × realizado, timeline de atividades, colaboração, integrações e eventos. Requisitos institucionais — organizações, memberships, equipes, papéis, billing, ownership, UX e MVP Teams — são definidos no repositório canônico do Teams, não aqui.

Core e Teams usam uma identidade StrideBR compartilhada, mas isso não define SSO, sessão, banco, storage, API ou eventos. Integrações futuras devem estabelecer contratos explícitos, ownership e autorização contextual; não devem pressupor acesso direto a tabelas internas do Core.

# 16. Administração, infraestrutura de produto e API

## PLANNED

- analytics administrativos por período;
- audit log com filtros;
- moderação quando conteúdo público crescer;
- feature flags por ambiente;
- API pública/para clientes oficiais quando o domínio estiver estabilizado;
- clientes mobile consumindo o mesmo domínio de negócio;
- PWA/offline seletivo onde fizer sentido.

# 17. Discovery e social — baixa prioridade

## PLANNED/LAB

- páginas públicas escolhidas pelo usuário;
- busca de pessoas/planos/conteúdo público;
- descoberta de rotas;
- exploração de novos lugares;
- recursos sociais utilitários.

Não priorizar:

- feed infinito;
- ranking social como centro do produto;
- gamification que desvie do treinamento;
- mecanismos de engajamento sem utilidade esportiva.

# 18. Diferenciais de identidade

Marcar como frentes estratégicas, não necessariamente como próximas entregas:

- **Activity Timeline rica**;
- **Smart Activity Merge**;
- **Gear Intelligence e equipamento por trecho**;
- **Music & Performance**;
- **Smart Routes**;
- **integração futura com StrideBR Teams**, conforme suas fronteiras explícitas.

# 19. Sequência sugerida

Sem amarrar números de versão antes da 1.0 estabilizar:

## Fase A — publicar e estabilizar

- Web 1.0;
- bugs;
- performance;
- confiabilidade.

## Fase B — dados e analytics

- importação enriquecida;
- timeline;
- filtros;
- comparação;
- Gear V2;
- treinos estruturados.

## Fase C — atividade avançada

- trechos;
- merge;
- multi-device;
- planejado × realizado aprofundado.

## Fase D — rotas e mobile

- Routes V2;
- gerador inteligente;
- app dedicado;
- controles de volta/trecho.

## Fase E — diferenciais

- Music & Performance;
- analytics mais profundos;
- mapas agregados;
- recursos de descoberta opcionais.

## Relação com Teams — em paralelo controlado durante 2027

- acompanhar discovery e prioridades pelo repositório canônico do Teams;
- evoluir no Core apenas capacidades que também tragam valor a usuários individuais;
- definir contratos de integração antes de qualquer dependência entre produtos.

# 20. Princípios de priorização

Antes de puxar uma ideia para desenvolvimento, perguntar:

1. Resolve um problema real observado?
2. Depende de uma fundação de dados que ainda não existe?
3. Pode ser implementada sem comprometer privacidade e confiabilidade?
4. É melhor como recurso geral ou específico de esporte?
5. O usuário entenderá o que a métrica significa?
6. Estamos adicionando utilidade ou apenas complexidade visual?
7. Esta feature ajuda o ciclo `planejar → executar → registrar → analisar → evoluir`?
8. Precisa entrar agora ou ficará melhor depois de estabilizarmos outra camada?

# 21. Ideias deliberadamente não priorizadas agora

- previsão automatizada de lesões;
- diagnósticos clínicos;
- marketplace aberto de treinadores;
- cadastro irrestrito de instituições;
- feed social infinito;
- ranking como mecanismo principal;
- hospedagem de música/vídeo sem necessidade;
- gamification como foco central;
- IA adicionada apenas por marketing.
