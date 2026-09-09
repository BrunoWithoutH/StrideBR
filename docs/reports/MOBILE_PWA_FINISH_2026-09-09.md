# StrideBR Web 1.0 — acabamento mobile/PWA

Data: 2026-09-09
Fonte de verdade: `stridebr(20260909-170217).zip`

## Escopo

Rodada restrita a quatro pontos de acabamento mobile/PWA:

1. eliminar scroll vertical interno do cronograma semanal no mobile;
2. reduzir densidade vertical do calendário mensal e seus cards;
3. corrigir dark mode/densidade do modal `Novo treino`;
4. adicionar ajuda contextual `Adicionar à tela inicial` sem linguagem técnica.

Não houve alteração de banco, migration, versão, integrações, SEO/OG, service worker, fluxo funcional dos cronogramas ou arquitetura geral.

O ZIP recebido já continha alterações não relacionadas em SEO/branding (`public/evento.php`, `scripts/tests/test_seo.php`, `src/includes/app.php`, `src/includes/seo.php`, `docs/branding/` e `public/assets/img/branding/stridebr-og-20260909.png`). Elas foram preservadas e não fazem parte desta rodada.

## 1. Scroll vertical interno — causa e correção

### Causa

O CSS mobile de `cronogramas.css` transformava explicitamente a semana numa viewport interna:

- altura baseada em `dvh`;
- `min-height`/`max-height` artificiais;
- `overflow:auto`;
- `overscroll-behavior:contain`.

Havia ainda uma regra mobile anterior com `height:min(64dvh,590px)` e `min-height:430px`. Mesmo existindo uma sobrescrita posterior, esse contrato era contraditório e deixava o comportamento dependente da cascata.

### Estado final

Em `<=760px` o calendário semanal usa:

- `height:auto`;
- `min-height:0`;
- `max-height:none`;
- `overflow-x:auto`;
- `overflow-y:hidden`;
- contenção apenas no eixo horizontal.

A grade semanal cresce até sua altura real. O `body/document` é responsável pela rolagem vertical. A navegação horizontal das sete colunas permanece rolável.

O comportamento desktop foi preservado.

## 2. Calendário mensal

A altura mínima das células foi reduzida para um calendário de consulta rápida:

- célula específica de Cronogramas: `94px`;
- célula mensal genérica: `104px`;
- gaps/paddings internos menores;
- estado de loading também teve a altura mínima reduzida para não reintroduzir um bloco exagerado.

Os cards recorrentes de Cronogramas agora priorizam somente:

- horário;
- código A/B/C + nome;
- estado.

Foco muscular, autor e totais de exercícios deixaram de ocupar permanentemente o card mensal. Esses dados continuam disponíveis nos contextos detalhados do produto.

Títulos secundários usam truncamento controlado em vez de crescer para muitas linhas.

## 3. Estados especiais

A distinção `Realizado em outra data` foi preservada.

No calendário ela aparece de forma compacta. Informações e ações como:

- data originalmente planejada;
- `Ver atividade`;
- `Ajustar datas`;
- `Abrir treino`;

foram movidas para um menu contextual `•••` usando `<details>/<summary>`, sem remover funcionalidade.

Assim, o estado continua semanticamente visível sem transformar a célula mensal em uma tela de detalhes.

## 4. Agenda x calendário

O calendário permanece orientado a visão geral. Ações e detalhes secundários ficam sob disclosure/contexto.

A Agenda/fluxos detalhados continuam sendo os lugares adequados para leitura sequencial e aprofundamento. Nenhuma regra de recorrência, ocorrência ou associação de treino foi alterada.

## 5. Modal `Novo treino`

### Causa do dark quebrado

O modal ainda possuía várias cores claras hardcoded (`#fbfcfe`, `#344154`, `#f0f3f7` e equivalentes) em header, cards, inputs e campos de agenda.

### Correção

As superfícies, textos, bordas, placeholders e estados passaram a usar os tokens atuais do design system:

- `--ui-panel`;
- `--ui-panel-soft`;
- `--ui-border` / `--ui-border-soft`;
- `--ui-text-strong` / `--ui-text-secondary`;
- `--ui-faint`;
- tokens de accent/danger quando aplicável.

Também há cobertura explícita do tema dark para as superfícies principais do quick-create.

O light mode permanece usando os mesmos tokens e foi validado em browser.

### Densidade mobile

Foram reduzidos somente excessos de espaçamento:

- header e formulário mais compactos;
- cards `Começar com` menores;
- gaps dos campos reduzidos;
- Início/Fim permanecem lado a lado em 360–430px;
- footer respeita `safe-area-inset-bottom`.

Campos necessários não foram removidos.

## 6. `Adicionar à tela inicial`

A infraestrutura foi adicionada em `public/assets/js/pwa.js`, preservando a detecção de standalone e o registro de service worker existentes.

Copy PT-BR:

- `Adicionar à tela inicial`;
- `Acesse o StrideBR direto da tela inicial do celular.`;
- iPhone: `Compartilhar → Adicionar à Tela de Início.`

Também há equivalentes EN pela i18n central.

### Android/Chrome

O aviso só se torna elegível quando `beforeinstallprompt` é realmente recebido. O evento é guardado e o `prompt()` só é chamado após clique explícito em `Adicionar`.

Não existe instalação automática.

### iPhone/iOS

Como não há prompt equivalente, o aviso apresenta somente a instrução curta `Compartilhar → Adicionar à Tela de Início`.

### Quando não aparece

O aviso é suprimido em:

- desktop/larguras não mobile;
- standalone/PWA já aberto como app;
- login/cadastro/recuperação/verificação;
- onboarding;
- gravação ativa de atividade/GPS;
- treino ativo detectado pela infraestrutura global;
- modal importante aberto.

O componente fica no fluxo da página logo após o header; não é modal e não cobre bottom navigation ou timer.

### Dismiss

`Agora não` grava cooldown local de 30 dias. Instalação aceita grava cooldown longo de 180 dias. Falha ao abrir prompt usa cooldown curto de 7 dias.

O armazenamento é defensivo (`try/catch`) para ambientes onde `localStorage` esteja indisponível.

## 7. Bottom navigation, timer e safe-area

Nenhuma alteração foi feita na bottom navigation ou no timer.

A correção semanal faz a página crescer naturalmente, então o conteúdo pode chegar ao final pela rolagem do documento sem ficar preso numa viewport interna.

O modal mantém footer com `env(safe-area-inset-bottom)`. O aviso de tela inicial é inline e deixa de aparecer durante superfícies importantes/treino ativo.

## 8. Arquivos alterados nesta rodada

Código/produto:

- `public/assets/css/cronogramas.css`
- `public/assets/css/ui-refresh.css`
- `public/assets/js/cronogramas.js`
- `public/assets/js/pwa.js`
- `public/user/agenda-mensal.php`
- `src/i18n/pt-BR.php`
- `src/i18n/en.php`

Testes/relatório:

- `scripts/test_static.sh`
- `scripts/tests/test_schedule_mobile_pwa_static.php`
- `scripts/tests/browser_schedule_mobile_pwa.py`
- `docs/reports/MOBILE_PWA_FINISH_2026-09-09.md`
- `docs/reports/screenshots/MOBILE_PWA_FINISH_2026-09-09/*.png`

## 9. Testes executados

### Regressão específica

`php scripts/tests/test_schedule_mobile_pwa_static.php`

- PASS — 27 assertions.

`python scripts/tests/browser_schedule_mobile_pwa.py`

- PASS — 47 checks;
- 7 screenshots.

Cobertura browser do fixture:

- semana: 360, 375, 390 e 430px;
- mês: 390, 768, 1024 e 1280px;
- modal: 390 dark + light;
- Android `beforeinstallprompt`;
- iOS instrução + dismiss;
- standalone sem aviso.

O teste mede explicitamente no mobile semanal:

- bloco maior que a viewport quando o conteúdo exige;
- `scrollHeight` interno sem exceder `clientHeight`;
- overflow horizontal preservado;
- documento com scroll vertical;
- `overflow-y` interno diferente de `auto/scroll`.

### Suíte geral

`./scripts/test_static.sh`

- PASS completo.
- i18n: 3252 assertions / 3174 keys por locale.

`./scripts/test_all.sh`

- etapa estática/unitária: PASS;
- exit final: 2, somente porque Docker Compose não está disponível no ambiente;
- suíte PostgreSQL não executada nesta sessão.

### Checks adicionais

- `node --check public/assets/js/cronogramas.js`: PASS;
- `node --check public/assets/js/pwa.js`: PASS;
- `php -l public/user/agenda-mensal.php`: PASS;
- Python compile do browser fixture: PASS;
- `git diff --check`: PASS;
- nenhuma migration modificada/criada.

O fixture legado `browser_pwa_display_mode.py` não pôde iniciar porque o Chromium gerenciado pelo Playwright não está instalado neste ambiente. Não foi marcado como aprovado. A regressão nova cobre display standalone usando `/usr/bin/chromium` e passou.

## 10. Screenshots

Gerados em `docs/reports/screenshots/MOBILE_PWA_FINISH_2026-09-09/`:

1. `01-week-390-dark.png`
2. `02-month-390-dark.png`
3. `03-month-desktop-light.png`
4. `04-agenda-shifted-390-light.png`
5. `05-new-workout-modal-390-dark.png`
6. `06-new-workout-modal-390-light.png`
7. `07-add-home-android-390-dark.png`

A inspeção visual confirmou:

- semana sem viewport vertical artificial;
- mês significativamente mais denso;
- estado deslocado compacto;
- modal completamente dark no dark mode;
- light mode preservado;
- aviso de tela inicial discreto no fluxo do documento.

## 11. Validação física recomendada antes de publicar

Há dois pontos que ainda valem smoke curto em aparelho real porque simuladores não reproduzem perfeitamente gesto/viewport móvel:

1. iPhone/Safari e standalone: gesto vertical dentro da grade semanal deve mover somente a página; gesto horizontal deve navegar pela semana; validar safe-area/teclado no quick-create.
2. Android/Chrome: confirmar que o `beforeinstallprompt` aparece nas condições reais do navegador e que o prompt nativo abre após tocar em `Adicionar`.

Isso é validação de integração com browser/aparelho, não blocker conhecido do código.

## 12. Confirmações

- sem migration;
- sem alteração de banco;
- sem commit;
- sem push;
- sem tag;
- sem deploy;
- sem alteração de SEO/OG desta rodada;
- sem alteração do service worker;
- sem feature pós-1.0;
- sem redesign global.
