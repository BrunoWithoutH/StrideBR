# Configuração de integrações do StrideBR

O StrideBR lê variáveis do ambiente do processo e, como fallback local, do `.env`. Valores já definidos pelo servidor têm prioridade. Nunca versione `.env` nem copie secrets reais para `.env.example`.

## Base

```dotenv
STRIDEBR_APP_ENV=production
STRIDEBR_APP_URL=https://stridebr.com.br
STRIDEBR_INTEGRATIONS_SECRET=
```

`STRIDEBR_INTEGRATIONS_SECRET` deve ser longo, permanente e igual em todos os processos que acessam `integracoes_usuario`.

## Strava

```dotenv
STRAVA_CLIENT_ID=
STRAVA_CLIENT_SECRET=
```

Callback:

```text
https://stridebr.com.br/auth/integration-callback.php?provider=strava
```

Scopes solicitados: `read,activity:read_all`.

## Polar AccessLink API v4

```dotenv
POLAR_CLIENT_ID=
POLAR_CLIENT_SECRET=
POLAR_OAUTH_AUTHORIZE_URL=https://auth.polar.com/oauth/authorize
POLAR_OAUTH_TOKEN_URL=https://auth.polar.com/oauth/token
POLAR_OAUTH_SCOPE="training_sessions:read activity:read profile:read"
```

Callback:

```text
https://stridebr.com.br/auth/integration-callback.php?provider=polar
```

Não configure os endpoints antigos `flow.polar.com/oauth2/authorization`, `polarremote.com/v2/oauth2/token` nem `accesslink.read_all`.

## Google Health

```dotenv
GOOGLE_HEALTH_CLIENT_ID=
GOOGLE_HEALTH_CLIENT_SECRET=
GOOGLE_HEALTH_OAUTH_AUTHORIZE_URL=https://accounts.google.com/o/oauth2/v2/auth
GOOGLE_HEALTH_OAUTH_TOKEN_URL=https://oauth2.googleapis.com/token
GOOGLE_HEALTH_OAUTH_SCOPE="https://www.googleapis.com/auth/googlehealth.activity_and_fitness.readonly https://www.googleapis.com/auth/googlehealth.location.readonly https://www.googleapis.com/auth/googlehealth.health_metrics_and_measurements.readonly"
```

Callback:

```text
https://stridebr.com.br/auth/integration-callback.php?provider=google_health
```

Essa conexão substitui o Fitbit Web legado. Não configure `FITBIT_CLIENT_ID`, `FITBIT_CLIENT_SECRET` ou `FITBIT_OAUTH_*` para novas conexões.

Google Sign-In é outra aplicação/fluxo e continua usando `/auth/google-callback.php`.

Em OAuth Testing Mode, esteja preparado para refresh tokens de curta duração. Para produção pública, conclua no Google Cloud/Health Console as verificações e revisões exigidas para os scopes solicitados.

## COROS MCP

```dotenv
COROS_MCP_URL=https://mcp.coros.com/mcp
```

Callback:

```text
https://stridebr.com.br/auth/integration-callback.php?provider=coros
```

Não defina `COROS_CLIENT_ID` ou `COROS_CLIENT_SECRET`. O client OAuth é descoberto/estabelecido pelo protocolo MCP atual, com Protected Resource Metadata path-aware, PKCE S256 e negociação `2026-07-28`; existe fallback controlado para compatibilidade com servidor MCP legado.

O endpoint público de metadata do client é:

```text
https://stridebr.com.br/auth/mcp-client-metadata.php
```

## Suunto

```dotenv
SUUNTO_CLIENT_ID=
SUUNTO_CLIENT_SECRET=
SUUNTO_OAUTH_AUTHORIZE_URL=https://cloudapi-oauth.suunto.com/oauth/authorize
SUUNTO_OAUTH_TOKEN_URL=https://cloudapi-oauth.suunto.com/oauth/token
SUUNTO_OAUTH_SCOPE=workout
SUUNTO_OAUTH_TOKEN_AUTH=basic
SUUNTO_API_BASE_URL=https://cloudapi.suunto.com
SUUNTO_SUBSCRIPTION_KEY=
```

Callback:

```text
https://stridebr.com.br/auth/integration-callback.php?provider=suunto
```

Sem `SUUNTO_SUBSCRIPTION_KEY`, o provider permanece indisponível.

## Garmin

A entrada da Garmin continua intencionalmente bloqueada no Web. Não preencha endpoints não confirmados. Quando o acesso oficial for liberado, o projeto poderá integrar Activity API, Training API e Courses API sobre a infraestrutura existente.

## HALO e integrações mobile

HALO não possui conexão direta habilitada. Health Connect, Samsung Health e Apple Health permanecem dependentes do futuro app mobile; nenhuma delas deve iniciar OAuth pelo Web.

## Sincronização

Status de configuração sem revelar secrets:

```bash
php scripts/integrations_status.php
```

Sincronização dos providers permitidos no runner:

```bash
./scripts/sync_integrations.sh
```

Filtros disponíveis:

```bash
./scripts/sync_integrations.sh --provider=strava
./scripts/sync_integrations.sh --provider=polar
./scripts/sync_integrations.sh --provider=google_health
./scripts/sync_integrations.sh --user=ID_DO_USUARIO
./scripts/sync_integrations.sh --limit=100
```

COROS não é incluído no polling periódico; use “Sincronizar agora” na interface.

## Banco

A migration `20260908_integrations_providers.sql` somente amplia o CHECK de `integracoes_usuario.provedor` para aceitar `google_health` e `coros`. Não adiciona tabela ou coluna e mantém `fitbit` aceito para registros históricos.

## Sincronização automática — operação no Dokploy

Configure **um agendamento a cada 15 minutos** (`*/15 * * * *`) no serviço `app`,
com as mesmas variáveis de ambiente e acesso ao PostgreSQL da aplicação:

```sh
sh /var/www/html/scripts/sync_integrations.sh --limit=100
```

Se Infra executar o agendamento no host, a forma equivalente, no diretório do
Compose de produção já configurado, é:

```sh
docker compose -f compose.dokploy.yaml exec -T app sh /var/www/html/scripts/sync_integrations.sh --limit=100
```

O shell e o runner existentes foram preservados. Não é necessário criar outro
serviço de sincronização. Este documento não instala cron nem realiza deploy.

- Strava, Polar, Google Health e Suunto configurado: entrada de atividades ON em
  novas conexões, sincronização inicial no retorno OAuth e execução periódica.
  Preferências OFF existentes são preservadas; o botão manual continua disponível.
- COROS: sincronização manual, fora do runner periódico, inclusive quando se passa
  `--provider=coros`. O comportamento existente de importação após conexão permanece.
- Apenas conexões habilitadas, conectadas ou com erro recuperável e prazo vencido
  são selecionadas. A última sincronização completa deve ter pelo menos 15 minutos.
- Tokens só são renovados quando a conexão elegível vai sincronizar e o token vence
  em até 120 segundos. Não há renovação em massa nem polling pelo navegador.
- PostgreSQL mantém uma trava por usuário/provedor entre processos e containers.
  A gravação da atividade e de sua identidade externa é atômica, com trava também
  por atividade e preservação do índice único já existente.
- Falhas recebem backoff de 15, 30, 60 minutos etc., limitado a 6 horas.
  `Retry-After` pode ampliar a espera. HTTP 401 exige reautorização.
  Rate limits conhecidos são compartilhados entre conexões do mesmo provedor;
  no Strava, o limite diário aguarda a próxima meia-noite UTC.
- Strava solicita listagem paginada e detalhes; usa polyline e laps do detalhe.
  Não acrescenta consultas de streams por atividade nesta rodada. Sem GPS, uma
  atividade pode ser importada sem rota. Falha no detalhe não vira importação
  silenciosa de um resumo incompleto.
- A importação inicial Strava limita detalhes a duas atividades e a janela HTTP
  a aproximadamente 12 segundos; o restante continua pelo runner. Execuções
  posteriores têm orçamento de 50 detalhes, até 10 páginas e janela HTTP de
  aproximadamente 120 segundos. O checkpoint só avança após conclusão sem falhas
  nem adiamentos. Limites de banco/refresh e tempo de persistência são adicionais.
- O runner termina com código 1 se houver falhas, inclusive parciais, e código 2
  para configuração/filtro inválido. Monitore saída e logs PHP; não salve tokens
  nem respostas de API para diagnosticar.

O diagnóstico usa `provider`, `stage`, `external_activity_id`, `http_status`,
`exception_type`, `error_code` e, em falhas PostgreSQL, `sqlstate`. Não registra
mensagem bruta da exceção, payload, cabeçalho Authorization ou credenciais.

### Evolução para webhook do Strava

A persistência e normalização são reutilizáveis, separadas do gatilho periódico.
Um futuro consumidor de webhook deve reutilizar as mesmas travas, deduplicação e
backoff, com validação da assinatura/assinatura de eventos conforme o contrato
oficial, validação de assinatura de subscription quando aplicável e tratamento
explícito de criação/alteração/exclusão e revogação. Nenhum endpoint ou subscription
incompleto foi criado. Antes dessa etapa, confirmar as regras atuais de verificação
e entrega na [documentação oficial de webhooks](https://developers.strava.com/docs/webhooks/).

Referências: [API de atividades](https://developers.strava.com/docs/reference/),
[limites de requisição](https://developers.strava.com/docs/rate-limits/).
