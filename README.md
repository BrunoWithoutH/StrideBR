# StrideBR

![GitHub repo size](https://img.shields.io/github/repo-size/BrunoWithoutH/StrideBR?style=for-the-badge)
![GitHub license](https://img.shields.io/github/license/BrunoWithoutH/StrideBR?style=for-the-badge)

<img src="public/assets/img/logos/stridebr-banner.svg" width="100%" alt="StrideBR banner">

> **StrideBR** is a flexible platform for planning, organizing and recording physical activities.

StrideBR is built around configurable modalities instead of assuming one fixed sport. The project combines weekly training planning, a reusable exercise library and a dynamic activity-recording engine in one web application.

## Current capabilities

- User signup, authentication and profile settings
- Multiple independent weekly training plans
- Calendar-style week and agenda views
- Planned workouts with start/end times, including workouts that cross midnight
- Exercise library with global and personal exercises
- Exercise categories and modality associations
- Workout prescriptions with sets, repetitions, load, rest, block and cluster
- Custom exercise-prescription columns
- Dynamic activity modalities, models and fields
- Repeated activity units such as attempts, laps, intervals or sets
- Typed and normalized measurement values
- Global stopwatch, timer and set-counter tools
- Workout execution from a scheduled workout, with set/exercise progress and activity creation
- Profiles, usernames, onboarding and privacy controls
- Mutual friends, snapshot sharing and read-only synchronized schedules
- Exercise image/video references by URL
- Moderator/admin/owner roles, feature flags and audit logs
- Permanent feedback channel with moderation queue
- Versioned legal acceptance, public registration, optional invite mode, email verification and password recovery
- Admin user management with block/unblock/edit/delete controls
- Route drawing for compatible activities, backend distance validation, terrain-elevation estimates and branded activity-share cards
- Rich goals with sport filters, custom deadlines, active-day targets and completion history
- Release-ready goals with continuous targets, email verification and password recovery
- Public sports-event calendar with admin-managed sources, images and saved events
- Self-service account data export and account deletion
- Contextual Home, self-comparison progress and A/B activity comparison
- Internal notifications, trainer overview with permission boundaries and product analytics opt-out
- Reversible activity deletion and per-account/per-activity route-sharing privacy defaults
- Best-effort GPS Web recording with quick start, offline/local recovery, goals, manual laps and editable review
- Connected-service foundation for Garmin, Strava, Polar, Fitbit, Suunto, Health Connect, Samsung Health and Apple Health, with automatic import currently implemented for Strava, Polar, Fitbit and Suunto
- Sport-specific progress hub with strength load/repetition logging, exercise progression, estimated 1RM, training calendar and muscle-distribution summaries
- Category-first sport picker with common sports first and an expandable full catalog
- Sport-aware calorie estimates using historical weight, objective activity data and device-provided calories when available
- Athletics attempts/marks and sport-specific session fields for racket, team, combat and precision sports
- Optional donations and public-page advertising foundation, disabled by default and isolated from authenticated athlete data

Deeper analysis, richer trainer workflows, broader public discovery, API and dedicated mobile clients remain planned features.

Product vision is documented in [`docs/PRODUCT_VISION.md`](docs/PRODUCT_VISION.md), the roadmap in [`docs/PRODUCT_ROADMAP.md`](docs/PRODUCT_ROADMAP.md), and the planned Teams pillar in [`docs/STRIDEBR_TEAMS.md`](docs/STRIDEBR_TEAMS.md). Current architecture and domain rules remain in [`docs/architecture.md`](docs/architecture.md). A documentation index is available at [`docs/README.md`](docs/README.md). Deployment notes are in [`docs/DEPLOY_DOKPLOY.md`](docs/DEPLOY_DOKPLOY.md).

The sports-event admin/import workflow is documented in [`docs/EVENTS_IMPORT_GUIDE.md`](docs/EVENTS_IMPORT_GUIDE.md).

Connected services are documented in [`docs/INTEGRATIONS.md`](docs/INTEGRATIONS.md). The server/provider actions still required before production are collected in [`docs/FINAL_SETUP_CHECKLIST.md`](docs/FINAL_SETUP_CHECKLIST.md).

The GPS Web recorder and its browser limitations are documented in [`docs/GPS_WEB_V1.md`](docs/GPS_WEB_V1.md).

### Importação e exportação de atividades

O StrideBR importa atividades de relógios e outros serviços em **FIT, TCX e GPX**, com preview, detecção de percursos e duplicatas. Atividades podem ser exportadas em GPX, TCX, JSON e, quando preservado, no arquivo original. Consulte `docs/ACTIVITY_IMPORT_EXPORT.md`.


## Stack

| Technology | Purpose |
|---|---|
| PHP 8.4 | Application backend |
| Apache 2.4 | Web server |
| PostgreSQL 17 | Database |
| PDO | Database access |
| Composer | PHP dependency management |
| JavaScript | Client-side interactions |
| HTML / CSS | User interface |
| Docker Compose | Local development environment |

## Quick start with Docker

### Requirements

- Git
- Docker with Docker Compose

Clone the repository:

```bash
git clone https://github.com/BrunoWithoutH/StrideBR.git
cd StrideBR
```

Optionally create a local environment file:

```bash
cp .env.example .env
```

Start the application and PostgreSQL:

```bash
docker compose up --build
```

Open:

```text
http://localhost:8080
```

The first database startup creates the base schema and seed data. Then the
`migrate` service applies every pending migration before Apache starts:

```text
src/database/stridebr.sql
src/database/stridebr_activities_schema.sql
src/database/stridebr_seed.sql
src/database/migrations/20260815_alpha_readiness.sql
src/database/migrations/20260815_feedback_anonymous.sql
src/database/migrations/20260815_fix_cronograma_delete_activity_trigger.sql
src/database/migrations/20260815_product_foundation.sql
src/database/migrations/20260903_v1_rc.sql
```

Applied versions are stored in `public.stridebr_schema_migrations`. Normal starts
preserve the PostgreSQL volume and apply only new migrations:

```bash
docker compose up -d --build
```

To recreate a development database from scratch (this deletes local data):

```bash
docker compose down -v
docker compose up --build
```

Run the application in the background:

```bash
docker compose up -d --build
```

Check services:

```bash
docker compose ps
```

Open a shell in the application container:

```bash
docker compose exec app bash
```

Open PostgreSQL:

```bash
docker compose exec postgres psql -U stridebr -d stridebr
```

Stop the environment:

```bash
docker compose down
```

## Environment variables

The application reads these database variables:

```text
STRIDEBR_DB_HOST
STRIDEBR_DB_PORT
STRIDEBR_DB_NAME
STRIDEBR_DB_USER
STRIDEBR_DB_PASSWORD
STRIDEBR_ELEVATION_API_ENABLED
STRIDEBR_MAPS_ARCGIS_KEY
```

`STRIDEBR_ELEVATION_API_ENABLED=0` disables external elevation lookup without disabling route saving, which is useful for integration tests and temporary API outages.

`STRIDEBR_MAPS_ARCGIS_KEY` enables the optional ArcGIS satellite and terrain basemaps in manual route editors. Keep it empty to use OpenStreetMap only. See `docs/MAPS.md` for provider, restriction and fallback details.

Application error visibility can be controlled with:

```text
STRIDEBR_APP_ENV=development
STRIDEBR_APP_ENV=production
```

Never commit production credentials. `.env` files are ignored by Git; `.env.example` contains development-only defaults.

The default Docker development ports are:

```text
Web:        localhost:8080
PostgreSQL: localhost:5434
```

The application container connects to PostgreSQL internally through `postgres:5432`.

## Manual setup

A manual environment needs:

- PHP 8.4 or compatible PHP 8.x
- `pdo_pgsql`
- PostgreSQL 17 or compatible supported version
- Composer
- a web server configured with `public/` as its document root

Install dependencies:

```bash
composer install
```

Create a PostgreSQL database and execute the SQL files in the order shown above. Then provide the database connection through environment variables.

## Project structure

```text
StrideBR/
├── docs/
│   └── architecture.md
├── public/
│   ├── assets/
│   ├── function/
│   ├── pages/
│   ├── user/
│   ├── calendario.php
│   ├── home.php
│   ├── index.php
│   ├── login.php
│   └── signup.php
├── src/
│   ├── config/
│   ├── database/
│   ├── function/
│   ├── includes/
│   └── layout/
├── Dockerfile
├── compose.yaml
├── composer.json
└── README.md
```

## Database model

The planning side is centered on:

```text
user
└── cronograms
    └── planned workouts
        └── exercise occurrences
            ├── standard prescription fields
            └── custom prescription fields
```

The activity-recording side is centered on:

```text
modality
└── activity model
    └── fields

user
└── activity record
    └── activity units
        └── typed values
```

A unit is intentionally generic and can represent an attempt, lap, interval, set, throw, descent or another model-defined occurrence.

See [`docs/architecture.md`](docs/architecture.md) for the complete design and development boundaries.

## Development checks

Lint every project PHP file:

```bash
find public src scripts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Check JavaScript syntax:

```bash
find public/assets/js -type f -name '*.js' -print0 | xargs -0 -n1 node --check
```

Run the pre-release checks with:

```bash
./scripts/release_check.sh
```

To include the isolated PostgreSQL integration suite:

```bash
./scripts/release_check.sh --full
```

The full runner creates a separate PostgreSQL test database, applies all migrations from scratch, verifies the migration registry/idempotent second run and executes integration suites without touching the normal development database. Static/unit checks remain available directly with `./scripts/test_static.sh`.

The release checklist is in [`docs/V1_RELEASE_CHECKLIST.md`](docs/V1_RELEASE_CHECKLIST.md).

## Database backup

With the `STRIDEBR_DB_*` environment variables exported and PostgreSQL client tools installed:

```bash
./scripts/backup_db.sh
```

Restore tests must use a separate database:

```bash
STRIDEBR_DB_NAME=stridebr_restore_test ./scripts/restore_db.sh backups/ARQUIVO.dump --yes
```

Dump files are ignored by Git.

## Security

- Passwords use PHP password hashing APIs.
- Authenticated identity comes from the server-side session.
- State-changing browser operations use CSRF protection.
- User-owned resources are checked against the authenticated user.
- SQL input is handled through PDO prepared statements.
- Database secrets come from environment variables.

If a credential has ever been committed to a public Git history, rotating it is required even after removing it from the current files.

## License

See [LICENSE](LICENSE).

## Interface language, appearance and Google sign-in

The interface supports Portuguese (Brazil) and an initial English localization. Appearance can follow the operating system or be forced to Light or Dark. Signed-in users can save both choices under Profile and preferences; the authentication pages also expose quick language and appearance controls.

Google sign-in uses a server-side OAuth 2.0 / OpenID Connect authorization-code flow. Configure a **Web application** OAuth client and set:

```env
GOOGLE_OAUTH_ENABLED=0
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
GOOGLE_OAUTH_REDIRECT_URI=https://your-host/auth/google-callback.php
```

Apply `src/database/migrations/20260903_v1_rc.sql` before enabling Google sign-in. Set `GOOGLE_OAUTH_ENABLED=1` only when you want the feature active. With `GOOGLE_OAUTH_ENABLED=0` (the default), the Google button stays hidden and OAuth entry is blocked even if the credentials remain configured; email/password authentication continues normally.

## Configuração de ambiente e Conexões

Antes de habilitar integrações externas, rode `./scripts/setup_env.sh` (local) ou `./scripts/setup_env.sh --production --url https://seu-dominio` (arquivo de configuração, antes de entregar para Infra). Veja `docs/INTEGRATIONS_SETUP.md` e `docs/FINAL_SETUP_CHECKLIST.md`.
