# Configuração de conexões do StrideBR

O StrideBR carrega variáveis da raiz do projeto em `.env`. Variáveis definidas diretamente no servidor têm prioridade e não são sobrescritas pelo arquivo.

## Preparação

Na raiz do projeto:

```bash
./scripts/setup_env.sh
nano .env
php scripts/integrations_status.php
```

O script cria `.env` a partir de `.env.example`, gera `STRIDEBR_INTEGRATIONS_SECRET` quando ainda não existe e tenta aplicar permissão `600`.

Nunca versione `.env`. Não altere `STRIDEBR_INTEGRATIONS_SECRET` depois que houver conexões gravadas no banco. Todos os processos/servidores que acessam a mesma tabela `integracoes_usuario` devem usar o mesmo segredo.

Em produção configure também:

```dotenv
STRIDEBR_APP_ENV=production
STRIDEBR_APP_URL=https://seu-dominio
```

Os callbacks são sempre:

```text
https://seu-dominio/auth/integration-callback.php?provider=strava
https://seu-dominio/auth/integration-callback.php?provider=polar
https://seu-dominio/auth/integration-callback.php?provider=fitbit
https://seu-dominio/auth/integration-callback.php?provider=suunto
https://seu-dominio/auth/integration-callback.php?provider=garmin
```

## Strava

Crie um aplicativo no painel de API do Strava. Configure o domínio de callback com o domínio público do StrideBR e copie Client ID e Client Secret:

```dotenv
STRAVA_CLIENT_ID=
STRAVA_CLIENT_SECRET=
```

O StrideBR solicita `read,activity:read_all`.

## Polar Flow / AccessLink

Crie um client em `https://admin.polaraccesslink.com/`, adicione o callback exato e copie Client ID/Secret:

```dotenv
POLAR_CLIENT_ID=
POLAR_CLIENT_SECRET=
POLAR_OAUTH_SCOPE=accesslink.read_all
```

O StrideBR registra o usuário no AccessLink após o OAuth e importa exercícios disponíveis pela API v3.

## Fitbit

Crie/gerencie um aplicativo em `https://dev.fitbit.com/`, use aplicação web/server e registre o callback exato. Configure:

```dotenv
FITBIT_CLIENT_ID=
FITBIT_CLIENT_SECRET=
FITBIT_OAUTH_SCOPE=activity profile heartrate location
```

A sincronização atual usa a lista de atividades e tenta obter TCX quando disponível.

## Suunto

A Suunto Cloud API exige acesso ao Partner Program/API Zone. Depois da aprovação, assine a Developer API, configure o OAuth no perfil, registre o callback e copie Client ID, Client Secret e a subscription key:

```dotenv
SUUNTO_CLIENT_ID=
SUUNTO_CLIENT_SECRET=
SUUNTO_SUBSCRIPTION_KEY=
SUUNTO_OAUTH_AUTHORIZE_URL=https://cloudapi-oauth.suunto.com/oauth/authorize
SUUNTO_OAUTH_TOKEN_URL=https://cloudapi-oauth.suunto.com/oauth/token
SUUNTO_OAUTH_SCOPE=workout
SUUNTO_API_BASE_URL=https://cloudapi.suunto.com
```

## Garmin Connect

Solicite acesso ao Garmin Connect Developer Program. O código deixa o provedor desativado até receber as credenciais e os endpoints oficiais liberados para o projeto:

```dotenv
GARMIN_OAUTH_CLIENT_ID=
GARMIN_OAUTH_CLIENT_SECRET=
GARMIN_OAUTH_AUTHORIZE_URL=
GARMIN_OAUTH_TOKEN_URL=
GARMIN_OAUTH_SCOPE=
```

Activity API, Training API e Courses API dependem da aprovação/configuração do projeto no portal Garmin.

## Health Connect, Samsung Health e Apple Health

Não são conexões OAuth do site e não podem ser lidas diretamente por navegador/PWA. Health Connect é uma API/SDK Android; Apple Health é acessado via HealthKit com capability/entitlement nativo. A integração automática direta exige um app ou bridge nativo mínimo. Sem isso, o StrideBR pode receber dados indiretamente por integrações cloud compatíveis ou por importação manual, mas não acessar esses repositórios locais diretamente.

## Sincronização periódica

Depois que houver contas conectadas:

```bash
./scripts/sync_integrations.sh
```

Em VPS pode ser executado por cron, por exemplo a cada 10 minutos:

```cron
*/10 * * * * cd /caminho/stridebr && ./scripts/sync_integrations.sh >> /var/log/stridebr-integrations.log 2>&1
```

Antes de ativar em produção, rode:

```bash
php scripts/integrations_status.php
```
