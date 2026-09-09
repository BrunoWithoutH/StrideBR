# StrideBR

[![Licença: GPL v3](https://img.shields.io/badge/Licen%C3%A7a-GPL%20v3-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4.svg)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-336791.svg)](https://www.postgresql.org/)

<img src="public/assets/img/logos/stridebr-banner.svg" width="100%" alt="StrideBR banner">

**Planeje. Treine. Registre sua evolução.**

StrideBR é uma plataforma esportiva brasileira, livre e open source, para planejar treinos, registrar atividades físicas e acompanhar sua evolução em diferentes modalidades.

- Site: [stridebr.com.br](https://stridebr.com.br)
- Instagram: [@stridebr.app](https://www.instagram.com/stridebr.app/)
- Feito no Brasil
- [GPL-3.0](LICENSE) / open source

## O que é o StrideBR?

O StrideBR reúne o planejamento de treinos, o registro de atividades e o acompanhamento de evolução em um só lugar. Ele é pensado para pessoas que treinam corrida, musculação e outras modalidades, e também para quem organiza rotinas de treino.

### Planeje

- Cronogramas e agenda de treinos
- Sessões e exercícios organizados por modalidade
- Biblioteca de treinos reutilizáveis

### Registre

- Atividades manuais e treino ao vivo
- Gravação com GPS pelo navegador
- Importação de arquivos FIT, TCX e GPX
- Integrações externas, quando configuradas e disponíveis para o provedor

### Acompanhe

- Histórico de atividades e treinos
- Progresso, comparações e metas
- Métricas por esporte e período

### Diferentes esportes

O produto trabalha com modalidades configuráveis. Cada modalidade pode ter seus próprios campos, métricas, unidades e tipos de atividade, sem reduzir a experiência a um único tipo de treino.

## Recursos e documentação

As capacidades abaixo descrevem o produto e sua implementação com mais detalhe:

- [Índice da documentação](docs/README.md)
- [Visão de produto](docs/PRODUCT_VISION.md)
- [Arquitetura](docs/architecture.md)
- [Importação e exportação de atividades](docs/ACTIVITY_IMPORT_EXPORT.md)
- [Integrações](docs/INTEGRATIONS.md)
- [Checklist de release e operação](docs/V1_RELEASE_CHECKLIST.md)
- [Deploy no Dokploy](docs/DEPLOY_DOKPLOY.md)

## Capacidades atuais

- Painel com resumo semanal, próximos treinos, metas e estatísticas
- Cronogramas, agenda em semana/mês/lista e sessões planejadas
- Biblioteca de treinos, exercícios e equipamentos
- Registro manual, treino ao vivo, GPS Web e importação FIT/TCX/GPX
- Histórico, filtros, edição em lote e comparação de atividades
- Progresso por modalidade e métricas configuráveis
- Conta, preferências, sessões, autenticação por senha e Google quando configurado
- Interface responsiva, PWA, tema claro/escuro e PT-BR/EN

## Stack

- PHP 8.4
- Apache 2.4
- PostgreSQL 17
- PDO e Composer
- JavaScript, HTML e CSS
- Docker Compose para desenvolvimento local

## Início rápido com Docker

```bash
cp .env.example .env
docker compose up -d --build
```

Abra [http://localhost:8080](http://localhost:8080). O banco é criado e recebe o schema e os dados de demonstração no primeiro início.

O serviço `migrate` aplica as migrations pendentes antes de iniciar o Apache. As versões aplicadas ficam em `public.stridebr_schema_migrations`; inicializações normais preservam o volume do PostgreSQL e aplicam somente migrations novas.

Para parar o ambiente:

```bash
docker compose down
```

Para recriar banco e volumes locais:

```bash
docker compose down -v
docker compose up -d --build
```

## Configuração

As variáveis de ambiente ficam em `.env`. Veja os nomes e valores de exemplo em [.env.example](.env.example).

Os grupos mais comuns são:

- Banco de dados: `STRIDEBR_DB_HOST`, `STRIDEBR_DB_PORT`, `STRIDEBR_DB_NAME`, `STRIDEBR_DB_USER`, `STRIDEBR_DB_PASSWORD`
- Aplicação: `STRIDEBR_APP_ENV`, `STRIDEBR_APP_URL`
- E-mail: `STRIDEBR_MAIL_TRANSPORT`, `STRIDEBR_SMTP_HOST`, `STRIDEBR_SMTP_PORT`, `STRIDEBR_SMTP_USERNAME`, `STRIDEBR_SMTP_PASSWORD`, `STRIDEBR_MAIL_FROM`
- Google Sign-In: `GOOGLE_OAUTH_ENABLED`, `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, `GOOGLE_OAUTH_REDIRECT_URI`
- Integrações: credenciais específicas de cada provedor

`STRIDEBR_ELEVATION_API_ENABLED=0` desativa a consulta externa de elevação sem impedir o salvamento de rotas. `STRIDEBR_MAPS_ARCGIS_KEY` habilita os mapas opcionais ArcGIS; vazio mantém OpenStreetMap. Consulte [docs/MAPS.md](docs/MAPS.md) para limites e fallback.

As portas padrão do Docker são `localhost:8080` para a aplicação e `localhost:5434` para PostgreSQL. Dentro dos containers, a aplicação acessa o banco em `postgres:5432`.

Nunca versione `.env` com segredos reais.

## Desenvolvimento sem Docker

1. Instale PHP 8.4 ou PHP 8.x compatível, PostgreSQL 17 e Composer.
2. Habilite `pdo_pgsql` e configure um servidor web com `public/` como document root.
3. Copie `.env.example` para `.env` e ajuste a conexão.
4. Execute `composer install`.
5. Crie um banco PostgreSQL e aplique `src/database/stridebr.sql`, `src/database/stridebr_activities_schema.sql`, `src/database/stridebr_seed.sql` e as migrations pendentes com `./scripts/migrate_product.sh`.

## Estrutura do projeto

```text
public/              Páginas, endpoints e arquivos públicos
src/                 Regras da aplicação, layout, i18n e integrações
src/database/        Schema, seeds e migrations
scripts/              Testes e utilitários de desenvolvimento
docs/                 Documentação técnica e de produto
storage/              Uploads e arquivos gerados em ambiente local
```

## Modelo de dados

O banco separa dados de conta, modalidades, atividades, treinos, cronogramas e métricas. A modelagem completa, convenções e scripts de banco estão em [docs/architecture.md](docs/architecture.md).

No planejamento, exercícios pertencem a treinos agendados e têm prescrição padrão ou campos customizados. No registro, modalidades definem modelos e campos; unidades repetidas podem representar tentativas, voltas, intervalos, séries ou outra ocorrência definida pelo modelo.

## Verificações locais

```bash
find public src scripts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find public/assets/js -type f -name '*.js' -print0 | xargs -0 -n1 node --check
./scripts/test_static.sh
```

Use `./scripts/release_check.sh` para a verificação de release e `./scripts/release_check.sh --full` para incluir a suíte isolada de PostgreSQL. Os testes específicos ficam em `scripts/tests/`.

## Segurança e backups

- Consulte o checklist de release para os procedimentos de segurança, backup e restauração.
- Para deploy, siga [docs/DEPLOY_DOKPLOY.md](docs/DEPLOY_DOKPLOY.md).

Para criar um backup local, com as variáveis `STRIDEBR_DB_*` configuradas e ferramentas do PostgreSQL instaladas:

```bash
./scripts/backup_db.sh
```

Teste restaurações em um banco separado:

```bash
STRIDEBR_DB_NAME=stridebr_restore_test ./scripts/restore_db.sh backups/ARQUIVO.dump --yes
```

Senhas usam as APIs de hash do PHP; operações que mudam estado usam CSRF; recursos são autorizados por usuário e consultas usam statements preparados com PDO.

## Idioma, aparência e Google Sign-In

A interface suporta PT-BR e uma localização inicial em inglês. A aparência pode seguir o sistema ou ser definida em claro e escuro nas preferências.

O Google Sign-In usa OAuth 2.0/OpenID Connect no servidor. Configure um cliente Web e defina:

```env
GOOGLE_OAUTH_ENABLED=0
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
GOOGLE_OAUTH_REDIRECT_URI=https://seu-dominio/auth/google-callback.php
```

Consulte [docs/GOOGLE_SIGNIN_SETUP.md](docs/GOOGLE_SIGNIN_SETUP.md), [docs/INTEGRATIONS_SETUP.md](docs/INTEGRATIONS_SETUP.md) e [docs/FINAL_SETUP_CHECKLIST.md](docs/FINAL_SETUP_CHECKLIST.md) antes de habilitar conexões externas.

## Licença

StrideBR é distribuído sob a [GNU General Public License v3.0](LICENSE).
