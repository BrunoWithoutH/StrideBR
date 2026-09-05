# StrideBR 1.0.0 RC2

A RC2 fecha a Web 1.0 para smoke real. O foco desta candidata é consolidar o editor de atividades, rotas, compartilhamento e a fundação PWA sem abrir novas áreas de produto.

## Principais alterações

- Registrar e Editar atividade compartilham a mesma base visual e de componentes, incluindo Trechos, métricas opcionais, duração e rotas.
- Duração suporta precisão opcional em milissegundos de forma global para a atividade e seus Trechos, preservando valores como `0.005` e `12.438`.
- Visibilidade da atividade fica explícita no formulário; Privacidade da rota continua sendo uma configuração separada.
- Editor de rota usa workspace amplo/fullscreen, com rota Livre fechável, Circuito/voltas, Undo, Ruas e camadas do editor de rota já suportadas pelo produto.
- Fundação PWA inclui manifest, standalone, safe areas, Service Worker conservador, Wake Lock quando disponível e bloqueio de controles durante GPS.
- Compartilhamento final da Web 1.0 separa Escopo, Formato e Composição. Cards de atividade suportam Story, Retrato e Quadrado; Cards de sessão são Story nesta versão.
- Cards de atividade têm composições Padrão e Compacto. Padrão combina Rota/Modalidade/Nenhum com título visível ou oculto; Compacto não usa título nem fundo Mapa.
- Sessões com vários Trechos abrem direto no editor em Sessão e podem alternar para Um trecho ou Vários trechos sem wizard anterior.
- Composições de sessão: Visão geral, Por trecho, Comparação, Destaque e Sequência em Percursos; Resumo, Lista, Sequência, Destaque, Minimal e Compacto em Resumo.
- Comparação usa Tempo para Trechos de distância equivalente e métricas normalizadas, como ritmo/velocidade, quando as distâncias diferem. Valores subsegundo são comparados sem arredondamento prévio.
- Preview e export usam a mesma definição de layout, anchors, geometria, cores e métricas; a exportação apenas trabalha em resolução física maior.

## Migrations

A consolidação da RC1 permanece em:

```text
20260903_v1_rc.sql
```

A única migration pós-RC1 presente no estado recebido é:

```text
20260903_z_activity_duration_precision_ms.sql
```

Ela altera `unidades_atividade.duracao_segundos` para `NUMERIC(14,3)`, preserva `NULL`, mantém a restrição de valor não negativo e permite precisão de três casas decimais.

A migration não foi renomeada/squashada nesta candidata porque o ambiente de validação disponível não possui PostgreSQL/Docker nem um registry compartilhado acessível para confirmar que o nome intermediário nunca foi aplicado. Migrations já aplicadas não podem ser renomeadas retroativamente.

Antes do deploy, execute no ambiente autorizado:

```bash
./scripts/migrate_product.sh status
./scripts/migrate_product.sh apply
./scripts/migrate_product.sh status
```

O status final deve ficar sem migrations pendentes.

## Validação da RC2

Execute:

```bash
./scripts/release_check.sh
```

E, em um ambiente com Docker/PostgreSQL:

```bash
./scripts/release_check.sh --full
```

A publicação também exige backup recente, restore em banco separado e o smoke manual descrito em `docs/V1_RELEASE_CHECKLIST.md`.

## Versão

A base está preparada para `v1.0.0-rc.2`, mas a tag só deve ser criada depois do smoke real aprovado. A mesma base pode virar `v1.0.0` posteriormente se não houver bloqueadores e os checks forem repetidos.
