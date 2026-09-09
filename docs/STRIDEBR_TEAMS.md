# StrideBR Teams

O StrideBR Teams possui repositório próprio: [BrunoWithoutH/StrideBR-Teams](https://github.com/BrunoWithoutH/StrideBR-Teams). Esse repositório é a fonte canônica para produto, domínio, organizações, equipes, memberships, papéis institucionais, billing, ownership de dados, UX, arquitetura, MVP 2027 e integração institucional Core ↔ Teams.

## Relação com o Core

O StrideBR/Core em [stridebr.com.br](https://stridebr.com.br) continua o produto individual gratuito e open source. O Teams é a camada institucional paga do ecossistema, desenvolvida e publicada de forma independente; seu domínio planejado é `teams.stridebr.com.br`.

Há uma única identidade StrideBR. Uma pessoa não possui conta, senha, perfil ou cópia de usuário Teams separados: ela pode acessar o Core e, quando autorizada, ter memberships, papéis e entitlements no contexto Teams. A implementação física atual da identidade está no Core, mas o mecanismo técnico futuro de autenticação/SSO entre produtos permanece aberto.

O Trainer individual do Core continua gratuito. Vínculo treinador-atleta, acompanhamento conforme permissões, prescrição, compartilhamento, planejamento individual e colaboração entre usuários comuns não são recursos exclusivos do Teams nem paywall.

Core e Teams têm repositórios, roadmaps, releases e deploys independentes. Identidade compartilhada não determina banco, storage ou sessão compartilhados. A integração futura deverá usar contratos explícitos e não pressupõe acesso direto do Teams às tabelas internas do Core.

## Contexto histórico

Antes da criação do repositório próprio, a especificação do Teams era mantida neste arquivo. Essa especificação histórica foi substituída em setembro de 2026 pela documentação canônica de StrideBR-Teams. O Git preserva a versão anterior; este documento permanece somente como ponte e referência de ecossistema.

O piloto desejado para 2027 é IFFar — Campus Frederico Westphalen. Escopo, requisitos e decisões do piloto são mantidos no repositório Teams.
