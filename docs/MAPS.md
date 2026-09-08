# Mapas e basemaps

Os mapas interativos continuam usando Leaflet. O StrideBR separa o fundo cartográfico das rotas, markers e demais overlays da aplicação.

## Basemaps

- `Mapa`: OpenStreetMap.
- `Satélite`: ArcGIS World Imagery por tiles do serviço oficial `World_Imagery`.
- `Relevo`: ArcGIS World Hillshade por tiles do serviço oficial `Elevation/World_Hillshade`.

Satélite e Relevo são opcionais. Sem configuração ArcGIS, o editor continua funcionando com OpenStreetMap e as opções ArcGIS ficam indisponíveis.

## Chave ArcGIS

Configure apenas a chave destinada ao frontend:

```text
STRIDEBR_MAPS_ARCGIS_KEY=
```

A aplicação renderiza essa chave intencionalmente no browser porque os tiles são requisitados diretamente pelo Leaflet. Ela não deve reutilizar credenciais administrativas, OAuth secrets, tokens de usuário ou qualquer segredo de backend.

Crie uma credencial exclusiva para os mapas e limite-a ao acesso de basemaps necessário para World Imagery e World Hillshade. Restrinja os referrers aos ambientes que realmente usarão a chave, por exemplo:

```text
http://localhost:8080
https://stridebr.com.br
https://staging.stridebr.com.br
https://stride.com.br
```

Use somente os hosts efetivamente ativos. Ambientes diferentes podem usar chaves diferentes para facilitar rotação, restrição e acompanhamento de uso.

## Falhas e fallback

A ausência da chave nunca bloqueia o editor. Se uma camada ArcGIS falhar durante o carregamento de tiles, o controle volta para `Mapa` e preserva coordenadas, zoom, centro, circuito, markers e estado de edição.

A preferência do editor é salva apenas no browser em `localStorage` com a chave `stridebr.map.basemap`. Mapas de detalhe, gravação GPS e o renderer do share card não herdam essa preferência.

## Atribuição

Cada tile layer mantém a atribuição do respectivo fornecedor no controle de attribution do Leaflet. A atribuição cartográfica não deve ser removida. O texto de Open-Meteo / Copernicus DEM exibido no editor refere-se somente ao cálculo de elevação da rota, não ao basemap.
