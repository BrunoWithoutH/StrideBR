# Roadmap do backend para StrideBR App

## Pronto para iniciar o aplicativo

- API `/api/v1` JSON-only, respostas e erros consistentes.
- Sessões mobile opacas por dispositivo, rotação de refresh e revogação.
- Identidade única com o Core/Web e futura compatibilidade com Teams.
- `me` e histórico/detalhe de atividades com ownership e paginação.

## Próximas entregas do Core

- Criação, edição, remoção e idempotência persistente de atividades; contrato de
  upload GPX/TCX/FIT e envio de GPS/streams do app.
- Treinos, biblioteca, exercícios, agenda e cronogramas.
- Perfil/preferências/privacidade, equipamentos, metas/progresso/recordes e status
  seguro de integrações.
- Reset/cadastro mobile compatíveis com verificação de e-mail e proteção antiabuso.
- OAuth mobile com browser externo/deep links, rate limits por rota e observabilidade.

Eventos esportivos não possuem ainda um domínio API público completo e não são
prometidos pela v1 inicial. Graduação, benchmarks e repertório também continuam
no roadmap de produto, não como contratos fictícios da API.
