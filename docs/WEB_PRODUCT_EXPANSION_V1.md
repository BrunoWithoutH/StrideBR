# StrideBR Core/Web — Product Expansion V1

Esta rodada atualiza o Web para consumir os domínios avançados já existentes no Core, sem criar engines matemáticas paralelas.

## Activity Detail V3

A Activity permanece numa única experiência com abas condicionais:

- Resumo;
- Gráficos;
- Splits;
- Voltas;
- Zonas;
- Análise.

Mapa, Streams e Analysis carregam de forma independente. Activity antiga, manual, importada, Quick Register ou sem GPS continua abrindo normalmente.

O mapa real usa Leaflet/OpenStreetMap através de `web-map.js`. Streams usam resolução adequada para viewport e preservam gaps. Splits, laps, zones e Analysis vêm dos serviços canônicos do Core.

## Pacer Web

`/user/pacer.php` gerencia Pacer Plans com:

- ritmo constante;
- negative split;
- positive split;
- custom;
- tolerância;
- constraints opcionais de FC;
- preview determinístico;
- vínculo com Workout.

O Web não executa guidance em tempo real; o Mobile continua responsável pelo runtime offline.

## Comparison V2

A comparação usa:

- summary;
- equipamento;
- Activity Streams;
- splits;
- Activity Analysis.

Para Activities compatíveis o Web sobrepõe curvas por distância e compara primeira/segunda metade, variabilidade, final e cardiac drift sem criar um vencedor arbitrário.

Força usa `series_exercicio_atividade`, a fonte histórica canônica.

## Histórico V3

Os filtros persistem em query params e cobrem:

- período;
- modalidade;
- distância;
- duração;
- equipamento;
- origem;
- com/sem rota;
- com/sem HR;
- com/sem Analysis;
- Workout vinculado;
- competição vinculada;
- inclusão nas estatísticas.

`exclude_from_stats` mantém a Activity no histórico, mas a remove de Sport Hub, Progress, totals e trends.

## Structured Endurance Workouts

O Workout existente foi expandido para representar aquecimento, trabalho, recuperação, desaquecimento, intervalos repetidos e targets de pace/speed/HR/RPE/duração/distância.

Planned × actual usa Activity Streams e retorna `null` para blocos que não podem ser correlacionados de forma confiável.

Detalhes: `docs/WEB_ENDURANCE_WORKOUTS.md`.

## Equipment V2

O detalhe de equipamento mostra dados objetivos:

- total de Activities;
- distância;
- duração;
- elevação;
- primeiro uso;
- último uso;
- histórico filtrável;
- limite opcional de quilometragem configurado pelo usuário.

Não há inferência de desgaste ou ganho de performance.

## Competitions V2

O fluxo cobre:

```text
Evento → participação → preparação → Activity → resultado
```

Participação aceita `interessado`, `inscrito`, `participou` e `cancelou`. Resultado pode guardar tempo, distância, colocação geral/categoria, categoria, medalha e observações, além da Activity relacionada.

Não existe ranking calculado novo.

## Routes

Activities com track podem gerar Routes reutilizáveis. Routes têm lista/detail próprios, mapa real, histórico explícito de usos, vínculo com Workout e vínculo opcional com Pacer.

Detalhes: `docs/WEB_ROUTES.md`.

## Home V2

A Home prioriza informação acionável e real:

1. execução/treino de hoje existente;
2. próximo treino e Pacer;
3. próxima competição;
4. Progress dos últimos 28 dias;
5. última Activity com mini mapa quando houver rota;
6. ações rápidas.

Os módulos antigos duplicados de próximo treino e Activity recente foram removidos da composição.

## Mapas e performance

O Web usa um componente centralizado de mapa. Mini mapas são lazy e não instanciam dezenas de mapas interativos fora da viewport.

Gráficos consomem Streams downsampled. Raw não é carregado por padrão.

## Responsividade e acessibilidade

As novas superfícies usam o design system existente, media queries, labels explícitos e summaries textuais. Tabelas e gráficos possuem layouts móveis próprios. Falha de mapa/Analysis não bloqueia o summary principal.

## Privacidade

Nenhuma superfície cria endpoint público novo para rota, GPS, HR ou Analysis. Todas as ações de Routes, Zones, Pacer e Activity detail continuam owner-scoped.

## Testes

A rodada possui suítes estáticas dedicadas para:

- Activity Detail V3;
- Pacer Web;
- Product Expansion C–H.

Também existe uma suíte PostgreSQL de integração para os fluxos de Route, exclude-from-stats, Equipment, Competition e endurance Workout.
