# API mobile do StrideBR Core

O aplicativo StrideBR conversa exclusivamente com a API HTTP versionada do Core:
`https://stridebr.com.br/api/v1`. Ele nunca acessa PostgreSQL, secrets de integrações
ou sessões PHP do site.

## Estado inicial implementado

`GET /health`, `GET /meta`, `POST /auth/login`, `POST /auth/refresh`,
`POST /auth/logout`, `GET /me`, `GET /activities` e `GET /activities/{id}`.
As rotas são JSON-only. Sucesso usa `{ "data": ... }`; falhas usam
`{ "error": { "code", "message", "fields"? } }`.

Use `Authorization: Bearer <access_token>` nas rotas autenticadas. Access tokens
expiram em 15 minutos. Refresh tokens expiram em 30 dias, são armazenados somente
como SHA-256 no servidor, são rotacionados a cada uso e a sessão anterior é revogada.
Alteração de senha, desativação da conta ou incremento de `sessao_versao` invalida
as sessões da API. O app deve guardar tokens somente no armazenamento seguro da
plataforma (Android Keystore / iOS Keychain), nunca em logs ou analytics.

As datas são ISO 8601 com offset. O banco usa `America/Sao_Paulo`; consumidores
devem preservar o offset recebido. Listagens usam `page` e `limit` (máximo 100),
e retornam `pagination` com total.

`GET /activities` aceita `page`, `limit`, `sport`, `from`, `to` e `q`. A lista é
resumida; o detalhe retorna segmentos e equipamentos. Rotas GPS grandes, streams,
uploads, criação/edição e importação ainda não fazem parte da v1 pública.

Para operações de criação futuras, o app deverá enviar uma `Idempotency-Key` e
manter uma fila local de reenvio. O Core não usa sessão web nem CSRF para Bearer
tokens. CORS não é aberto por padrão: aplicativos nativos não precisam dele.

OAuth de Strava, Polar e demais provedores permanece no Core. O app nunca recebe
client secrets, refresh tokens de provedores ou segredos de webhook. Um fluxo de
navegador externo + deep link será definido antes de expor OAuth ao app.

Ambientes devem apontar explicitamente para development, staging ou produção;
produção é `https://stridebr.com.br/api/v1`. A v1 só recebe mudanças compatíveis;
uma mudança incompatível exige uma versão nova.
