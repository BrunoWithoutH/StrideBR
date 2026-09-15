# Roadmap do backend para StrideBR App

## Pronto para iniciar o aplicativo

- API `/api/v1` JSON-only, respostas e erros consistentes.
- Sessões mobile opacas por dispositivo, rotação de refresh e revogação.
- Identidade única com o Core/Web e futura compatibilidade com Teams.
- `me`, histórico/detalhe de atividades e publicação GPS idempotente com ownership e paginação.
- Treinos nativos v1: calendário por intervalo, detalhe, planejamento pessoal, biblioteca e vínculo com Activity.
- Workout Session v1: execução Bearer compartilhando a engine Web, séries/reps/carga, histórico, quick register e finalização em Activity canônica.
- Mobile Training Platform API v1: calendário, editor estrutural, templates, sessões, séries e vínculo Workout ↔ Activity.
- Mobile Progress Platform API v1: panorama, séries temporais, cardio, força, exercícios, calendário e aderência ao planejamento.
- Activity Streams + Advanced Activity Analysis v1: timeline alinhada, downsampling, splits, manual laps, zones, análise determinística, import/backfill e capabilities leves no detail.
- Stride Pacer Platform v1: planos determinísticos, geração even/negative/custom, evaluator de referência, hysteresis e integração opcional com Workouts para runtime offline no Mobile.

## Próximas entregas do Core

- Edição/remoção de atividades e contratos de upload GPX/TCX/FIT. A publicação de
  gravação GPS do app já faz parte da API v1.
- Perfil/preferências/privacidade, equipamentos, recordes adicionais e status
  seguro de integrações.
- Reset/cadastro mobile compatíveis com verificação de e-mail e proteção antiabuso.
- OAuth mobile com browser externo/deep links, rate limits por rota e observabilidade.

Eventos esportivos não possuem ainda um domínio API público completo e não são
prometidos pela v1 inicial. Graduação, benchmarks e repertório continuam fora do contrato mobile atual; o Core não publica contratos fictícios para recursos ainda não expostos pela API.

## Mobile Training Platform API v1

Backend consolidado para Cronograma v2, Editor de Academia v1 e Workout Session v1: calendário por range, CRUD pessoal, recorrência suportada pelo Core, templates, catálogo/exercícios, editor estrutural transacional, sessão ativa, histórico, quick register, Activity/GPS linkage e capabilities. Contrato canônico: `docs/MOBILE_TRAINING_API.md`.

## Mobile Progress Platform API v1

Backend analítico para Activities canônicas e planejamento: overview, comparação com período anterior, timeseries day/week/month, distribuição por modalidade, calendário/heatmap, corrida/caminhada/trilha, ciclismo, força, séries/reps/carga, evolução por exercício e aderência planejado × realizado. Contrato canônico: `docs/MOBILE_PROGRESS_API.md`.
## Activity Streams + Analysis + Pacer v1

Fundação Core concluída para timeline esportiva por Activity, leitura por tempo/distância, downsampling LTTB, splits automáticos, manual laps, perfis manuais de HR/pace zones, Activity Analysis versionada e Stride Pacer determinístico. Contratos canônicos: `docs/MOBILE_ACTIVITY_STREAMS_API.md`, `docs/ACTIVITY_ANALYSIS.md` e `docs/PACER.md`.

Wear recorder, Bluetooth HR no Android, áudio/haptic live coach, integrações Garmin e course-aware pace adjustment continuam fora do Core v1; o Android futuro executa as regras de Pacer offline usando o plano fornecido pelo Core.
