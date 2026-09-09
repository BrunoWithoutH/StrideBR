# StrideBR Web 1.0 — Biblioteca + Batch toolbar

Data: 2026-09-09

Escopo estrito: duas regressões visuais remanescentes da rodada final de Product/UX — ações do heading da Biblioteca e sobreposição da toolbar de seleção em lote de Atividades.

## Resultado

Os dois problemas foram reproduzidos pela estrutura/CSS existente, corrigidos na origem e validados com CSS/JS reais do projeto. Não houve alteração de banco, migration, versão, feature, autenticação, presenter de atividades, Progress, Trainer, Cronogramas, PWA, Ads ou deploy.

## 1. Biblioteca — causa do `+ Novo treino` centralizado

O PHP já marcava o grupo inativo com o atributo `hidden` e `library.js` já alternava corretamente `group.hidden` ao trocar de tab.

O problema estava no CSS autoral:

```css
.library-heading-actions { display:flex; }
```

Essa declaração tinha prioridade sobre o `display:none` do stylesheet de user-agent associado ao atributo HTML `hidden`. Na prática, os dois grupos permaneciam renderizados visualmente.

Além disso, os dois grupos eram filhos diretos de `.library-page-heading`. Quando ambos apareciam, o flex do heading distribuía três filhos:

1. título;
2. ações de Treinos;
3. ações de Exercícios.

Por isso `+ Novo treino` parecia uma coluna solta no meio da página.

## 2. Biblioteca — por que as duas tabs apareciam juntas

Não era erro da History API nem do JS de tabs. `library.js` já fazia:

```js
group.hidden = group.dataset.libraryHeadingActions !== tab
```

A regressão era exclusivamente o contrato CSS de `[hidden]` quebrado pela regra `display:flex`.

Foi adicionada uma regra explícita:

```css
.library-heading-actions[hidden]{display:none!important}
```

Isso torna o contrato de visibilidade independente do stylesheet padrão do navegador.

## 3. Biblioteca — estrutura final do heading

O heading agora possui apenas dois blocos estruturais de primeira classe:

- título/eyebrow;
- um único slot `.library-page-heading-actions`.

Dentro desse slot ficam os dois grupos contextuais, dos quais exatamente um pode estar visível.

### Treinos

- `+ Novo treino` à direita no desktop;
- `Nova categoria` e `+ Novo exercício` não ocupam layout.

### Exercícios

- `Nova categoria` + `+ Novo exercício` à direita;
- `+ Novo treino` não ocupa layout.

A troca Treinos ↔ Exercícios continua usando o `library.js` existente, `history.pushState()` e o mesmo documento, sem reload.

No mobile, o slot passa para a largura inteira abaixo do título. Treinos usa uma ação; Exercícios usa duas ações e pode empilhá-las nos menores breakpoints existentes.

## 4. Batch toolbar — causa da sobreposição

A toolbar usava um grid principal rígido com três colunas:

```text
seleção | campos + Aplicar | Cancelar/Apagar
```

Os breakpoints eram baseados na largura do viewport. Isso não refletia a largura real da toolbar.

Em desktop, Atividades usa `.activity-history-workspace` com duas colunas: Histórico à esquerda e painel de detalhes à direita. Assim, um viewport de 1280 px podia manter o breakpoint "desktop" enquanto a toolbar tinha uma largura real muito menor.

Dentro da coluna central ainda existia outro grid rígido com três selects + `Aplicar`. A combinação podia consumir mais largura que a célula e colidir visualmente com `Cancelar seleção`.

Não era problema de `z-index`.

## 5. Batch toolbar — CSS/layout corrigido

A toolbar principal passou de grid rígido para flex com wrapping real:

- `.activity-bulk-bar`: `display:flex` + `flex-wrap:wrap`;
- grupo de seleção: largura compacta e flexível;
- `.activity-bulk-fields`: flexível, com `min-width:0`, `flex-wrap` e basis adequada;
- campos individuais podem encolher/quebrar sem sair do container;
- `Aplicar` continua dentro do grupo de alterações;
- `.activity-bulk-actions` pode quebrar para a linha seguinte naturalmente quando o container não comporta tudo.

Em até 1050 px, o grupo de alterações e o grupo de ações passam deliberadamente a ocupar linhas próprias. Em até 560 px, a toolbar vira composição vertical e `Aplicar` ocupa a largura disponível; `Cancelar seleção` e `Apagar` ficam em duas colunas minmax(0,1fr), sem clipping.

Nenhuma correção usa `position:absolute`, `z-index`, margem negativa, ocultação visual ou largura fixa arbitrária.

## 6. Comportamento validado por largura

- 1440 px: ações podem permanecer compactas quando há espaço; sem colisão.
- 1280 px: validado com o workspace real em duas colunas; wrapping ocorre antes de colisão.
- 1024 px: campos ficam numa linha própria e ações abaixo; sem overlap.
- 900 px: workspace deixa de dividir o histórico; toolbar continua sem overlap.
- 768 px: campos e ações quebram em grupos claros; sem overflow.
- 390 px: composição vertical, `Aplicar` em largura cheia e Cancelar/Apagar legíveis.
- 375 px: sem overlap e sem body horizontal overflow.
- 360 px: sem overlap e sem body horizontal overflow.

## 7. Testes

### Suíte estática completa

`./scripts/test_static.sh`: PASS.

Inclui:

- PHP syntax;
- JS syntax;
- shell syntax;
- design system/dark contrast;
- Activity/Library regressions existentes;
- `final product polish static`: 75 assertions;
- i18n coverage: 3242 assertions / 3164 keys por locale.

### Browser regressions desta rodada

Novo teste:

```text
scripts/tests/browser_library_batch_regressions.py
```

Resultado:

```text
PASS library/batch regressions: 41 checks, 9 screenshots
```

Cobertura:

### Biblioteca

- Treinos ativa → `+ Novo treino` visível;
- Treinos ativa → ações de Exercícios ocultas;
- Exercícios ativa → `+ Novo treino` oculto;
- Exercícios ativa → ações de Exercícios visíveis;
- exatamente um action group visível;
- troca de tab preserva o mesmo documento;
- `history.pushState()` é acionado;
- mobile 390/375/360 sem overflow.

### Batch

- zero itens → Apply e Delete disabled;
- quatro itens via `Selecionar carregadas`;
- Apply continua disabled enquanto nenhum campo muda;
- Delete habilita com seleção;
- Apply habilita após mudança real;
- Cancelar limpa checkboxes e sai do modo seleção;
- Apply, Cancelar e Apagar não se sobrepõem;
- ações permanecem dentro do bounding box da toolbar;
- toolbar não possui scroll horizontal interno;
- body não ganha overflow horizontal mobile;
- validado em 1440, 1280, 1024, 900, 768, 390, 375 e 360 px.

O browser fixture geral anterior também foi reexecutado depois da correção:

```text
PASS final product polish browser: 29 checks, 14 screenshots
```

## 8. Screenshots

Pasta:

```text
docs/reports/screenshots/FINAL_LIBRARY_BATCH_FIXES_2026-09-09/
```

Arquivos:

- `A-library-workouts-desktop.png`
- `B-library-exercises-desktop.png`
- `C-library-workouts-mobile.png`
- `D-library-exercises-mobile.png`
- `E-activities-selection-zero.png`
- `F-activities-selection-four.png`
- `G-batch-1024.png`
- `H-batch-768.png`
- `I-batch-390.png`

Eles correspondem diretamente aos nove cenários visuais pedidos.

## 9. Arquivos de código/teste alterados nesta etapa

- `public/user/biblioteca.php`
- `public/assets/css/cronogramas.css`
- `public/assets/css/atividades.css`
- `scripts/tests/test_final_product_polish_static.php`
- `scripts/tests/browser_library_batch_regressions.py` (novo)
- este relatório e os nove screenshots dedicados.

## Confirmações

- sem migration;
- sem commit;
- sem tag;
- sem deploy;
- sem alteração de versão;
- nenhuma feature nova.
