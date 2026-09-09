# Integrações do StrideBR

O StrideBR usa uma camada única de conexões externas. Tokens de usuário são armazenados em `integracoes_usuario` e protegidos por AES-256-GCM com `STRIDEBR_INTEGRATIONS_SECRET`. Atividades importadas entram pelo mesmo normalizador usado no restante do produto e são deduplicadas pela combinação `provedor + id_externo`.

## Estado atual

| Provedor | Estado Web | Entrada de atividades | Observações |
| --- | --- | --- | --- |
| Strava | Implementado | Sim | OAuth, refresh sob demanda, detalhes, rota/laps quando disponíveis e deduplicação |
| Polar | Implementado em AccessLink API v4 | Sim | OAuth atual, refresh sob demanda, sessões de treino, rotas, estatísticas e laps quando disponíveis |
| Google Health | Implementado | Sim | Substitui a integração Fitbit Web legada; importa exercícios e tenta TCX para GPS/dados detalhados quando autorizado |
| COROS | Implementado via MCP | Sim | OAuth por discovery/PKCE do MCP oficial; FIT quando disponível e detalhe MCP como fallback; sincronização manual |
| Suunto | Preparado | Sim quando configurado | Requer aprovação/credenciais e `SUUNTO_SUBSCRIPTION_KEY` |
| Garmin Connect | Indisponível | Não | Adapter final continua bloqueado até acesso oficial; interesse futuro em Activity API, Training API e Courses API |
| HALO | Aguardando disponibilidade | Não | Nenhuma API direta foi presumida; futuro mobile pode usar Health Connect/Apple Health |
| Health Connect | Futuro mobile | Não no Web | Requer app Android |
| Samsung Health | Futuro mobile | Não no Web | Previsto via Health Connect/app Android |
| Apple Health | Futuro mobile | Não no Web | Requer app iOS/HealthKit |
| WHOOP | Fora de escopo | Não | Não implementado nesta rodada |
| Fitbit Web legado | Aposentado | Não | Registros históricos continuam válidos; novas conexões usam Google Health |

## Callbacks OAuth

Os providers cloud usam o callback central:

```text
https://stridebr.com.br/auth/integration-callback.php?provider=strava
https://stridebr.com.br/auth/integration-callback.php?provider=polar
https://stridebr.com.br/auth/integration-callback.php?provider=google_health
https://stridebr.com.br/auth/integration-callback.php?provider=coros
https://stridebr.com.br/auth/integration-callback.php?provider=suunto
```

Google Sign-In continua separado:

```text
https://stridebr.com.br/auth/google-callback.php
```

O callback de integrações valida `state`, expiração da tentativa, sessão autenticada e redirect interno seguro antes de trocar o authorization code por tokens.

## Segurança de tokens

`STRIDEBR_INTEGRATIONS_SECRET` deve ser um segredo permanente com pelo menos 32 caracteres. O StrideBR deriva uma chave e criptografa access tokens e refresh tokens com AES-256-GCM.

Secrets de aplicação e tokens de usuário não são enviados ao JavaScript nem renderizados na interface. Trocar `STRIDEBR_INTEGRATIONS_SECRET` depois que usuários conectaram contas exige reconexão dessas contas.

## Strava

Scopes atuais:

```text
read
activity:read_all
```

O access token é renovado individualmente quando está próximo do vencimento. A sincronização lista atividades recentes, consulta detalhe somente para atividades ainda não importadas, reaproveita rota/laps/dispositivo quando retornados e interrompe enriquecimento adicional quando os headers de rate limit indicam proximidade do limite. A deduplicação ocorre antes da persistência.

O aplicativo Strava de produção ainda está sujeito à capacidade/review configurada externamente. Webhooks podem ser adicionados no futuro, mas não foram criados nesta RC.

## Polar AccessLink API v4

OAuth:

```text
https://auth.polar.com/oauth/authorize
https://auth.polar.com/oauth/token
```

Scopes:

```text
training_sessions:read activity:read profile:read
```

A integração usa a API v4 de training sessions. O parâmetro `to` é tratado como exclusivo. Para enriquecer uma atividade, o StrideBR consulta o dia da sessão com `routes`, `statistics` e `laps`, sem executar a antiga etapa de registro `/v3/users`.

Access e refresh tokens são salvos criptografados e o refresh ocorre somente para a conexão que está sendo usada.

## Google Health

OAuth:

```text
https://accounts.google.com/o/oauth2/v2/auth
https://oauth2.googleapis.com/token
```

Scopes somente leitura:

```text
https://www.googleapis.com/auth/googlehealth.activity_and_fitness.readonly
https://www.googleapis.com/auth/googlehealth.location.readonly
https://www.googleapis.com/auth/googlehealth.health_metrics_and_measurements.readonly
```

O fluxo usa `response_type=code` e `access_type=offline`. `prompt=consent` só é solicitado em reautorização, quando não existe refresh token utilizável ou quando os scopes concedidos não cobrem os scopes atuais.

A sincronização importa os data points de exercício e tenta `exportExerciseTcx` para obter GPS e detalhes compatíveis com o parser TCX existente. Se localização não foi concedida ou o TCX não estiver disponível, a atividade ainda pode ser importada a partir do resumo de exercício. Distância, elevação, FC e demais métricas são preservadas quando retornadas.

Projetos Google em Testing podem receber refresh tokens com vida curta, frequentemente em torno de sete dias. Escala pública pode exigir verificação OAuth e revisão de segurança/configuração do projeto Google; isso é uma etapa externa ao código do StrideBR.

## COROS MCP

Endpoint padrão:

```text
https://mcp.coros.com/mcp
```

O StrideBR não usa `COROS_CLIENT_ID` nem `COROS_CLIENT_SECRET`. O início da conexão consulta Protected Resource Metadata path-aware e Authorization Server Metadata, usa PKCE S256 e negocia o transporte MCP atual (`2026-07-28`). Há fallback controlado para servidores MCP legados que ainda não reconheçam `server/discover`. Quando o authorization server aceita client metadata document, o StrideBR expõe:

```text
/auth/mcp-client-metadata.php
```

Quando discovery exigir Dynamic Client Registration, o registro é feito em runtime e qualquer secret retornado pelo servidor é criptografado com a mesma infraestrutura de integrações.

A sincronização usa as tools oficiais de registros/atividades. FIT é preferido quando disponível e limitado a no máximo 50 downloads por execução; se não houver FIT, o detalhe MCP é usado como fallback. COROS foi intencionalmente excluído do runner periódico para evitar polling agressivo no tier sem webhook.

## Suunto

A integração só é marcada como configurada quando existem Client ID, Client Secret, endpoints HTTPS, segredo de integrações e `SUUNTO_SUBSCRIPTION_KEY`.

Scope atual:

```text
workout
```

Sem aprovação/credenciais a interface mostra o provider como indisponível e não oferece um OAuth quebrado.

## Sincronização e resultado parcial

A API interna de sincronização retorna três contadores:

```text
created
existing
failed
```

A interface apresenta resultado equivalente a “novas / já existentes / falharam”. Uma falha em uma atividade não cancela as demais.

O runner periódico é:

```bash
./scripts/sync_integrations.sh
```

Ele processa Strava, Polar, Google Health e Suunto. COROS permanece manual/oportunista. A execução usa a preferência de sincronização do usuário e não renova tokens em lote: refresh acontece sob demanda por conexão.
