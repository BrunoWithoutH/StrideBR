# StrideBR Web 1.0.0-rc.4 — Integrações externas + AdSense

Data da revisão: 2026-09-08  
Base: ZIP mais recente fornecido pelo usuário, preservando também a rodada de hierarquia de Cronogramas/Agenda já aplicada ao working tree.  
Escopo: integrações externas, preparação de configuração por environment, ativação dos placements públicos do AdSense, testes e documentação.  
Não executado: commit, push, tag ou deploy.

## 1. Resultado executivo

A infraestrutura genérica existente de `integracoes_usuario` foi preservada e ampliada, sem criar um segundo sistema de OAuth ou tabela paralela. Tokens OAuth continuam protegidos por AES-256-GCM através de `STRIDEBR_INTEGRATIONS_SECRET`. OAuth continua usando sessão/state, expiração do state, callback central e redirects internos validados.

Estado final dos providers:

| Provider | Estado no código | Observação |
| --- | --- | --- |
| Strava | implementado | OAuth, refresh sob demanda, importação detalhada, deduplicação, rate-limit e revogação no disconnect |
| Polar | implementado em AccessLink API v4 | endpoints/scopes atuais, refresh sob demanda, sessões, rota/estatísticas/laps quando disponíveis |
| Google Health | implementado | provider `google_health`; substitui Fitbit Web legado; OAuth offline; exercícios/métricas e TCX quando disponível |
| COROS | implementado via MCP | OAuth discovery + PKCE, MCP atual com fallback legado, FIT limitado e detalhe como fallback; sincronização manual/oportunista |
| Suunto | preparado/implementado | somente fica disponível com Client ID, Client Secret e `SUUNTO_SUBSCRIPTION_KEY`; depende de aprovação/configuração externa |
| Garmin | indisponível | `implementation_ready=false`; nenhum endpoint especulativo |
| HALO | aguardando disponibilidade | sem OAuth/API inventada; referência futura a Health Connect/Apple Health |
| Health Connect | mobile futuro | preservado; sem implementação Web |
| Apple Health | mobile futuro | preservado; sem implementação Web |
| Samsung Health | bridge mobile futuro | preservado; sem implementação Web |
| Fitbit Web | aposentado como provider ativo | removido do registry/UI; valor permanece aceito no CHECK apenas para preservar registros históricos |
| WHOOP | fora de escopo | não incluído |

## 2. Migration mínima e motivo

Foi necessária uma única migration:

`src/database/migrations/20260908_integrations_providers.sql`

A tabela `integracoes_usuario` já era genérica e não precisou de coluna ou tabela nova. Porém o constraint `ck_integracoes_usuario_provedor` aceitava somente os providers antigos e rejeitaria `google_health` e `coros` no PostgreSQL.

A migration apenas recria esse CHECK incluindo os dois novos IDs. `fitbit` permanece no constraint para que conexões históricas existentes não se tornem inválidas, mas não existe mais como provider conectável no registry.

Nenhuma regra de atividade, autenticação, cronograma, GPS, rota, compartilhamento ou associação atividade ↔ treino foi alterada por essa migration.

## 3. Polar AccessLink API v4

Alterações:

- authorize default: `https://auth.polar.com/oauth/authorize`;
- token default: `https://auth.polar.com/oauth/token`;
- base de dados: `https://www.polaraccesslink.com/v4/data`;
- scopes: `training_sessions:read activity:read profile:read`;
- removido o fluxo legado de registro `/v3/users`;
- removidos defaults antigos `flow.polar.com`, `polarremote.com` e `accesslink.read_all`;
- access/refresh tokens ficam criptografados;
- refresh é individual e somente quando a conexão está próxima de expirar;
- scopes concedidos são persistidos;
- listagem usa janela de datas compatível com `to` exclusivo;
- enriquecimento por `features=routes`, `statistics` e `laps` quando retornado pela API;
- rota, FC, cadência, potência, elevação, dispositivo e laps são preservados quando presentes;
- ausência de detalhes não impede a importação do resumo;
- erro/revogação conduz a estado de erro genérico e possibilidade de reautorizar, sem expor resposta privada da API.

Callback:

`https://stridebr.com.br/auth/integration-callback.php?provider=polar`

## 4. Google Health

O provider ativo é `google_health`. A Fitbit Web API antiga não é mais registrada como integração configurável independente.

OAuth:

- authorize: `https://accounts.google.com/o/oauth2/v2/auth`;
- token: `https://oauth2.googleapis.com/token`;
- `response_type=code`;
- `access_type=offline`;
- `include_granted_scopes=true`;
- `prompt=consent` somente em reautorização, ausência de refresh token ou alteração/incompletude de scopes;
- nenhum scope de escrita;
- nenhum scope de sleep nesta rodada.

Scopes atuais:

- `googlehealth.activity_and_fitness.readonly`;
- `googlehealth.location.readonly`;
- `googlehealth.health_metrics_and_measurements.readonly`.

Importação:

- exercícios pela Google Health API;
- distância, duração ativa, elevação, FC, calorias e splits quando disponíveis;
- tentativa de `exportExerciseTcx?alt=media` para reaproveitar GPS/detalhes no parser TCX existente;
- falha/ausência do TCX não elimina a atividade de resumo;
- origem persistida como `google_health`;
- deduplicação por ID externo.

Testing Mode do Google continua compatível com refresh tokens de vida curta: a aplicação não assume refresh token permanente e renova somente a conexão individual quando necessário. Escala pública ainda pode exigir verificação OAuth e security review no projeto Google.

Callback:

`https://stridebr.com.br/auth/integration-callback.php?provider=google_health`

Google Sign-In permanece separado em `/auth/google-callback.php`.

## 5. COROS MCP

Configuração única:

`COROS_MCP_URL=https://mcp.coros.com/mcp`

Não existem `COROS_CLIENT_ID` nem `COROS_CLIENT_SECRET` no ambiente esperado.

Fluxo implementado:

- Protected Resource Metadata RFC 9728 com descoberta path-aware e fallback na raiz;
- Authorization Server Metadata/OpenID discovery com validação exata de `issuer`;
- PKCE S256;
- Client ID Metadata Document em `/auth/mcp-client-metadata.php` quando suportado;
- Dynamic Client Registration como fallback se anunciado pelo authorization server;
- eventual secret retornado por DCR é armazenado criptografado, não em código/HTML;
- token endpoint/resource descobertos ficam em metadados criptograficamente protegidos da conexão;
- refresh individual quando aplicável.

Transporte MCP:

- negocia MCP `2026-07-28` com `server/discover`, headers `MCP-Protocol-Version`, `Mcp-Method` e `_meta` atual;
- aceita resposta JSON ou SSE;
- se o servidor responder como legado/`Method not found`, usa fallback controlado para `2025-11-25` com initialize/session;
- nenhuma sessão legada é criada quando o endpoint aceita o protocolo atual.

Sincronização:

- `querySportRecords`;
- download FIT quando disponibilizado;
- máximo local de 50 tentativas/downloads FIT por execução;
- `getActivityDetail` como fallback quando não há FIT;
- parser FIT existente é reutilizado para FC, distância, elevação, cadência, potência, rota e demais dados disponíveis;
- sincronização COROS não entra no runner periódico; permanece manual/oportunista para evitar polling agressivo;
- nenhuma webhook/Partner API foi inventada.

Callback:

`https://stridebr.com.br/auth/integration-callback.php?provider=coros`

Metadata pública do client:

`https://stridebr.com.br/auth/mcp-client-metadata.php`

## 6. Strava

O fluxo existente foi preservado e completado sem usar os tokens pessoais do dashboard como credenciais globais.

- Client ID/Secret exclusivamente pelo environment;
- scopes `read,activity:read_all`;
- access/refresh token criptografados;
- refresh individual sob demanda;
- lista atividades e tenta detalhe individual enquanto existe orçamento seguro de requests;
- interpreta `X-RateLimit-*` e `X-ReadRateLimit-*` e interrompe enriquecimento quando próximo do limite;
- mantém importação do item resumido se o detalhe não puder ser obtido;
- rota/polyline, FC, cadência, potência, elevação e dados do dispositivo são reaproveitados quando disponíveis;
- deduplicação por `strava + external_activity_id`;
- disconnect tenta `oauth/revoke` e sempre remove as credenciais locais;
- nenhum polling frequente ou webhook incompleto foi criado.

Callback:

`https://stridebr.com.br/auth/integration-callback.php?provider=strava`

A capacidade inicial de atletas/review do aplicativo Strava permanece uma restrição externa; não existe workaround no código.

## 7. Suunto, Garmin e HALO

### Suunto

Configurado somente se todos os requisitos existirem, inclusive `SUUNTO_SUBSCRIPTION_KEY`. Sem a chave, a UI permanece indisponível e não inicia OAuth.

Callback:

`https://stridebr.com.br/auth/integration-callback.php?provider=suunto`

### Garmin

Mantido com `implementation_ready=false`. Nenhum endpoint especulativo ou botão funcional falso foi adicionado. A documentação mantém Activity API, Training API e Courses API como interesse futuro.

### HALO

Exibido como aguardando disponibilidade/parceria. Não existe REST/OAuth/Client Secret inventado. Health Connect/Apple Health aparecem somente como caminhos móveis futuros.

## 8. Sincronização, deduplicação e erros

O pipeline usa a fonte de verdade já existente:

- provider/origem;
- ID externo;
- índice/consulta de deduplicação por usuário + provider + ID externo;
- parser GPX/TCX/FIT já existente quando aplicável.

Resultado detalhado de sync:

`created / existing / failed`

A interface traduz isso em resultado equivalente a “novas / já existentes / falharam”. Falha em uma atividade é contabilizada e não desfaz as demais.

Refresh de token não é executado em lote. `scripts/sync_integrations.php` seleciona conexões, mas cada conexão só é renovada se o token daquela conta estiver expirando.

Mensagens externas brutas não são exibidas ao usuário e deixaram de ser despejadas pelo runner CLI. Estado persistido usa mensagem genérica de reautorização.

## 9. Interface

Foram revisados onboarding e configurações existentes. A UI usa o registry central e distingue:

- conectado;
- não conectado/disponível;
- erro/reautorizar;
- indisponível;
- requer aplicativo mobile;
- aguardando disponibilidade.

Providers conectáveis recebem ações coerentes de conectar, sincronizar agora, reautorizar e desconectar conforme o estado. Providers futuros/bloqueados não recebem CTA OAuth falso. Última sincronização e erro recente continuam aproveitando os campos já existentes no modelo.

Fitbit Web não aparece mais como card/provider independente. Google Health é descrito como integração para Fitbit e dispositivos compatíveis.

Nenhum token, refresh token ou client secret é enviado para HTML/JS.

## 10. AdSense

A arquitetura de monetização foi preservada.

Estado esperado em produção nesta rodada:

- `STRIDEBR_ADS_ENABLED=1`;
- `STRIDEBR_ADS_AUTHENTICATED_ENABLED=0`;
- `STRIDEBR_ADS_PLACEHOLDERS=0`;
- `STRIDEBR_ADS_DEV_PREVIEW=0`.

Consequência:

- placements públicos podem renderizar quando seus slots estão presentes no environment;
- Home, Biblioteca, Perfil e Equipamentos continuam sem anúncios reais;
- IDs de slot não foram hardcoded no repositório;
- `adsbygoogle.js` continua sendo preparado somente quando um placement real é efetivamente renderizado;
- inicialização continua única;
- no-fill continua recolhendo o placement;
- Auto Ads não foi adicionado;
- nenhuma segunda CMP local foi criada.

A metatag permanece centralizada e testada como uma ocorrência por documento:

`<meta name="google-adsense-account" content="ca-pub-3948145279411749">`

### ads.txt

Criado `public/ads.txt` com exatamente:

`google.com, pub-3948145279411749, DIRECT, f08c47fec0942fa0`

O documento root precisa continuar apontando para `public/` na publicação para que `/ads.txt` seja servido diretamente como arquivo texto.

### CSP/CMP

Nenhuma allowlist genérica com `*` foi adicionada. A CSP existente continua abrindo recursos de ads somente quando a publicidade real está habilitada/configurada. Não foi criada uma segunda CMP sobre a CMP do Google.

Uma migração ampla da CSP para nonce/`strict-dynamic` foi deliberadamente deixada fora desta RC por ser transversal e não necessária para ativar os placements já previstos.

## 11. Instagram

`STRIDEBR_INSTAGRAM_URL` foi centralizado no environment e propagado para os locais sociais já existentes. Não foi criada área promocional nova.

## 12. Environment

`.env.example` documenta, sem secrets reais:

- `STRIDEBR_INTEGRATIONS_SECRET`;
- `STRAVA_CLIENT_ID`, `STRAVA_CLIENT_SECRET`;
- `POLAR_CLIENT_ID`, `POLAR_CLIENT_SECRET`, endpoints/scopes atuais;
- `GOOGLE_HEALTH_CLIENT_ID`, `GOOGLE_HEALTH_CLIENT_SECRET`, endpoints/scopes atuais;
- `COROS_MCP_URL`;
- `SUUNTO_CLIENT_ID`, `SUUNTO_CLIENT_SECRET`, OAuth/base/subscription key;
- flags/client/slots AdSense;
- `STRIDEBR_INSTAGRAM_URL`.

Variáveis Fitbit Web ativas foram removidas. Nenhuma credencial COROS fictícia foi adicionada.

O `compose.yaml` propaga as variáveis. `compose.dokploy.yaml` já usa `env_file`, portanto não exige duplicar cada integração nessa definição.

## 13. Continuidade da rodada Cronogramas/Agenda

As alterações de hierarquia visual já concluídas antes desta rodada foram preservadas no mesmo working tree e fazem parte do pacote final:

- pseudo-calendário semanal removido antes da view Mês/Lista;
- resumo desktop preservado na sidebar;
- resumo mobile compacto;
- navegação temporal unificada;
- Semana sem duplicar sua lista completa antes da grade;
- Lista sem “lista antes da lista”;
- screenshots em `docs/reports/screenshots/RC4_SCHEDULE_HIERARCHY_2026-09-08/`.

Browser fixture final: 56 assertions. Offset antes do calendário mensal: 338 px no fixture conceitual anterior e 58 px no estado final, redução de 280 px.

## 14. Arquivos alterados em relação ao ZIP-base

### Modificados

- `.env.example`
- `compose.yaml`
- `docs/INTEGRATIONS.md`
- `docs/INTEGRATIONS_SETUP.md`
- `docs/MONETIZATION.md`
- `public/assets/css/cronogramas.css`
- `public/assets/css/ui-refresh.css`
- `public/assets/js/cronogramas.js`
- `public/auth/integration-callback.php`
- `public/auth/integration.php`
- `public/function/integration-action.php`
- `public/user/cronogramatreinos.php`
- `public/user/onboarding.php`
- `public/user/settings.php`
- `scripts/sync_integrations.php`
- `scripts/test_static.sh`
- `scripts/tests/browser_planning_product.py`
- `scripts/tests/test_ads_placements_static.php`
- `scripts/tests/test_deploy_configuration.php`
- `scripts/tests/test_integrations_foundation_static.php`
- `scripts/tests/test_production_theme_migrations_static.php`
- `src/function/integrations.php`
- `src/i18n/en.php`
- `src/i18n/pt-BR.php`
- `src/includes/configuration.php`
- `src/layout/footer.php`

### Adicionados

- `public/ads.txt`
- `public/auth/mcp-client-metadata.php`
- `src/database/migrations/20260908_integrations_providers.sql`
- `scripts/tests/test_integrations_external_rc4.php`
- `scripts/tests/test_schedule_hierarchy_static.php`
- `scripts/tests/browser_schedule_hierarchy.py`
- `docs/reports/screenshots/RC4_SCHEDULE_HIERARCHY_2026-09-08/*`
- `docs/reports/RC4_INTEGRATIONS_ADSENSE_2026-09-08.md`

Nenhum arquivo do ZIP-base foi removido.

## 15. Testes executados

### Suíte estática final

Comando:

`./scripts/test_static.sh`

Resultado: PASS.

- 68 checks/grupos reportados como aprovados;
- 4.714 assertions explicitamente numeradas em 52 grupos que informam contagem;
- PHP syntax: PASS;
- JavaScript syntax: PASS;
- shell syntax: PASS;
- migrations/search_path: PASS;
- environment/deploy: 83 assertions;
- integrações foundation: 17 assertions;
- integrações externas RC4: 42 assertions;
- AdSense: 46 assertions;
- hierarquia de Cronogramas: 54 assertions;
- i18n: 3.190 assertions / 3.112 keys por locale;
- segurança/templates: PASS;
- ausência de merge markers: PASS.

Os testes de integrações cobrem, entre outros:

- registry/provider desconhecido;
- provider sem configuração;
- AES-256-GCM encrypt/decrypt;
- token expirando;
- refresh token presente no fluxo;
- OAuth state e expiração;
- Google Health offline access/prompt condicional;
- Polar v4 endpoints/scopes e ausência de legado;
- COROS discovery, PKCE, client metadata, MCP 2026-07-28 e fallback legado;
- limite/fallback FIT COROS;
- Strava dedup/revoke/rate-limit;
- Suunto sem subscription key indisponível;
- Garmin/HALO indisponíveis;
- ausência de Fitbit ativo;
- ausência de secrets no `.env.example`;
- ausência de erro externo bruto em logs OAuth/runner.

### Browser fixture de Cronogramas

Comando:

`python3 scripts/tests/browser_schedule_hierarchy.py`

Resultado: PASS, 56 assertions e screenshots atualizados.

### Browser fixture dependente do app real

`scripts/tests/browser_planning_product.py` não pôde ser executado neste ambiente: o Playwright procura seu Chromium gerenciado, que não está instalado. Além disso, esse teste depende da aplicação/fixtures de banco em `localhost:8080`. O Chromium de sistema existe e foi suficiente para o fixture autocontido de hierarquia.

### PostgreSQL/Docker

`docker` e `psql` não estão disponíveis neste ambiente. Portanto não foi possível executar conexão OAuth persistida/importação contra PostgreSQL real. Nenhuma request real foi feita para Google Health, Polar, COROS, Strava, Suunto ou Google Ads durante os testes automatizados.

### Revisão de working tree

- `git diff --check`: PASS;
- nenhuma operação Git de escrita foi executada;
- varredura por padrões de private key, Google access/API token, GitHub token, Stripe live key e JWT longo: zero hits fora do `.env` privado;
- IDs reais dos sete slots AdSense: zero ocorrências no código/documentação;
- endpoints Fitbit/Polar legados: somente documentação/testes de regressão, nenhum endpoint ativo;
- `COROS_CLIENT_ID/SECRET`: somente documentação/teste dizendo para não criar;
- `public/ads.txt`: conteúdo exato validado.

## 16. Limitações e ações externas antes/depois da publicação

1. Aplicar migrations antes da primeira conexão `google_health` ou `coros`.
2. Manter `STRIDEBR_INTEGRATIONS_SECRET` estável, privado e com pelo menos 32 caracteres. Trocar esse segredo invalida a capacidade de descriptografar tokens já armazenados.
3. Configurar Client IDs/Secrets de Strava, Polar e Google Health no ambiente de produção.
4. Registrar exatamente os callbacks de produção nos consoles externos aplicáveis.
5. Google Health: habilitar/configurar o projeto, consent screen e scopes; Testing Mode pode gerar refresh token de aproximadamente sete dias; escala pública pode exigir verification/security review.
6. Strava: respeitar o limite/review do aplicativo e sua capacidade inicial de atletas; não existe workaround no código.
7. Suunto: obter aprovação/credenciais e `SUUNTO_SUBSCRIPTION_KEY` antes de ficar disponível.
8. Garmin continua dependendo da liberação externa do programa.
9. HALO continua dependendo de documentação/parceria oficial.
10. AdSense: publicar `public/ads.txt`, confirmar CMP externa, configurar `STRIDEBR_ADSENSE_CLIENT` e os slots públicos no environment e manter `STRIDEBR_ADS_AUTHENTICATED_ENABLED=0` nesta RC.
11. Fazer smoke test autenticado real de conexão/sync por provider no ambiente publicado, pois este runner não possui PostgreSQL nem credenciais reais.

## 17. Segurança do pacote

O working tree contém um `.env` privado local. Ele não é necessário para substituir o código e pode conter configuração sensível. O ZIP final desta revisão deve excluir:

- `.git/`;
- `.env`.

`.env.example` permanece no pacote.

## 18. Confirmações

- Sem commit.
- Sem push.
- Sem tag.
- Sem deploy.
- Uma migration mínima foi criada exclusivamente porque o CHECK existente impediria os dois novos providers.
- Nenhuma migration de dados/atividade/cronograma foi criada.
- Nenhuma credencial privada real foi adicionada ao código.
- Nenhum slot AdSense real foi hardcoded.
- Publicidade autenticada permanece desligada por flag.
- Estado preparado para `StrideBR 1.0.0-rc.4`.
