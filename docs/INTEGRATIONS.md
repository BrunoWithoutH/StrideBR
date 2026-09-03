# Integrações do StrideBR

O StrideBR usa uma camada única de conexões para importar atividades de serviços externos sem duplicar regras de persistência. Toda atividade recebida passa pelo normalizador do StrideBR, recebe origem externa e usa a combinação de provedor + ID externo para evitar duplicatas.

## Estado dos provedores

| Provedor | Entrada automática | Saída de treinos | Observação |
| --- | --- | --- | --- |
| Strava | Implementada | Não | OAuth e importação de atividades |
| Polar Flow | Implementada | Não | OAuth, AccessLink e importação de exercícios |
| Fitbit | Implementada | Não | OAuth, atividades e TCX quando disponível |
| Suunto | Implementada | Não | OAuth e Suunto Cloud API `/v2/workouts` |
| Garmin Connect | Preparada | Preparada | Depende do acesso ao Garmin Connect Developer Program para os endpoints e contratos liberados ao projeto |
| Health Connect | Requer app Android | Requer app Android | Ponte para apps Android compatíveis, incluindo Samsung Health quando os dados forem publicados no Health Connect |
| Samsung Health | Via Health Connect | Via app Android | O site não acessa diretamente os dados locais do telefone |
| Apple Health | Requer app iOS | Requer app iOS | HealthKit não é acessível diretamente pelo site |

## Callback OAuth

Cada provedor cloud usa o callback:

```text
https://SEU_DOMINIO/auth/integration-callback.php?provider=PROVEDOR
```

Exemplos de `PROVEDOR`: `strava`, `polar`, `fitbit`, `suunto` e, quando liberado, `garmin`.

## Segredo dos tokens

Defina `STRIDEBR_INTEGRATIONS_SECRET` com um valor aleatório longo e permanente. O StrideBR deriva uma chave AES-256 e armazena access/refresh tokens criptografados com AES-256-GCM.

Não troque esse segredo depois de contas terem sido conectadas sem antes planejar a reconexão, porque tokens existentes deixam de poder ser descriptografados.

## Sincronização automática

O runner é:

```bash
./scripts/sync_integrations.sh
```

Ele processa Strava, Polar, Fitbit e Suunto, respeita a preferência de sincronização do usuário, ignora conexões ainda não configuradas, evita execução concorrente e aplica um intervalo antes de repetir conexões em erro.

Filtros úteis:

```bash
./scripts/sync_integrations.sh --provider=strava
./scripts/sync_integrations.sh --user=ID_DO_USUARIO
./scripts/sync_integrations.sh --limit=100
```

No servidor, execute por cron em um intervalo moderado. Webhooks podem substituir ou complementar o polling em provedores que os disponibilizam, mas não são necessários para o funcionamento inicial.

## Garmin

A interface, armazenamento de tokens, preferências, origem da atividade e capacidades de entrada/saída já estão preparados. O adaptador final de Activity API e Training API não usa endpoints presumidos: ele deve ser concluído com os contratos técnicos fornecidos pela Garmin após aprovação do StrideBR no Garmin Connect Developer Program.

Isso evita depender de endpoints privados do Garmin Connect ou de automações não suportadas.
