# Eventos esportivos

O calendário público usa somente dados locais do PostgreSQL. `/calendario.php` e `/evento.php` não consultam sites externos durante o request.

A administração continua disponível em `/admin/events.php` para criação, edição, fontes, imagens e importação manual JSON/CSV. A sincronização externa é complementar e não substitui essas superfícies.

## Importação manual

Ao cadastrar um evento a partir de uma página, PDF, regulamento ou calendário, preserve apenas fatos publicados pela fonte:

- título;
- modalidade e tipo;
- data e hora quando informadas;
- cidade, UF, local e endereço;
- organizador;
- distâncias quando realmente forem distâncias;
- provas/programa quando explicitamente publicados;
- categorias;
- prazo de inscrição;
- URL oficial;
- URL de inscrição;
- fontes usadas na conferência.

Não invente preço, percurso, programação, regulamento ou detalhes ausentes.

O painel aceita JSON ou CSV com prévia. Itens válidos entram como `rascunho`. O limite atual permanece em 200 eventos e 1 MB por importação. A detecção manual existente de duplicados continua ativa.

## Imagens

O painel aceita JPG, PNG e WebP de até 6 MB por arquivo e no máximo 8 imagens por evento. A sincronização externa não baixa imagens. Só reutilize material de terceiros quando houver autorização apropriada.

## External Sports Events V1

A migration `20260918_external_sports_events_v1.sql` adiciona:

- `eventos_modalidades`: relação N:N de modalidades por evento;
- `eventos_provas`: programação/provas estruturadas;
- `eventos_fontes_externas`: vínculo idempotente com providers;
- `eventos_provedores_sync`: estado e diagnóstico dos providers;
- `eventos_esportivos.origem`;
- `eventos_esportivos.sincronizacao_bloqueada`;
- `eventos_esportivos.seo_indexavel`;
- `eventos_esportivos.horario_informado`.

O `idmodalidade` de `eventos_esportivos` continua existindo por compatibilidade e é migrado para `eventos_modalidades` como modalidade principal.

### Providers V1

| Provider | Fonte fixa | Estado inicial |
| --- | --- | --- |
| `faergs` | `https://www.faergs.com.br/` | desabilitado / compliance pendente |
| `cbat` | `https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_oficial` | desabilitado / compliance pendente |
| `cbat` | `https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_brasil` | desabilitado / compliance pendente |
| `cbat` | `https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_estadual` | desabilitado / compliance pendente |
| `cbc` | `https://www.cbc.esp.br/modalidades/calendario/busca/estrada` | desabilitado / compliance pendente |
| `cbc` | `https://www.cbc.esp.br/modalidades/calendario/busca/pista` | desabilitado / compliance pendente |
| `cbc` | `https://www.cbc.esp.br/modalidades/calendario/busca/mtb` | desabilitado / compliance pendente |
| `fgc` | `https://www.fgc.com.br/estrada/campeonato-2026/` | desabilitado / compliance pendente |
| `fgc` | `https://www.fgc.com.br/mountain-bike/campeonato-2026/` | desabilitado / compliance pendente |
| `fgc` | `https://www.fgc.com.br/downhill/campeonato-2026/` | desabilitado / compliance pendente |
| `cbtri` | `https://cbtri.org.br/calendario_2026/` | desabilitado / compliance pendente |

Os providers são implementados, mas a migration os cria com `enabled=FALSE`, `auto_publish=FALSE` e `compliance_status='pendente'`. Antes de habilitar em produção, revise manualmente o `robots.txt`, termos de uso e autorização aplicáveis à coleta automatizada da fonte. Se a política não puder ser confirmada, mantenha o provider desabilitado.

Não há provider V1 para CBDA, CBDU, Rio Grande Run, Baixa Pace, SESC RS ou World Athletics.

## Segurança da coleta

Toda rede passa por `src/function/external_events/http.php`.

O cliente:

- aceita somente HTTPS;
- usa allowlist fixa de hosts definida pelo provider;
- não aceita URL arbitrária enviada por usuário;
- rejeita credenciais embutidas na URL;
- não segue redirects automaticamente;
- valida cada redirect e limita a três saltos;
- verifica TLS;
- usa User-Agent `StrideBR External Events/1.0 (+https://stridebr.com.br/)`;
- limita cada resposta a 2 MiB;
- aplica timeout curto e respeita o deadline global;
- envia `If-None-Match` e `If-Modified-Since` quando disponíveis;
- respeita `429`/`Retry-After` sem retry agressivo;
- não usa browser automation;
- não contorna CAPTCHA ou anti-bot;
- não acessa conteúdo autenticado;
- não baixa imagens;
- não arquiva HTML completo no banco.

Mudança inesperada de estrutura faz o parser falhar fechado. Uma falha não apaga dados já válidos e não impede os demais providers do batch.

## Normalização

Todos os providers entregam o mesmo formato conceitual:

- provider e identidade externa;
- URL da fonte;
- título;
- início/fim;
- cidade, UF, país, local e endereço;
- organizador;
- URL oficial e de inscrição;
- prazo de inscrição quando publicado;
- status;
- modalidade principal e modalidades adicionais;
- tipo;
- distâncias reais;
- programa/provas;
- metadados factuais mínimos da fonte.

Valor desconhecido permanece `NULL` ou vazio.

### Provas e atletismo

`distancias` não representa saltos, arremessos, lançamentos ou combinadas. Essas informações ficam em `eventos_provas`.

Grupos normalizados de atletismo:

- `corrida`;
- `barreiras`;
- `obstaculos`;
- `marcha`;
- `revezamento`;
- `salto`;
- `arremesso_lancamento`;
- `combinadas`.

Uma prova só é persistida quando a fonte publica explicitamente o programa/lista correspondente. O nome de um campeonato nunca é usado para inventar provas.

## Deduplicação e proveniência

A identidade de uma fonte segue esta ordem:

1. `provider + external_id` quando existe;
2. URL específica do evento quando diferente do calendário compartilhado;
3. fingerprint estável de título normalizado + data + cidade/UF + modalidade.

Para convergência entre fontes, o Core compara data, cidade/UF, título normalizado e modalidade. Match único e seguro pode reutilizar o mesmo evento. Match ambíguo não é unido silenciosamente.

Um evento pode ter múltiplas linhas em `eventos_fontes_externas` e continua usando `eventos_fontes` para apresentação pública das fontes.

Eventos manuais são fonte de verdade. Se uma fonte externa convergir com um evento manual, a proveniência pode ser anexada, mas a sincronização não sobrescreve os campos manuais.

Eventos externos novos usam:

- `origem=externo`;
- `status=rascunho`, salvo provider explicitamente configurado para autopublicação;
- `seo_indexavel=FALSE`;
- `sincronizacao_bloqueada=FALSE`.

Ao salvar manualmente um evento externo no admin, `sincronizacao_bloqueada` passa a `TRUE`. Se o admin publicá-lo, a página pode se tornar indexável.

## Stale e cancelamento

Desaparecer do calendário externo não significa cancelamento.

O vínculo registra `last_seen_at`, incrementa `sync_misses` e define `stale_since`. A V1 não exclui o evento automaticamente.

Somente um cancelamento explicitamente publicado pela fonte confiável atualiza o evento para `cancelado`.

## Publicação e SEO

A sincronização pode ser configurada por provider com `auto_publish`, mas todos começam com autopublicação desligada.

Eventos externos rasos não entram automaticamente no sitemap. `seo_indexavel=FALSE` produz `noindex` no detalhe e exclui o slug do sitemap mesmo se o evento tiver sido publicado por outro fluxo.

O Core armazena fatos e links, não copia descrições editoriais extensas, imagens, regulamentos completos ou resultados individuais em massa.

## CLI

A migration precisa estar aplicada antes da execução:

```bash
php scripts/sync_external_events.php
```

Provider específico:

```bash
php scripts/sync_external_events.php --provider=faergs
php scripts/sync_external_events.php --provider=cbat
php scripts/sync_external_events.php --provider=cbc
php scripts/sync_external_events.php --provider=fgc
php scripts/sync_external_events.php --provider=cbtri
```

Deadline global, em segundos:

```bash
php scripts/sync_external_events.php --deadline=120
```

O runner usa lock local para impedir duas execuções simultâneas. Falha de um provider é registrada e os demais continuam enquanto houver deadline.

Enquanto `enabled=FALSE` ou `compliance_status` não for `aprovado`, o CLI informa o provider como ignorado e não faz request externo.

Após uma revisão de compliance, a habilitação é uma ação operacional explícita no banco. Não habilite um provider apenas para testar o parser em produção.

## Schedule Dokploy

Depois da revisão de compliance, a recomendação inicial é executar a cada 12 horas, fora de minuto cheio:

```text
17 */12 * * *
```

Com diretório de trabalho na raiz do Core:

```bash
php scripts/sync_external_events.php --deadline=120
```

Não use frequência alta para compensar falhas. `Retry-After` e `next_sync_at` existem para reduzir pressão sobre as fontes.

## Diagnóstico

`/admin/events.php` exibe estado, compliance, último sucesso e último erro de cada provider quando a migration V1 está aplicada.

No banco, consulte `eventos_provedores_sync` para detalhes adicionais de `consecutive_failures`, `next_sync_at` e `stats`.

Erros de parser indicam mudança de estrutura e devem ser tratados atualizando fixture + parser juntos. Não transforme uma mudança de HTML em importação permissiva.

## Adicionando provider

1. identifique uma página/feed público de descoberta estável;
2. revise robots.txt, termos e autorização;
3. defina hosts e URLs iniciais fixos;
4. implemente `ExternalEventsProvider` com `parse` e, se necessário, `discover`;
5. normalize apenas dados publicados;
6. crie fixtures mínimas, sem arquivar a página inteira;
7. teste HTML quebrado/fail-closed;
8. adicione o provider ao registry;
9. crie estado de sync desabilitado por padrão;
10. valide manualmente antes de aprovar compliance ou autopublicação.

## Fase 2

Ficam fora desta V1:

- CBDA;
- CBDU e eventos universitários multi-esporte reais;
- Rio Grande Run;
- Baixa Pace;
- SESC RS;
- World Athletics;
- ingestão de PDF/regulamento;
- parser genérico de qualquer URL;
- imagens externas;
- resultados individuais;
- política automática de arquivamento de eventos stale;
- modelagem específica de evento adiado além dos metadados da fonte.
