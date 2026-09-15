# Activity Detail V3

A tela Web de Activity usa os mesmos domínios canônicos das APIs Mobile: Activity, Route, Activity Streams, Splits, Manual Laps, Zone Profiles e Activity Analysis.

## Estrutura

O detalhe permanece numa única experiência organizada por abas:

- Resumo;
- Gráficos;
- Splits;
- Voltas;
- Zonas;
- Análise.

Abas sem dados não são exibidas. Activity antiga, manual ou Quick Register continua abrindo normalmente.

## Mapa

`public/assets/js/web-map.js` é o componente compartilhado de mapa desta fase. Ele usa Leaflet e `StrideBRBasemaps`, com OpenStreetMap como mapa-base disponível sem chave. A rota geográfica continua vindo de `rotas_atividade`; Activity Streams não duplicam latitude/longitude.

O mapa do detalhe possui polyline, início, fim, fit bounds, zoom e pan. Gaps conhecidos podem quebrar a polyline usando `route_point_index` dos Streams.

## Gráficos

Os gráficos usam `/api/atividade-streams.php`, que chama `activityStreamRead()`.

A carga inicial usa `resolution=medium`, não `raw`. Tempo e distância são eixos selecionáveis. Pace, velocidade, FC, elevação, grade, cadência e potência só aparecem quando disponíveis.

Samples são alinhados, então cursor e tooltip usam uma única posição para todas as métricas. Gaps de dados não são ligados como uma linha contínua.

## Splits e Voltas

Splits são derivados no Core por `activityStreamSplits()`. O Web apenas escolhe a distância: 500 m, 1 km, 5 km ou custom dentro dos limites do serviço.

Voltas vêm de `activityStreamLaps()` e permanecem semanticamente separadas de splits automáticos.

## Zonas

`/user/zonas.php` usa diretamente `zone_profile_service.php` para criar, editar e excluir profiles manuais de frequência cardíaca e pace.

O Web não calcula zonas por idade. Sem profile aplicável, a Activity mostra apenas um CTA para configuração.

## Análise

`/api/atividade-analysis.php` chama `activityAnalysisCompute()`. O Web não possui fórmulas próprias de pacing, decoupling, elevação ou cadência.

Findings são traduzidos em texto factual na apresentação. Best efforts são rotulados como “Melhores trechos desta atividade”, nunca como recordes pessoais.

## Falhas e progressive enhancement

Mapa, Streams e Analysis são carregados separadamente. Uma falha em qualquer uma dessas áreas não impede o resumo da Activity de abrir.
