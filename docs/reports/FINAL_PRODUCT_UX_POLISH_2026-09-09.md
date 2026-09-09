# StrideBR Web 1.0 — Final Product/UX Polish

Data: 2026-09-09
Fonte de verdade: `stridebr(20260909-034410).zip`
HEAD recebido: `bc52fd1 chore(release): prepare StrideBR 1.0.0-rc.5`

## Resultado

A rodada foi concluída sobre o working tree recebido, sem resetar nem substituir as alterações anteriores de Integrações/SEO. O trabalho desta etapa ficou restrito ao polimento da Web 1.0 pedido: navegação parcial de workspaces, Histórico/seleção em lote, reset de senha, resolução de exercícios, nomenclatura, Biblioteca, Treinador, Progresso e copy.

Não houve commit, tag, push, deploy, alteração de versão ou feature pós-1.0. Nenhuma migration foi criada ou modificada.

## 1. Páginas e componentes auditados

- `public/assets/js/cronogramas.js`
- `public/assets/js/agenda-mensal.js`
- `public/assets/js/atividades.js`
- `public/assets/js/library.js`
- `public/assets/js/progresso.js`
- `public/assets/js/trainer.js`
- `public/user/cronogramatreinos.php`
- `public/user/agenda-mensal.php`
- `public/user/atividades.php`
- `public/user/biblioteca.php`
- `public/user/treinador.php`
- `public/user/progresso.php`
- `public/forgot-password.php`
- `public/reset-password.php`
- `public/verify-email.php`
- `src/includes/auth.php`
- presenter e pipeline de atividades/força
- helpers de cronograma/exercícios
- layouts de Trainer
- i18n PT-BR/EN
- CSS de Atividades, Cronogramas/Biblioteca e Progresso

A auditoria confirmou que Agenda mensal, Atividades, Biblioteca e Progresso já possuíam infraestrutura parcial/History API que deveria ser preservada.

## 2. Reloads encontrados

O reload desnecessário comprovado estava na troca de cronograma em `cronogramas.js`: o seletor alterava `window.location.href` embora o mesmo módulo já possuísse `refreshScheduleView()`.

Também foi identificado no fechamento da rodada que excluir um treino ainda fazia POST/redirect completo, enquanto criar treino, editar treino, reagendar ocorrências e navegar períodos já tinham caminhos assíncronos.

A troca de atleta em Trainer tinha uma tentativa de refresh parcial no working tree, mas continha dois problemas: movia o fragmento da lista antes de localizar sua seção de origem e não conseguia voltar por History para o estado sem atleta selecionado.

## 3. Navegação/revalidation parcial final

### Cronogramas

- troca de cronograma: `fetch` + History API + `refreshScheduleView()`;
- Semana/Mês/Lista: troca local sem full reload;
- semana anterior/próxima/atual: links reais interceptados para refresh parcial;
- mês anterior/próximo/hoje: navegação parcial e cache mensal existente preservado;
- `popstate`: revalida o workspace correspondente;
- `href` real continua presente como fallback sem JavaScript;
- header/footer globais não são reconstruídos;
- loading fica restrito a `.schedule-view-layout` via `aria-busy`/estado local.

Criar treino e editar treino já usam APIs/fetch existentes e foram preservados.

Excluir treino agora usa o mesmo handler PHP de `cronogramatreinos.php`, solicitando JSON somente quando JavaScript está ativo. Após sucesso, o workspace é revalidado e a barra de Desfazer é preservada. Sem JS, o POST/redirect tradicional continua funcionando. A confirmação destrutiva continua obrigatória.

### Trainer

A seleção de atleta troca somente a seção `Como treinador`/workspace, usa `pushState`, mantém `href` real e suporta Back/Forward inclusive até o estado sem atleta selecionado.

### Infra já existente preservada

- Agenda mensal: fetch/cache/History existente;
- Atividades: refresh parcial/master-detail existente;
- Biblioteca: tabs com History existente;
- Progresso: fetch + AbortController + History existente.

Nenhum segundo router frontend foi criado.

## 4. Optimistic interactions

Não foi introduzido optimistic write indiscriminado. Seleção em lote, escolha de filtros/tabs e estados locais continuam respondendo imediatamente. Mutations destrutivas continuam esperando confirmação e resposta do servidor antes de consolidar a UI.

Drag/drop e alterações de ocorrência continuam usando o comportamento já existente de request + atualização/revalidation localizada. A exclusão de treino é server-first para preservar corretamente o snapshot de undo.

## 5. Histórico de Atividades — antes/depois conceitual

Antes, musculação usava o mesmo grid de métricas de corrida para renderizar `Código` e `Foco`. Isso criava duas colunas textuais de primeira classe e tornava a linha mais alta que atividades de endurance.

Depois, musculação ganhou preview horizontal compacto:

- badge pequeno A/B/C quando disponível;
- duração;
- número de exercícios;
- número de séries;
- foco muscular em linha secundária truncável.

Código e foco não são mais grandes colunas de métrica. O painel master-detail continua sendo o lugar dos detalhes completos.

## 6. Métricas de strength

Foram escolhidas somente métricas já confiáveis no modelo:

- duração;
- contagem de exercícios;
- contagem de séries;
- foco textual secundário;
- código A/B/C como badge/meta.

As contagens são obtidas em uma única query agregada para as atividades carregadas. Não foi introduzido N+1 e não foi criado cálculo novo de volume total.

## 7. Batch UX final

A infraestrutura existente de `/api/atividades-lote.php`, delete, undo e refresh foi preservada.

A toolbar contextual agora separa:

1. quantidade selecionada + `Selecionar carregadas`;
2. campos de alteração;
3. `Aplicar`;
4. `Cancelar seleção` e `Apagar` em grupo separado.

`Aplicar` fica desabilitado quando não há itens selecionados ou quando todos os campos permanecem em `Não alterar`.

`Selecionar carregadas` descreve exatamente o escopo frontend e não promete selecionar resultados ainda não carregados.

No mobile os controles quebram em blocos, respeitam safe-area e a ação destrutiva não fica cortada. Linhas selecionadas usam superfície/borda de accent neutro.

## 8. Password reset final

O reset deixou de depender primariamente de link longo e passou a seguir o padrão de código de verificação já existente no produto:

1. usuário informa e-mail;
2. resposta pública permanece genérica;
3. usuário recebe código de 6 dígitos;
4. código válido cria uma autorização de reset limitada;
5. usuário informa nova senha + confirmação;
6. sessões anteriores são revogadas;
7. sucesso oferece `Entrar`;
8. não há auto-login.

Links antigos de reset de 64 caracteres continuam aceitos apenas para compatibilidade com e-mails já emitidos antes da mudança.

O e-mail de código também passou a usar i18n PT-BR/EN.

## 9. Segurança do código de reset

- CSPRNG: `random_int(100000, 999999)`;
- código armazenado por hash SHA-256 na infraestrutura `auth_tokens` existente;
- uso único;
- expiração: 15 minutos;
- código anterior é invalidado quando um novo código é criado;
- cooldown de resend existente: 2 minutos;
- limite do fluxo: 6 falhas/15 min;
- limite por IP: 20 falhas/15 min;
- autorização limitada de reset: 10 minutos;
- código correto não cria sessão autenticada normal;
- após alteração da senha, `sessao_versao` é incrementada e sessões anteriores são revogadas;
- nova senha nunca é enviada por e-mail;
- resposta inicial não enumera existência de conta.

Não foi necessária migration porque `auth_tokens` já comporta o fluxo.

## 10. Exercise resolver / normalization

Novo helper central: `src/function/exercise_resolver.php`.

`stridebr_normalize_exercise_name()`:

- trim;
- Unicode normalization quando Intl está disponível;
- fallback razoável via iconv;
- lowercase;
- remoção de marcas de acento para chave de matching;
- colapso de whitespace;
- normalização de hífens, `_`, `/` e separadores equivalentes;
- remoção conservadora de pontuação irrelevante.

O nome original digitado/importado continua preservado para exibição/snapshot.

A resolução prioriza:

1. ID canônico;
2. nome normalizado exato;
3. slug;
4. alias curado normalizado;
5. fuzzy/similaridade.

Registro/importação de força e cronograma/manual usam o resolver servidor-side. O frontend usa a mesma semântica apenas para feedback imediato.

## 11. Estratégia fuzzy

Fuzzy não foi usado para adivinhar agressivamente. Candidatos abaixo do limiar ficam desconhecidos; candidatos plausíveis aparecem como sugestão. Auto-link por similaridade só é permitido quando o melhor candidato possui confiança muito alta (`>= 0.97`) e vantagem clara sobre o segundo (`>= 0.08`).

O frontend apresenta `Parece com:` e não preenche automaticamente o ID em typo/sugestão comum.

Não foi habilitado `pg_trgm`, não foi criado índice e não houve migration.

## 12. Casos que exigem confirmação

- candidato fuzzy sem confiança/margem suficiente;
- nomes ambíguos como `Supino` diante de `Supino Reto` e `Supino Inclinado`;
- exercício desconhecido permanece válido como snapshot/custom, sem rejeitar o registro.

Teste explícito impede `Supino reto` de ser convertido em `Supino inclinado`.

## 13. Naming inconsistencies

Vocabulário visível consolidado sem churn de rotas:

- Eventos: listagem/descoberta pública;
- Agenda: calendário pessoal;
- Cronogramas: planejamento;
- Biblioteca: treinos/exercícios reutilizáveis;
- Atividades: realizado/registrado;
- Progresso: analytics/evolução;
- Treinador e atletas: coaching/vínculo.

`/calendario.php` continua sendo a rota interna existente, mas a UI permanece `Eventos`.

## 14. Rotas internas preservadas

- `/calendario.php` não foi renomeada;
- `bibliotecaexercicios.php` e `bibliotecatreinos.php` continuam wrappers de compatibilidade para `biblioteca.php`;
- nenhum redirect/churn de URL desnecessário foi introduzido.

## 15. Biblioteca

A auditoria confirmou que o working tree recebido já estava próximo do resultado desejado:

- eyebrow `PLANEJAMENTO` + `Biblioteca`;
- ações contextuais no heading;
- Treinos mostra `+ Novo treino`;
- Exercícios mostra `Nova categoria` + `+ Novo exercício`;
- tabs usam a infraestrutura existente de History API.

Ajuste adicional: em mobile, a ação única da tab Treinos ocupa a largura adequada em vez de permanecer numa grid artificial de duas colunas.

Não houve redesign das linhas de exercícios.

## 16. Treinador e atletas

- removido CTA duplicado `Abrir agenda mensal` do heading;
- subnav continua sendo a navegação entre Cronogramas/Agenda/Treinador;
- papéis `Como atleta` e `Como treinador` ficaram mais claros;
- empty state sem treinador foi encurtado;
- busca/conexão com treinador permanece dentro do contexto de atleta;
- modo treinador OFF ficou compacto;
- ao selecionar atleta, workspace é atualizado parcialmente com History API;
- Back/Forward restaura corretamente inclusive o estado sem atleta selecionado;
- informação de privacidade foi movida para permissões/vínculo, não removida.

## 17. Progresso

Analytics e gráficos existentes foram preservados. A rodada removeu explicações que narravam a própria interface e reduziu moderadamente gaps/alturas.

- toolbar Período/Esporte/Métrica preservada;
- summary strip Atividades/Tempo/Distância/Elevação preservado;
- Consistência mostra dias ativos + heatmap;
- Volume e Tendência mantêm os gráficos;
- Modalidades continua ranking denso, sem pizza/donut/radar;
- Comparação mantém Atual/Anterior/Δ;
- deltas continuam neutros, sem verde/vermelho automático;
- progressive disclosure de tendências por modalidade foi preservado.

## 18. Copy phrases

### REMOVIDA

- `home.active_goals_help` — Home — repetia o comportamento visível das metas.
- `progress.subtitle` — Progresso — subtitle genérico que narrava a página.
- `progress.question_consistency` — Progresso — pergunta decorativa antes de `Consistência`.
- `progress.consistency_help_product` — Progresso — explicava regra interna sem mudar decisão do usuário.
- `progress.question_volume` — Progresso — repetia `Volume`.
- `progress.question_trend` — Progresso — repetia `Tendência`.
- `progress.question_sports` — Progresso — repetia `Modalidades`.
- `progress.modalities_help` — Progresso — justificava uma decisão de design.
- `progress.question_compare` — Progresso — repetia `Comparação entre períodos`.
- `progress.period_comparison_help` — Progresso — explicava que o delta era neutro em vez de a UI simplesmente sê-lo.

Keys realmente sem uso foram removidas em PT-BR e EN.

### MANTIDA / MOVIDA

- `events.subtitle` — Eventos — mantida porque ajuda a explicar escopo/fontes da descoberta pública.
- `friends.subtitle` — Amigos — mantida porque contextualiza uma ação do usuário.
- `agenda.subtitle_self` — Agenda — mantida porque recorrências + datas específicas não são totalmente óbvias.
- `trainer.subtitle` — Treinador — movida para permissões/vínculo porque comunica consequência de privacidade importante.

## 19. Responsividade

Browser fixture final cobriu:

- 1440 px;
- 1024 px;
- 768 px;
- 390 px;
- 375 px;
- 360 px;
- light e dark.

Foram verificados Histórico/batch, Biblioteca Treinos/Exercícios, Trainer OFF/ativo, Progresso, reset por código e navegações parciais de Cronogramas/Trainer.

Não houve body horizontal overflow nos cenários cobertos. A linha de musculação permaneceu com altura próxima à linha de corrida. A ação destrutiva batch ficou visível no mobile e os gráficos de Progresso mantiveram altura legível/compacta.

## 20. Acessibilidade

- links de navegação parcial mantêm `href` real;
- workspace de Cronogramas usa `aria-busy` durante refresh;
- botões/inputs batch preservam elementos nativos;
- checkboxes continuam associados aos itens e estado visual não usa success/danger;
- input de código usa label, `inputmode="numeric"` e `autocomplete="one-time-code"`;
- confirmação destrutiva continua ocorrendo antes de excluir treino;
- Trainer move foco para workspace quando há atleta selecionado;
- Back/Forward continuam funcionais;
- alterações não adicionaram animações obrigatórias incompatíveis com reduced motion.

## 21. Arquivos alterados nesta rodada específica

Comparação byte-a-byte contra o ZIP de entrada, excluindo `.git`, `.env` e caches:

### Modificados

- `public/assets/css/atividades.css`
- `public/assets/css/cronogramas.css`
- `public/assets/css/sport-hub.css`
- `public/assets/js/atividades.js`
- `public/assets/js/cronogramas.js`
- `public/assets/js/trainer.js`
- `public/forgot-password.php`
- `public/home.php`
- `public/reset-password.php`
- `public/user/atividades.php`
- `public/user/cronogramatreinos.php`
- `public/user/progresso.php`
- `public/user/treinador.php`
- `scripts/test_static.sh`
- `scripts/tests/test_desktop_release_finish_static.php`
- `scripts/tests/test_unit.php`
- `scripts/tests/test_visual_consistency_static.php`
- `src/function/atividade_presenter.php`
- `src/function/cronograma.php`
- `src/function/strength_activity.php`
- `src/i18n/en.php`
- `src/i18n/pt-BR.php`
- `src/includes/auth.php`
- `src/layout/trainer_as_athlete.php`

### Novos de implementação/teste

- `src/function/exercise_resolver.php`
- `scripts/tests/test_final_product_polish_static.php`
- `scripts/tests/browser_final_product_polish.py`

Além disso foram geradas 14 screenshots em `docs/reports/screenshots/FINAL_PRODUCT_POLISH_2026-09-09/` e este relatório.

O working tree total permanece maior porque o ZIP de entrada já continha alterações não commitadas de Integrações/SEO; elas foram preservadas.

## 22. Testes finais

### Lint/syntax

- PHP lint: **48 arquivos modificados/untracked — PASS**.
- JavaScript syntax: **4 arquivos — PASS**.
- Python compile: **3 arquivos — PASS**.
- Shell syntax: **1 arquivo — PASS**.
- `git diff --check`: PASS.
- status de migrations: vazio.

### `./scripts/test_static.sh`

PASS no working tree final.

Destaques:

- SEO metadata: 102 assertions;
- deploy configuration: 83;
- unit: 23;
- external integrations RC4: 42;
- AdSense: 46;
- final product polish: **73 assertions**;
- visual consistency: 34;
- schedule hierarchy: 54;
- i18n: **3.242 assertions / 3.164 keys por locale**;
- demais grupos estáticos/unitários: PASS.

A linha `StrideBR activity preference after save: simulated preference failure` faz parte de teste deliberado de falha auxiliar e não representa erro da suíte.

### `./scripts/test_all.sh`

Executado no working tree final. Toda a etapa estática/unitária passou novamente. O script encerrou com exit 2 porque Docker Compose não existe neste ambiente:

`Docker Compose não encontrado. Os checks estáticos/unitários passaram, mas os testes PostgreSQL não foram executados.`

Portanto a suíte PostgreSQL real não é declarada como aprovada nesta execução.

### Browser

`python scripts/tests/browser_final_product_polish.py`

PASS: **29 checks, 14 screenshots** usando `/usr/bin/chromium` headless e CSS/JS reais com fixtures autocontidas.

O teste cobre também:

- batch: seleção, Apply desabilitado sem mudança, habilitação após alteração, selecionar carregadas e cancelar;
- Cronogramas: troca de cronograma sem reload, pushState e mutation de exclusão com confirmação + revalidation + undo;
- Trainer: troca parcial de atleta e Back para estado sem atleta;
- ausência de erros JS nos cenários executados.

## 23. Screenshots

Geradas em `docs/reports/screenshots/FINAL_PRODUCT_POLISH_2026-09-09/`:

1. `01-activities-batch-strength-desktop-dark.png`
2. `02-activities-batch-strength-mobile-light.png`
3. `03-library-workouts-desktop-dark.png`
4. `04-library-workouts-mobile-light.png`
5. `05-library-exercises-tablet-light.png`
6. `06-library-exercises-mobile-dark.png`
7. `07-trainer-mode-off-tablet-dark.png`
8. `08-trainer-active-mobile-light.png`
9. `09-progress-desktop-dark.png`
10. `10-progress-mobile-light.png`
11. `11-reset-code-mobile-dark.png`
12. `12-activities-batch-tablet-dark.png`
13. `13-progress-375-dark.png`
14. `14-library-workouts-360-light.png`

## 24. Blockers restantes

O ambiente desta execução não possui Docker Compose/PostgreSQL descartável disponível para a suíte integrada. Por isso ainda é necessário, antes ou durante a revisão final do pacote, executar em um ambiente com banco:

- reset por código contra `auth_tokens` real, incluindo e-mail existente/inexistente, código usado/expirado e revogação de sessões;
- resolver de exercícios usando catálogo PostgreSQL real;
- batch persistido/undo contra banco;
- mutations de Cronogramas com undo real;
- fluxos completos de Trainer com dados reais.

Os browser tests desta rodada são fixtures autocontidas com CSS/JS reais; não substituem um smoke manual autenticado da aplicação com banco.

## Confirmações finais

- **sem commit**;
- **sem push**;
- **sem tag**;
- **sem deploy**;
- **sem alteração de versão/release**;
- **sem migration criada ou modificada**;
- **nenhuma feature pós-1.0 adicionada**;
- infraestrutura já pronta de PWA, GPS, Share, Ads, integrações e SEO preservada.
