# StrideBR 1.0.0 RC3

A RC3 consolida a Web 1.0 para staging no Dokploy. O foco desta candidata é consolidar o editor de atividades, rotas, compartilhamento e a fundação PWA sem abrir novas áreas de produto.

## Principais alterações

- Inclui a rodada atual de Produto/UX: planejamento semanal, cargas de treino, progresso, dashboard, contexto esportivo e administração de feedback.
- Consolida PWA/offline, GPS e compartilhamento, com ajustes mobile e evidências visuais.
- Prepara Docker/Dokploy com código imutável, uploads persistentes, job de migrations, health/readiness, SMTP e proxy confiável; staging e production separados.

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

As migrations existentes foram preservadas integralmente nesta rodada. Os resultados de fresh/upgrade e registry da RC3 estão no [relatório final](reports/RC3_RELEASE_VALIDATION_2026-09-08.md).

Antes do deploy, execute no ambiente autorizado:

```bash
./scripts/migrate_product.sh status
./scripts/migrate_product.sh apply
./scripts/migrate_product.sh status
```

O status final deve ficar sem migrations pendentes.

## Validação da RC3

Execute:

```bash
./scripts/release_check.sh
```

E, em um ambiente com Docker/PostgreSQL:

```bash
./scripts/release_check.sh --full
```

A promoção para produção/1.0 final exige backup recente, restore em banco separado e o smoke manual descrito em `docs/V1_RELEASE_CHECKLIST.md`. A RC3 é publicada para permitir a homologação em staging.

## Versão

A candidata atual é `v1.0.0-rc.3`. A publicação desta RC exige os checks locais e a auditoria registrados no relatório final. Deploy real e homologação são etapas posteriores de Infra; não criar `v1.0.0` nesta rodada.
