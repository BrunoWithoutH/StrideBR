# StrideBR — configuração final do ambiente e Conexões

## Regra importante

O StrideBR agora lê automaticamente o arquivo `.env` da raiz do projeto. Variáveis já definidas pelo sistema/servidor têm prioridade e não são sobrescritas.

`.env` contém segredos e não deve entrar no Git nem ser enviado em ZIP público. O deploy para Alwaysdata preserva o `.env` remoto.

## 1. Ambiente local no T480

```bash
cd /srv/http/stridebr
./scripts/setup_env.sh
nano .env
php scripts/integrations_status.php
```

O modo local cria `.env` a partir de `.env.example` e gera `STRIDEBR_INTEGRATIONS_SECRET` automaticamente.

Se você usa Docker Compose local, o arquivo fica montado junto do projeto e passa a ser lido pelo PHP do StrideBR. Não é necessário adicionar manualmente cada Connect em `compose.yaml`.

## 2. Produção atual — Alwaysdata

Entre por SSH:

```bash
ssh stridebr@ssh-stridebr.alwaysdata.net
cd ~/www/stridebr
./scripts/setup_env.sh --production --url https://stridebr.alwaysdata.net
nano .env
php scripts/integrations_status.php
```

O modo `--production` propositalmente não grava variáveis de banco. Assim ele não substitui a configuração PostgreSQL que o Alwaysdata já fornece.

Proteja o arquivo:

```bash
chmod 600 .env
```

O segredo `STRIDEBR_INTEGRATIONS_SECRET` é usado para cifrar access tokens e refresh tokens no banco. Depois que houver contas conectadas, não troque esse valor. Se houver mais de uma instância do StrideBR acessando o mesmo banco, todas devem usar exatamente o mesmo segredo.

Faça backup desse segredo em um gerenciador de senhas.

## 3. URL e callbacks

No `.env` de produção:

```dotenv
STRIDEBR_APP_ENV=production
STRIDEBR_APP_URL=https://stridebr.alwaysdata.net
```

Enquanto estiver no Alwaysdata, cadastre estes callbacks:

```text
https://stridebr.alwaysdata.net/auth/integration-callback.php?provider=strava
https://stridebr.alwaysdata.net/auth/integration-callback.php?provider=polar
https://stridebr.alwaysdata.net/auth/integration-callback.php?provider=fitbit
https://stridebr.alwaysdata.net/auth/integration-callback.php?provider=suunto
https://stridebr.alwaysdata.net/auth/integration-callback.php?provider=garmin
```

Quando mudar para o VPS/domínio definitivo, altere `STRIDEBR_APP_URL` e os callbacks nos portais dos provedores.

## 4. Strava

1. Entre em `https://www.strava.com/settings/api`.
2. Crie/configure o aplicativo StrideBR.
3. Em Authorization Callback Domain use `stridebr.alwaysdata.net` enquanto este for o domínio público.
4. Copie Client ID e Client Secret.
5. No `.env` remoto:

```dotenv
STRAVA_CLIENT_ID=...
STRAVA_CLIENT_SECRET=...
```

6. Rode:

```bash
php scripts/integrations_status.php
```

7. Entre no StrideBR > Editar perfil > Conexões > Strava > Conectar.

O StrideBR solicita `read,activity:read_all` e importa as atividades autorizadas.

## 5. Polar Flow / AccessLink

1. Entre em `https://admin.polaraccesslink.com/` com uma conta Polar Flow.
2. Crie um client para o StrideBR.
3. Adicione o callback exato do Polar mostrado acima.
4. Guarde Client ID e Client Secret.
5. No `.env`:

```dotenv
POLAR_CLIENT_ID=...
POLAR_CLIENT_SECRET=...
POLAR_OAUTH_SCOPE=accesslink.read_all
```

6. Rode o status e conecte pelo perfil.

Depois do OAuth, o StrideBR registra o usuário no AccessLink e consegue ler exercícios disponíveis pela API. A API v3 retorna exercícios enviados ao Flow nos últimos 30 dias e somente depois do usuário ter sido registrado com o client.

## 6. Fitbit

1. Entre em `https://dev.fitbit.com/`.
2. Use Register an App / Manage My Apps.
3. Crie uma aplicação web/server para o StrideBR e registre o callback exato do Fitbit.
4. Copie Client ID e Client Secret.
5. No `.env`:

```dotenv
FITBIT_CLIENT_ID=...
FITBIT_CLIENT_SECRET=...
FITBIT_OAUTH_SCOPE=activity profile heartrate location
```

6. Confira o status e conecte pelo perfil.

A implementação atual lê a lista de atividades e tenta buscar o TCX do registro quando o Fitbit o disponibiliza. Não depende das APIs intraday especiais.

## 7. Suunto

A Suunto Cloud API não é aberta para uso pessoal simples. É preciso entrar no Partner Program.

1. Solicite acesso ao Suunto Partner Program/Cloud API.
2. Depois da aprovação, entre no API Zone.
3. Assine a Developer API para obter a subscription key.
4. No perfil do API Zone, configure App name, Client secret e o callback exato.
5. O Client ID é gerado pela Suunto.
6. No `.env`:

```dotenv
SUUNTO_CLIENT_ID=...
SUUNTO_CLIENT_SECRET=...
SUUNTO_SUBSCRIPTION_KEY=...
SUUNTO_OAUTH_AUTHORIZE_URL=https://cloudapi-oauth.suunto.com/oauth/authorize
SUUNTO_OAUTH_TOKEN_URL=https://cloudapi-oauth.suunto.com/oauth/token
SUUNTO_OAUTH_SCOPE=workout
SUUNTO_API_BASE_URL=https://cloudapi.suunto.com
```

7. Confira o status e conecte.

Quando for publicar para usuários reais, siga também o processo da Production API da Suunto.

## 8. Garmin Connect

A Garmin exige aprovação no Garmin Connect Developer Program.

1. Solicite o Garmin Connect Developer Program para o StrideBR.
2. Peça/seleciona pelo menos Activity API; Training API e Courses API fazem sentido para enviar treinos e rotas do StrideBR ao Garmin Connect.
3. Depois da aprovação, use os Client ID/Secret, endpoints OAuth e scopes liberados para o seu projeto.
4. Configure:

```dotenv
GARMIN_OAUTH_CLIENT_ID=...
GARMIN_OAUTH_CLIENT_SECRET=...
GARMIN_OAUTH_AUTHORIZE_URL=...
GARMIN_OAUTH_TOKEN_URL=...
GARMIN_OAUTH_SCOPE=...
```

5. Só considere a conexão Garmin liberada quando `php scripts/integrations_status.php` mostrar `PRONTO` e o adaptador tiver sido validado contra a documentação entregue no Developer Portal.

O StrideBR já tem a fundação de conta, token cifrado, preferências, origem e deduplicação. O recebimento final de Activity API e envio por Training/Courses precisa dos contratos oficiais liberados pela Garmin ao projeto.

## 9. Health Connect, Samsung Health e Apple Health

Esses três não são ativados com Client ID no site:

- Galaxy Watch -> Samsung Health -> Health Connect -> futuro app Android StrideBR.
- Outros apps Android compatíveis -> Health Connect -> futuro app Android StrideBR.
- Apple Watch -> Apple Health/HealthKit -> futuro app iOS StrideBR.

No site eles permanecem como integrações preparadas/informativas até existir o app móvel.

## 10. Sincronização automática

Teste manualmente:

```bash
cd ~/www/stridebr
./scripts/sync_integrations.sh
```

No VPS, depois, use cron. Exemplo a cada 10 minutos:

```cron
*/10 * * * * cd /CAMINHO/DO/stridebr && ./scripts/sync_integrations.sh >> /var/log/stridebr-integrations.log 2>&1
```

Enquanto estiver no Alwaysdata, você pode usar o sistema de tarefas agendadas da hospedagem ou continuar usando o botão Sincronizar agora até configurar a execução periódica.

## 11. Doações

Inicialmente deixe:

```dotenv
STRIDEBR_DONATION_ENABLED=0
STRIDEBR_DONATION_PIX_KEY=
STRIDEBR_DONATION_PIX_NAME=
STRIDEBR_DONATION_URL=
```

No VPS, quando quiser ativar:

```dotenv
STRIDEBR_DONATION_ENABLED=1
STRIDEBR_DONATION_PIX_KEY=sua-chave
STRIDEBR_DONATION_PIX_NAME=nome-do-favorecido
STRIDEBR_DONATION_URL=https://...
```

PIX e URL são independentes; pode usar apenas um deles.

## 12. Anúncios

Mantenha inicialmente:

```dotenv
STRIDEBR_ADS_ENABLED=0
```

Depois do domínio/VPS definitivo, configure AdSense, `ads.txt`, consentimento e os slots `STRIDEBR_ADSENSE_*`. O StrideBR não carrega anúncios nas áreas autenticadas com dados esportivos.

## 13. Migrations e testes

Localmente:

```bash
cd /srv/http/stridebr
./scripts/deploy_alwaysdata.sh
./scripts/migrate_product.sh status
sudo ./scripts/test_all.sh
```

Na RC2, confira `./scripts/migrate_product.sh status` antes e depois do deploy. A migration pós-RC1 presente no pacote é `20260903_z_activity_duration_precision_ms.sql`; preserve seu nome se ela já tiver sido aplicada em qualquer banco compartilhado.

Depois que tudo estiver validado, se quiser recalcular calorias antigas:

```bash
./scripts/recalculate_activity_energy.sh --all
```

## 14. Diagnóstico rápido

Sempre que mexer no `.env`:

```bash
php scripts/integrations_status.php
```

O comando não imprime client secrets, access tokens, refresh tokens nem `STRIDEBR_INTEGRATIONS_SECRET`. Ele mostra somente o que está pronto e o que está faltando.
