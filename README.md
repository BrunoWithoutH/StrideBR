# StrideBR

![GitHub repo size](https://img.shields.io/github/repo-size/BrunoWithoutH/StrideBR?style=for-the-badge)
![GitHub license](https://img.shields.io/github/license/BrunoWithoutH/StrideBR?style=for-the-badge)
![GitHub tag](https://img.shields.io/github/v/tag/BrunoWithoutH/StrideBR?style=for-the-badge&label=release)

<img src="public/assets/img/logos/stridebr-banner.svg" width="100%" alt="StrideBR banner">

> Plataforma web para planejar, organizar, registrar e acompanhar atividades físicas.

**Versão atual:** `1.0.0-rc.1`

StrideBR é uma plataforma esportiva flexível construída em torno de modalidades, modelos de atividade e campos configuráveis. Em vez de assumir um único esporte ou formato de treino, o sistema permite registrar desde corrida e ciclismo até musculação, esportes de raquete, modalidades coletivas, lutas e atividades com tentativas, voltas, séries ou intervalos.

O projeto começou com foco em corrida e evoluiu para reunir planejamento de treinos, registro de atividades, acompanhamento de progresso, rotas, GPS Web, eventos, equipamentos e integrações em uma única aplicação.

## Principais recursos

### Atividades

- Registro manual por modalidade e modelo de atividade
- Campos dinâmicos e valores tipados/normalizados
- Unidades repetidas como séries, voltas, tentativas e intervalos
- Distância, duração, ritmo, elevação e outras métricas esportivas
- Registro específico para força, esportes de raquete, coletivos, combate e precisão
- Histórico com filtros, comparação entre atividades e restauração após exclusão
- Importação de atividades em FIT, TCX e GPX
- Exportação em GPX, TCX, JSON e arquivo original quando disponível
- Compartilhamento com cards e controles de privacidade

### GPS e rotas

- Gravação GPS diretamente pelo navegador
- Início rápido de atividade
- Recuperação local/offline
- Voltas manuais e metas durante a gravação
- Revisão e edição antes de salvar
- Rotas com validação de distância
- Elevação e perfil de percurso
- Privacidade configurável para compartilhamento de rotas

### Treinos e planejamento

- Múltiplos cronogramas semanais
- Visualização semanal e agenda mensal
- Treinos planejados com horários
- Suporte a treinos que atravessam a meia-noite
- Biblioteca global e pessoal de exercícios
- Séries, repetições, carga, descanso, blocos e clusters
- Campos personalizados por exercício
- Execução de treino planejado com acompanhamento de progresso
- Compartilhamento e sincronização de cronogramas em modo somente leitura

### Progresso

- Metas por modalidade e métrica
- Prazos personalizados
- Metas contínuas e por dias ativos
- Histórico de conclusão
- Comparação do usuário consigo mesmo
- Comparação A/B entre atividades
- Evolução de exercícios de força
- Estimativa de 1RM
- Calendário de treinamento
- Distribuição por grupos musculares
- Estimativas de gasto energético

### Conta e comunidade

- Cadastro e login
- Verificação de e-mail
- Recuperação de senha
- Google Sign-In
- Perfil e username
- Onboarding
- Preferências de idioma e tema
- Amigos mútuos
- Notificações internas
- Exportação dos dados da conta
- Exclusão da conta
- Controles de privacidade
- Perfis de treinador e atleta com limites de permissão

### Eventos, equipamentos e integrações

- Calendário público de eventos esportivos
- Eventos salvos pelo usuário
- Gestão administrativa de fontes e eventos
- Equipamentos associados às atividades
- Fundação para integrações com Garmin, Strava, Polar, Fitbit, Suunto, Health Connect, Samsung Health e Apple Health
- Importação automática implementada para Strava, Polar, Fitbit e Suunto

### Administração e produto

- Papéis de moderador, administrador e owner
- Gerenciamento de usuários
- Bloqueio e desbloqueio de contas
- Feature flags
- Auditoria
- Canal permanente de feedback
- Fila de moderação
- Métricas internas de produto com opt-out
- Fundação para anúncios em páginas públicas e apoio voluntário, desativada por padrão

## Estado do projeto

A versão `1.0.0-rc.1` é a primeira Release Candidate do StrideBR.

A RC consolida o trabalho realizado após a closed alpha e serve como base para estabilização, correção de bugs, validação de deploy e preparação da versão `1.0.0`.

Ainda estão planejados, entre outros:

- fluxos mais avançados para treinadores;
- descoberta pública mais ampla;
- API pública;
- clientes móveis dedicados;
- integrações adicionais e maior automação de sincronização.

Consulte [`docs/PRODUCT_ROADMAP.md`](docs/PRODUCT_ROADMAP.md) e [`docs/V1_RELEASE_NOTES.md`](docs/V1_RELEASE_NOTES.md).

## Stack

| Tecnologia | Uso |
|---|---|
| PHP 8.x | Backend |
| PostgreSQL | Banco de dados |
| Apache | Servidor web |
| PDO | Acesso ao PostgreSQL |
| JavaScript | Interações no cliente |
| HTML / CSS | Interface |
| Composer | Dependências PHP |
| Docker Compose | Ambiente local |

## Executando localmente

### Requisitos

- Git
- Docker
- Docker Compose

Clone o repositório:

```bash
git clone https://github.com/BrunoWithoutH/StrideBR.git
cd StrideBR
```

Crie o arquivo de ambiente local:

```bash
cp .env.example .env
```

Suba a aplicação:

```bash
docker compose up --build
```

Acesse:

```text
http://localhost:8080
```

Para executar em segundo plano:

```bash
docker compose up -d --build
```

Para parar:

```bash
docker compose down
```

Para recriar completamente o banco de desenvolvimento:

```bash
docker compose down -v
docker compose up --build
```

> Esse comando remove os dados armazenados no volume local do PostgreSQL.

## Banco de dados e migrations

Na primeira inicialização, o ambiente cria o schema base, aplica os seeds e executa as migrations pendentes.

Arquivos base:

```text
src/database/stridebr.sql
src/database/stridebr_activities_schema.sql
src/database/stridebr_seed.sql
```

Migrations atualmente publicadas:

```text
src/database/migrations/
├── 20260815_alpha_readiness.sql
├── 20260815_feedback_anonymous.sql
├── 20260815_fix_cronograma_delete_activity_trigger.sql
├── 20260815_product_foundation.sql
└── 20260903_v1_rc.sql
```

As versões aplicadas são registradas em:

```text
public.stridebr_schema_migrations
```

O runner executa somente migrations ainda não registradas e suporta execução idempotente.

Documentação:

- [`docs/MIGRATIONS_CLI.md`](docs/MIGRATIONS_CLI.md)
- [`docs/MIGRATIONS_PGADMIN.md`](docs/MIGRATIONS_PGADMIN.md)
- [`docs/architecture.md`](docs/architecture.md)

## Variáveis de ambiente

O StrideBR usa variáveis de ambiente para banco, aplicação e serviços externos.

Exemplo:

```env
STRIDEBR_APP_ENV=development

STRIDEBR_DB_HOST=postgres
STRIDEBR_DB_PORT=5432
STRIDEBR_DB_NAME=stridebr
STRIDEBR_DB_USER=stridebr
STRIDEBR_DB_PASSWORD=

STRIDEBR_ELEVATION_API_ENABLED=0
```

Use `.env.example` como referência.

Arquivos `.env` reais não devem ser enviados ao Git.

## Integrações

As integrações externas são configuradas por variáveis de ambiente e permanecem desacopladas do funcionamento principal da aplicação.

Documentação:

- [`docs/INTEGRATIONS.md`](docs/INTEGRATIONS.md)
- [`docs/INTEGRATIONS_SETUP.md`](docs/INTEGRATIONS_SETUP.md)
- [`docs/GOOGLE_SIGNIN_SETUP.md`](docs/GOOGLE_SIGNIN_SETUP.md)

O Google Sign-In usa OAuth 2.0 / OpenID Connect com authorization code flow no servidor.

## Importação e exportação

O StrideBR aceita atividades em:

- FIT
- TCX
- GPX

O fluxo possui preview, detecção de duplicatas e suporte a percurso.

As atividades podem ser exportadas em:

- GPX
- TCX
- JSON
- arquivo original, quando preservado

Consulte [`docs/ACTIVITY_IMPORT_EXPORT.md`](docs/ACTIVITY_IMPORT_EXPORT.md).

## GPS Web

O gravador GPS funciona diretamente no navegador e foi desenvolvido como alternativa web para registrar atividades externas sem exigir aplicativo nativo.

Por depender das APIs e restrições de cada navegador/sistema operacional, existem limitações específicas de execução em background.

Consulte [`docs/GPS_WEB_V1.md`](docs/GPS_WEB_V1.md).

## Eventos

O StrideBR possui calendário de eventos esportivos com administração de fontes, imagens e eventos salvos.

Consulte [`docs/EVENTS_IMPORT_GUIDE.md`](docs/EVENTS_IMPORT_GUIDE.md).

## Estrutura do projeto

```text
StrideBR/
├── docs/
├── public/
│   ├── admin/
│   ├── api/
│   ├── assets/
│   ├── auth/
│   ├── errors/
│   ├── pages/
│   ├── uploads/
│   └── user/
├── scripts/
│   └── tests/
├── src/
│   ├── config/
│   ├── database/
│   │   └── migrations/
│   ├── function/
│   ├── i18n/
│   ├── includes/
│   └── layout/
├── compose.yaml
├── Dockerfile
├── composer.json
└── README.md
```

## Testes

A suíte completa pode ser executada com:

```bash
./scripts/test_all.sh
```

Os testes incluem verificações de:

- sintaxe PHP;
- sintaxe JavaScript;
- scripts shell;
- migrations;
- autenticação;
- atividades;
- rotas;
- cronogramas;
- sessões de treino;
- amizades;
- treinador;
- permissões;
- GPS Web;
- integrações;
- segurança;
- interface e consistência estática.

Para validar uma instalação limpa do banco:

```bash
./scripts/tests/test_migrations_clean.sh
```

Para verificações de release:

```bash
./scripts/release_check.sh
```

E incluindo a suíte isolada de PostgreSQL:

```bash
./scripts/release_check.sh --full
```

## Backup e restore

Com as variáveis `STRIDEBR_DB_*` configuradas:

```bash
./scripts/backup_db.sh
```

Para testar uma restauração em banco separado:

```bash
STRIDEBR_DB_NAME=stridebr_restore_test ./scripts/restore_db.sh backups/ARQUIVO.dump --yes
```

Dumps de banco são ignorados pelo Git.

## Segurança

Entre as proteções adotadas pelo projeto:

- hash de senha pelas APIs nativas do PHP;
- sessão no servidor;
- proteção CSRF em operações mutáveis;
- validação de propriedade dos recursos;
- queries parametrizadas com PDO;
- segredos via variáveis de ambiente;
- controles de permissão por papel e relacionamento.

Falhas de segurança devem ser reportadas conforme [`SECURITY.md`](SECURITY.md).

## Documentação

Alguns pontos de entrada:

- [`docs/architecture.md`](docs/architecture.md) — arquitetura e regras do produto
- [`docs/ACTIVITIES_V2.md`](docs/ACTIVITIES_V2.md) — sistema de atividades
- [`docs/ACTIVITY_SHARING_V2.md`](docs/ACTIVITY_SHARING_V2.md) — compartilhamento
- [`docs/ACTIVITY_IMPORT_EXPORT.md`](docs/ACTIVITY_IMPORT_EXPORT.md) — importação e exportação
- [`docs/GPS_WEB_V1.md`](docs/GPS_WEB_V1.md) — GPS Web
- [`docs/INTEGRATIONS.md`](docs/INTEGRATIONS.md) — integrações
- [`docs/PERFORMANCE.md`](docs/PERFORMANCE.md) — performance
- [`docs/MONETIZATION.md`](docs/MONETIZATION.md) — monetização
- [`docs/PRODUCT_ROADMAP.md`](docs/PRODUCT_ROADMAP.md) — roadmap
- [`docs/V1_RELEASE_NOTES.md`](docs/V1_RELEASE_NOTES.md) — notas da RC/1.0

## Licença

StrideBR é distribuído sob a **GNU General Public License v3.0**.

Consulte [`LICENSE`](LICENSE).
