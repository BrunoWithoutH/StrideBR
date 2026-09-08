# Treino em andamento e semana mobile — 08/09/2026

Correções feitas sobre o working tree existente, sem substituir alterações anteriores. Não houve migration/schema, commit, tag, deploy ou mudança de versão.

## Causas e correções

1. **Keys cruas:** os catálogos PT/EN já continham `workout_session.*`, mas `stridebr_js_i18n_dictionary()` filtrava esse prefixo. O runtime recebia dicionário incompleto: `tn()` devolvia as chaves, e `t()` caía em textos ingleses. Exportado o prefixo completo. Mantidas as chamadas reais de pluralização; não há concatenação manual de key. O nome acessível do backdrop também foi localizado.
2. **Pluralização:** cabeçalho, resumo de exercício e “Última vez” usam a mesma infraestrutura `tn()` com singular/plural. Fixture inclui 10 exercícios/37 séries, um exercício de série única e histórico por tempo. PT: exercício/exercícios, série/séries, Marcar tudo; EN: exercise/exercises, set/sets, Mark all.
3. **NaN:** `elapsedText()` executava `new Date(session.data_inicio)` diretamente sobre a representação textual PostgreSQL e calculava minutos/segundos sem testar validade. O formato SQL não é um contrato portátil entre Chromium e Safari.
4. **Timer:** `sessaoCarregar()` mantém `data_inicio` do banco como fonte de verdade e entrega `started_at_ms` numérico e `data_inicio` em ISO 8601 com timezone. JS calcula somente com milissegundos finitos e positivos; ausente/inválido resulta em `00:00`. Não inventa novo início no reload. A formatação do resumo também rejeita valores não finitos. Backend protege contra início vazio.
5. **Overflow:** a grade de séries tinha mínimos rígidos, somados a padding de lista, card e linha; ações tinham padding/largura intrínseca excessivos; cabeçalho recebia tokens internos longos. Corrigidos os componentes, sem adicionar `overflow-x:hidden` global.
6. **Elementos envolvidos:** `.session-set-row`, seus inputs/colunas, `.session-exercise`, `.workout-session-exercises`, header e `.workout-session-actions`. A matriz final verifica tanto document width quanto scrollWidth dos componentes; todos cabem.
7. **Séries mobile:** `28px minmax(0,1fr) minmax(0,.75fr) 44px`, gap de 8px; cabeçalho `# / Carga / Reps / Feita`. Inputs e Done com 44px, fonte dos inputs 16px. Labels visuais repetidas ocultas somente no mobile, preservando o `<label>` acessível. Carga usa `inputmode=decimal` e aceita vírgula/ponto; reps mantém input numérico e validação existente.
8. **Densidade:** padding da lista 8px, card 10px, linha 4px; header mais compacto. Nome longo quebra; metadata permanece abaixo. Touch targets dos campos e Done preservados.
9. **Concluído:** removidos fundos claros fixos dos cards e linhas concluídas em favor de `--ui-success`/`--ui-success-soft`. O tema determina o tom; texto conserva foreground normal. No dark, check usa success também. Light e dark inspecionados.
10. **Barra inferior:** grid de três colunas com maior proporção para Finalizar; botões localizados, padding compacto e textos quebráveis. Continua como footer do painel flex, reservando a própria altura, sem sobrepor a área rolável. Cancelamento e finalização conservam suas regras/confirmações.
11. **Safe area/teclado:** padding inferior usa `env(safe-area-inset-bottom)` existente; adicionado fallback `vh` antes de `dvh`. Quando o teclado reduz a visual viewport, somente o modal se ajusta à altura/offset disponíveis, com scroll mínimo para manter o input acima do footer. Teste simula visual viewport de 460px e verifica input/barra. Não equivale a teclado ou home indicator físicos.
12. **Carga:** cada edição manual cria um limite; propaga só para índices seguintes vazios ou reconhecidos como defaults herdados. Para no próximo override, inclusive vazio explícito; ignora séries concluídas como alvos; não altera reps. Valores persistem no PostgreSQL numa transação, com lock da sessão e das séries. Requisições do cliente são sequenciadas e edição de um campo preserva o outro. Marcar tudo só muda conclusão.
    - Proveniência dos defaults fica na sessão PHP autenticada, por ID da sessão de treino; reload preserva essa informação. Sem schema novo.
    - Se essa sessão autenticada for perdida ou o treino for aberto em outro navegador, os valores do banco continuam intactos e valores preenchidos de origem desconhecida passam a ser limites conservadores. Não são reclassificados silenciosamente como herdados. Não há garantia de continuidade automática dos defaults entre logins/dispositivos.
13. **Semana mobile:** a implementação correta é a semana de `public/user/cronogramatreinos.php`, compartilhando `cronogramas.css`/`ui-refresh.css`; `agenda-mensal.php` e `calendario.php` foram inspecionados e não contêm essa grade horária. Uma altura `!important` de `ui-refresh.css` anulava a adaptação mobile. Agora só se aplica no desktop. Mobile usa altura natural e dias de ao menos 240px, horizontal entre dias, vertical pela página. Sem scrollHeight vertical excedente dentro do componente.
14. **Desktop:** preservado o grid semanal por horários e sua rolagem vertical própria acima de 760px. Ajustes de densidade/colunas de séries limitados aos breakpoints mobile. Matriz de desktop/tablet aprovada.

Também corrigida uma corrida de hidratação: refresh sem histórico não apaga mais o histórico já carregado por outro request.

## Arquivos desta rodada

- `src/includes/i18n.php`
- `src/layout/quick_tools.php`
- `src/function/treino_sessao_api.php`
- `src/function/workout_load.php` (novo)
- `public/assets/js/workout-session.js`
- `public/assets/css/style.css`
- `public/assets/css/ui-refresh.css`
- `public/assets/css/cronogramas.css`
- `scripts/test_static.sh`
- `scripts/tests/test_workout_load.php` (novo)
- `scripts/tests/fixture_workout_mobile.php` (novo, bloqueado fora de CLI/development/PostgreSQL local)
- `scripts/tests/browser_workout_mobile.py` (novo)
- `scripts/tests/browser_week_mobile.py` (novo; usa fixture de planejamento já existente)
- Este relatório, auditoria Dokploy e capturas em `docs/reports/workout-mobile-2026-09-08/`.

## Testes e evidências

- `./scripts/test_all.sh`: passou; 11 grupos PostgreSQL, 157 assertions; seis migrations aplicadas em banco descartável com runner idempotente.
- `./scripts/test_static.sh`: passou na execução final, incluindo novo teste de propagação e exportação i18n. Execução no sandbox bloqueou subprocesso PHP de teste preexistente; repetida fora do sandbox com sucesso.
- `sh scripts/tests/test_migrations_upgrade.sh`: passou em banco descartável; histórico anterior preservado e somente arquivo pendente aplicado.
- `test_workout_load.php`: avanço, fronteiras manuais incluindo vazio, concluídas, valores desconhecidos e defaults desatualizados; dicionários PT/EN.
- `browser_workout_mobile.py`: Chromium e WebKit aprovados em 320, 360, 375, 390, 768, 1024 e 1440px, PT/EN, light/dark; singular, histórico, timer ativo/reload/fallback inválido/ausente, propagação, override, concluídas, decimal com vírgula, Marcar tudo, viewport de teclado e ausência de overflow.
- `browser_week_mobile.py`: Chromium/WebKit aprovados nas sete larguras; mobile sem scroll vertical excedente interno, documento sem overflow horizontal, dias horizontais legíveis e desktop.
- `git diff --check` e sintaxe PHP/JS: passaram.

WebKit foi executado em container descartável `mcr.microsoft.com/playwright/python:v1.62.0-noble`, pois o host não tinha bibliotecas compatíveis. Teste de respostas inválidas bloqueia service workers no contexto de teste para permitir interceptação determinística. Não modifica o service worker do produto.

Capturas inspecionadas:

- [375 dark PT](workout-mobile-2026-09-08/chromium-workout-pt-BR-dark-375.png)
- [390 dark PT](workout-mobile-2026-09-08/chromium-workout-pt-BR-dark-390.png)
- [375 light PT](workout-mobile-2026-09-08/chromium-workout-pt-BR-light-375.png)
- [Desktop](workout-mobile-2026-09-08/chromium-workout-pt-BR-dark-1440.png)
- [Primeira série concluída](workout-mobile-2026-09-08/webkit-workout-375-first-set-completed.png)
- [Exercício concluído dark](workout-mobile-2026-09-08/webkit-workout-375-completed.png)
- [Input focado](workout-mobile-2026-09-08/webkit-workout-375-focused.png)
- [Viewport de teclado simulada](workout-mobile-2026-09-08/webkit-workout-375-keyboard-viewport.png)
- [Semana 375](workout-mobile-2026-09-08/webkit-week-375.png)
- [Semana desktop](workout-mobile-2026-09-08/webkit-week-1440.png)

Limite de validação: WebKit atual automatizado não comprova Safari iOS 15 físico, instalação PWA, teclado nativo ou safe-area real. Esse smoke test físico permanece pendente. Sem migration, commit, tag ou deploy; sem mudanças em anúncios, GPS, Admin ou demais frentes do produto. A auditoria Dokploy é apenas encaminhamento A/B/C; prompt de implementação depende da concordância posterior solicitada.
