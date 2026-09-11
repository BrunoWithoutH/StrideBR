# StrideBR Core — Navegação, Strava, backfill e seleção de Atividades

Data: 2026-09-10  
Fonte de verdade: `stridebr(20260910-234925).zip`  
HEAD recebido: `2dcd7ce — Fix integration metadata object handling`

## 1. Estado Git encontrado

O ZIP já estava além do snapshot antigo `549424d`. O HEAD local e o GitHub estavam em `2dcd7ce`. O working tree recebido continha 12 alterações locais anteriores, principalmente em Sport Hub, Progresso, Header/Settings e i18n. Essas alterações foram preservadas; não houve reset, checkout destrutivo, commit, push, tag ou deploy.

## 2. Arquivos desta rodada

Arquivos alterados/adicionados em relação ao ZIP recebido:

- `public/assets/css/atividades.css`
- `public/assets/css/ui-refresh.css`
- `public/assets/js/atividades.js`
- `public/assets/js/integrations.js`
- `public/assets/js/scripts.js`
- `public/auth/integration-callback.php`
- `public/auth/integration.php`
- `public/function/integration-action.php`
- `public/user/atividades.php`
- `public/user/perfil.php`
- `scripts/test_static.sh`
- `scripts/tests/browser_core_nav_strava.py`
- `scripts/tests/test_core_nav_strava_backfill_static.php`
- `scripts/tests/test_final_product_polish_static.php`
- `scripts/tests/test_gps_web_static.php`
- `scripts/tests/test_integration_sync.php`
- `scripts/tests/test_share_compact_ux_static.php`
- `scripts/tests/test_strava_webhooks_integration.php`
- `scripts/tests/test_ux_consistency_static.php`
- `src/function/integration_sync_support.php`
- `src/function/integrations.php`
- `src/i18n/en.php`
- `src/i18n/pt-BR.php`
- `src/layout/footer.php`
- `src/layout/header.php`
- `src/layout/integration_cards.php`
- screenshots em `docs/reports/screenshots/CORE_NAV_STRAVA_2026-09-10/`

Nenhuma migration foi criada ou alterada nesta rodada.

## 3. Navegação desktop

O desktop usa o avatar como único menu de conta. O dropdown foi organizado em identidade, Conta, Pessoas, Recursos, Admin condicional e Sair. `Conexões e integrações` aponta diretamente para `/user/settings.php?view=connections` e inclui copy secundária. Progresso não foi duplicado no menu.

Logout continua via POST + CSRF. A ação global de criar e notificações existentes foram preservadas.

## 4. Navegação mobile/PWA

A bottom navigation passou a ser:

`Início | Treinos | Atividades | Progresso | Perfil`

A aba Perfil usa o avatar real quando disponível e abre o perfil do próprio usuário. O antigo `Mais`/sheet foi removido do DOM.

No header mobile ficaram notificações + hamburger global. O hamburger é um menu secundário, não menu de perfil. Possui hit area de 42px, drawer acima do conteúdo, backdrop clicável, fechamento por Escape/outside click e safe-area. O browser fixture validou toque, teclado, backdrop e coexistência com notificações.

## 5. Bug do menu PWA

A causa prática era que o controle superior compartilhava um contrato antigo de menu sem um comportamento mobile próprio suficientemente robusto e concorria com outros detalhes/popovers do header. A correção mantém a infraestrutura de `<details>`/JS existente, mas cria contrato explícito para mobile e fechamento coordenado entre menus.

O fixture Chromium confirmou que toque abre, backdrop fecha, Enter abre e Escape fecha. Ao abrir o menu global com notificações abertas, o outro menu é fechado.

## 6. Perfil / Settings / Conexões

O perfil próprio ganhou atalhos compactos para Editar perfil, Configurações e Conexões. Eles não aparecem em perfil alheio.

Settings preserva o segmented nav:

`Perfil | Preferências | Conexões | Conta e segurança`

Conexões é acessível diretamente; retornos de OAuth/sync foram alinhados para `/user/settings.php?view=connections#conexoes`.

Não foi encontrado no repositório um asset oficial `Connect with Strava`. Por isso não foi fabricado logo nem marca: o CTA é textual `Conectar com Strava` e o provider usa wordmark textual simples.

## 7. Estados visuais do Strava

Conexão e sincronização foram separadas na UI. Estados suportados:

- Não conectado
- Importando atividades recentes
- Importando histórico
- Importação pausada temporariamente
- Erro recuperável / tentativa posterior
- Reautorização necessária
- Histórico sincronizado
- Nenhuma atividade encontrada
- Sync de atividades desativado

Quando existem dados confiáveis, o card mostra contagem importada, data mais antiga alcançada e última sincronização. Não existe percentual ou barra de progresso fabricada.

## 8. Arquitetura do backfill

O backfill não cria fila/tabela nova e não substitui reconciliation/webhook. O estado persiste em `integracoes_usuario.metadados.backfill`.

Responsabilidades continuam separadas:

1. webhook — novidades atuais;
2. reconciliation recente — recuperação de novidades perdidas;
3. backfill — caminhada incremental ao passado.

Cada passo de backfill lista no máximo 10 atividades e busca no máximo 3 detalhes novos por execução. O passo possui deadline curto e é retomável.

## 9. Checkpoint

O checkpoint é temporal (`before`), não número de página. Quando há atividade Strava local pré-existente, a mais antiga serve como âncora inicial. Sem histórico local, começa próximo ao presente.

O cursor só avança depois que o lote foi tratado de forma segura. Se a execução parar pelo budget/deadline ou erro recuperável, o mesmo intervalo é repetido; IDs já persistidos são deduplicados e o restante continua. Isso evita perder posição quando a página contém mais atividades novas que o orçamento do passo.

O writer de backfill também foi alinhado à correção de metadata do HEAD `2dcd7ce`: uma raiz JSONB legacy-array é preservada em `_legacy_array` em vez de ser descartada ao criar `backfill`.

## 10. Contas antigas

Uma conexão Strava já existente, ativa, com sync habilitado e sem `metadados.backfill` é automaticamente elegível. Não exige desconectar/reconectar. O estado inicial é criado idempotentemente.

Se o usuário desativar sync de atividades, o backfill para e o checkpoint permanece. Ao reativar, continua do ponto salvo.

## 11. Fairness / rate limit

O runner usa a infraestrutura atual de locks, retry/backoff e cooldown global do provider. Para Strava, reconciliation recente é espaçada em 6 horas porque webhook é o caminho principal; backfill pode ficar elegível entre essas janelas.

Conexões Strava com backfill pendente são ordenadas pelo `last_attempt_at`, dando oportunidade a quem tentou há mais tempo. Uma conta não consome todo o histórico em uma única rodada.

429 pausa o backfill, persiste `retry_at`, mantém o cursor e aciona o cooldown compartilhado do Strava.

## 12. `Sincronizar agora`

Manual sync não reinicia histórico. Ele:

1. tenta novidades/recentes primeiro;
2. respeita lock, sync OFF, reautorização, retry e cooldown;
3. não executa import concorrente;
4. executa no máximo um pequeno passo de backfill quando elegível;
5. preserva checkpoint existente;
6. retorna em tempo limitado.

Se o histórico já estiver concluído, o manual sync não altera seu cursor/estado.

## 13. Webhook boolean

O bug PostgreSQL `SQLSTATE 22P02` foi corrigido na origem. `signature_verified` continua BOOLEAN e o enqueue envia explicitamente `true`/`false` para `CAST(:signed AS boolean)`.

A regressão PostgreSQL cobre false e true. O teste também foi tornado determinístico ao selecionar o evento signed pelo fingerprint quando dois eventos do mesmo owner existem.

`STRAVA_WEBHOOK_SIGNING_SECRET` vazio continua válido. Nenhum segredo foi inventado ou reutilizado. A subscription existente não foi recriada nem alterada.

## 14. Seleção em Atividades

O modo seleção não cria mais uma segunda faixa persistente abaixo de Histórico. O mesmo header troca de conteúdo:

- normal: título, busca, filtro, Selecionar;
- seleção: contador, Selecionar carregadas, Editar, Excluir, Cancelar.

No browser mobile, entrar em seleção não aumenta a posição vertical da lista; no fixture o header contextual ficou inclusive menor que o normal.

Editar abre `<dialog>` no desktop e bottom-sheet no mobile, reutilizando `/api/atividades-lote.php`. O Apply continua desabilitado sem alteração real. Excluir preserva confirmação/undo. Cancelar limpa seleção e restaura o header normal.

## 15. Refresh discreto de Atividades

Ao retornar à aba/janela após pelo menos 60 segundos, a página reconsulta somente o histórico local do próprio StrideBR em background. Não chama Strava nem o endpoint de sync.

O refresh é ignorado enquanto a seleção batch ou editor/modal de atividade estão ativos. Preserva filtros, detalhe aberto e scroll. Se IDs novos aparecerem na lista, mostra feedback discreto de novas atividades importadas.

## 16. Migrations

Nenhuma migration nova ou alteração de migration foi necessária. O backfill usa JSONB existente em `integracoes_usuario.metadados`.

## 17. Testes executados

### Estáticos/unitários

`./scripts/test_static.sh` — PASS completo.

Destaques:

- Core navigation + Strava backfill: **34 assertions**
- Strava webhooks: **13 assertions**
- integrações externas: **42 assertions**
- final product polish: **83 assertions**
- i18n: **3301 assertions / 3223 keys por locale**
- PHP syntax: PASS
- JS syntax: PASS
- shell syntax: PASS
- `git diff --check`: PASS

`./scripts/test_all.sh` foi executado no working tree final. Toda a etapa estática/unitária passou novamente; o script encerrou com exit 2 porque Docker Compose não existe neste ambiente. Portanto as regressões PostgreSQL adicionadas a `test_integration_sync.php` e `test_strava_webhooks_integration.php` não puderam ser executadas aqui.

### Browser

`scripts/tests/browser_core_nav_strava.py` — **34 checks / 4 screenshots PASS** em Chromium autocontido via `set_content`, cobrindo:

- hamburger mobile por toque;
- abertura por teclado;
- Escape;
- backdrop;
- coexistência com notificações;
- ausência do antigo Mais;
- Perfil na bottom nav;
- menu de conta desktop;
- Conexões em Settings;
- card Strava importando histórico sem percentual falso;
- seleção contextual de Atividades;
- selecionar carregadas;
- dialog batch e disabled/enabled do Apply;
- dark + light.

O fixture HTTP antigo `browser_integration_cards.py` foi tentado, mas o Chromium deste ambiente bloqueia `localhost` por policy (`ERR_BLOCKED_BY_ADMINISTRATOR`). Por isso foi usado o fixture autocontido com os CSS/JS reais.

WebKit foi tentado e não possui executável Playwright instalado. Safari/WebKit não está aprovado por esta sessão.

## 18. Limitações visuais/ambiente

- sem Docker Compose / PostgreSQL disponível nesta sessão;
- sem WebKit Playwright;
- localhost bloqueado no Chromium por policy do ambiente;
- browser final usa fixture autocontido para interações e layout, não sessão autenticada real.

Screenshots: `docs/reports/screenshots/CORE_NAV_STRAVA_2026-09-10/`.

## 19. Teste real obrigatório após deploy autorizado

1. criar uma atividade nova no Strava;
2. não clicar em Sincronizar;
3. aguardar worker;
4. confirmar enqueue do webhook;
5. confirmar processamento;
6. confirmar atividade no StrideBR;
7. confirmar ausência de duplicata;
8. conferir o card de Conexões;
9. testar uma conta Strava antiga iniciando/retomando backfill sem reconectar;
10. deixar o runner trabalhar em mais de uma conta e confirmar avanço alternado do histórico.

## Escopo e segurança

- nenhuma alteração em StrideBR Teams;
- nenhuma alteração de secret;
- nenhuma nova subscription Strava;
- nenhum commit/push/tag/deploy;
- nenhuma migration criada;
- working tree pré-existente preservado.
