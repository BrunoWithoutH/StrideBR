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
- Basic training timer and counter tools

Route drawing, statistics, records, community publishing, events, API and mobile clients are planned features.

The architecture and product rules are documented in [`docs/architecture.md`](docs/architecture.md).

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

The first database startup automatically executes, in order:

```text
src/database/stridebr.sql
src/database/stridebr_activities_schema.sql
src/database/stridebr_seed.sql
```

Database initialization scripts run only when the PostgreSQL data volume is empty. To recreate a development database from scratch:

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
```

Application error visibility can be controlled with:

```text
STRIDEBR_APP_ENV=development
STRIDEBR_APP_ENV=production
```

Never commit production credentials. `.env` files are ignored by Git; `.env.example` contains development-only defaults.

The default Docker development ports are:

```text
Web:        localhost:8080
PostgreSQL: localhost:5433
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

Create a PostgreSQL database and execute the three SQL files from `src/database/` in the order shown above. Then provide the database connection through environment variables.

## Project structure

```text
StrideBR/
├── .github/
│   └── copilot-instructions.md
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

When Docker is available, a clean database initialization is the preferred integration check:

```bash
docker compose down -v
docker compose up --build
```

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
