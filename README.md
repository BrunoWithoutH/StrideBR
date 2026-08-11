# StrideBR

![GitHub repo size](https://img.shields.io/github/repo-size/BrunoWithoutH/StrideBR?style=for-the-badge)
![GitHub forks](https://img.shields.io/github/forks/BrunoWithoutH/StrideBR?style=for-the-badge)
![Bitbucket open issues](https://img.shields.io/bitbucket/issues/BrunoWithoutH/StrideBR?style=for-the-badge)
![Bitbucket open pull requests](https://img.shields.io/bitbucket/pr-raw/BrunoWithoutH/StrideBR?style=for-the-badge)  

<img src="public/assets/img/logos/stridebr-banner.svg" width="100%" style="max-width:600px;">

> **StrideBR** is a platform built to help athletes and sports enthusiasts track, organize and improve their training over time.


## Features

Current and upcoming features include:

- [x] Physical activity tracking
- [x] Weekly training schedule
- [x] Workout tools
- [ ] Sports event calendar

## Stack

The project is intentionally kept simple and currently uses:

- **PHP 8.5** — application/backend
- **Apache 2.4** — web server
- **PostgreSQL 18** — database
- **Composer** — PHP dependency management
- **NanoID** — ID generation
- **Docker Compose** — development environment
- **JavaScript** — frontend interactions
- **HTML/CSS** — frontend

There is **no Node.js runtime required** by the project. The JavaScript files in `public/assets/js/` are plain browser-side JavaScript.

## Requirements

The recommended environment is Linux with:

- Git
- Docker
- Docker Compose

The project is designed to run entirely through Docker. You do **not** need to install PHP, Apache, PostgreSQL or Composer directly on the host.

> Other operating systems may work with Docker Desktop, but Linux is the primary development environment.

## Quick Start

Clone the repository:

```bash
git clone https://github.com/BrunoWithoutH/StrideBR.git
cd StrideBR
```

Start the development environment:

```bash
docker compose up -d --build
```

The first startup creates the application and PostgreSQL containers and initializes the database volume.

After the containers are running, open:

```text
http://localhost
```

Check the containers with:

```bash
docker compose ps
```

View application logs with:

```bash
docker compose logs -f app
```

View PostgreSQL logs with:

```bash
docker compose logs -f postgres
```

Stop the environment:

```bash
docker compose down
```

To stop the containers without removing them:

```bash
docker compose stop
```

To start them again:

```bash
docker compose start
```

## Project Structure

The important directories are:

```text
StrideBR/
├── public/                  # Public web root served by Apache
│   ├── assets/              # CSS, JavaScript, images and audio
│   ├── function/            # Public PHP endpoints
│   ├── pages/               # Public pages
│   ├── user/                # Authenticated user pages
│   ├── index.php
│   ├── login.php
│   └── signup.php
│
├── src/
│   ├── config/              # Database configuration
│   ├── database/            # Database schemas and SQL
│   ├── function/            # Application functions
│   ├── includes/            # Shared includes
│   └── layout/              # Shared page layout
│
├── vendor/                  # Composer dependencies
├── composer.json            # PHP dependencies
├── package.json             # Frontend package metadata
├── compose.yaml             # Docker development environment
└── README.md
```

## Docker Environment

The development environment is divided into two containers:

```text
┌──────────────────────────────┐
│          StrideBR            │
│                              │
│  Apache + PHP + Composer     │
│  http://localhost            │
└──────────────┬───────────────┘
               │
               │ PostgreSQL
               ▼
┌──────────────────────────────┐
│       stridebr-postgres      │
│                              │
│       PostgreSQL 18          │
│       Database: stridebr     │
└──────────────────────────────┘
```

The two containers communicate through the Docker Compose network.

The application should connect to PostgreSQL using the **service name**:

```text
postgres
```

It should **not** use `localhost` as the PostgreSQL host from inside the PHP container.

### Database configuration

The default development database is:

| Setting | Value |
|---|---|
| Host | `postgres` |
| Port | `5432` |
| Database | `stridebr` |
| User | `stridebr` |
| Password | `stridebr_dev` |

The PostgreSQL data is stored in a Docker named volume:

```text
stridebr-postgres-data
```

This means the database survives container recreation.

> The database name, PostgreSQL service name and credentials are part of the current development configuration. If these names are changed, update the PHP database configuration and any other tools/scripts that depend on them.

## Database

The PostgreSQL container is intentionally kept separate from the application container.

The database is created automatically by PostgreSQL using the values defined in `compose.yaml`.

The application uses the PostgreSQL database:

```text
stridebr
```

The project currently contains SQL files under:

```text
src/database/
```

including:

```text
src/database/stridebr.sql
src/database/stridebr_activities_schema.sql
```

The exact database initialization/migration strategy should be kept in `compose.yaml` and the project's SQL files rather than requiring manual PostgreSQL installation on the host.

### Accessing PostgreSQL

You can open `psql` directly inside the database container:

```bash
docker compose exec postgres psql -U stridebr -d stridebr
```

For example:

```sql
\conninfo
```

Exit with:

```sql
\q
```

You can also inspect the database from the host if the PostgreSQL port is exposed by Compose:

```bash
psql -h 127.0.0.1 -p 5432 -U stridebr -d stridebr
```

This requires `psql` to be installed on the host. It is **not required** for normal development.

## PHP Dependencies

PHP dependencies are managed by Composer.

The current PHP dependency is:

```text
hidehalo/nanoid-php
```

NanoID is used by the project for generating identifiers.

The project uses it in several PHP files, including:

```text
public/signup.php
public/user/cronogramatreinos.php
public/user/exercicioscronograma.php
public/user/ferramentastreino.php
src/function/salvarcronograma.php
src/function/atividade_modelo.php
```

The `vendor/` directory is generated by Composer.

When the Docker environment is built for the first time, Composer should install the dependencies automatically.

If you need to reinstall PHP dependencies inside the application container:

```bash
docker compose exec app composer install
```

## JavaScript Dependencies

The project contains browser-side JavaScript under:

```text
public/assets/js/
```

Currently:

```text
atividades.js
cronogramas.js
loginform.js
scripts.js
treino.js
```

The project also contains:

```text
package.json
```

with the NanoID JavaScript package.

However, the project does **not** require a Node.js server or Node.js runtime to serve the application.

If frontend dependencies need to be installed or rebuilt in the future, that can be handled by a dedicated development container or build step without requiring Node.js on the host.

## Development

The repository is intended to be edited directly from the host machine while the application runs inside Docker.

Because the project directory is mounted into the application container, changes made in your editor are immediately available to Apache/PHP.

### VS Code

Open the cloned repository normally:

```bash
cd StrideBR
code .
```

You edit the files on the host:

```text
public/
src/
composer.json
compose.yaml
...
```

while Docker runs the application.

You do **not** need to enter the container to edit the source code.

If you want to use a shell inside the application container:

```bash
docker compose exec app bash
```

If the image does not include Bash:

```bash
docker compose exec app sh
```

## Database Configuration in PHP

The web application reads the PostgreSQL connection information from environment variables.

The expected variables are:

```text
STRIDEBR_DB_HOST
STRIDEBR_DB_NAME
STRIDEBR_DB_USER
STRIDEBR_DB_PASSWORD
```

The application should receive values equivalent to:

```text
STRIDEBR_DB_HOST=postgres
STRIDEBR_DB_NAME=stridebr
STRIDEBR_DB_USER=stridebr
STRIDEBR_DB_PASSWORD=stridebr_dev
```

The PHP configuration also sets:

```text
America/Sao_Paulo
```

as the PostgreSQL session timezone and uses:

```text
stridebr, public
```

as the PostgreSQL search path.

## pgAdmin

The project contains a PostgreSQL administration configuration file:

```text
src/config/pg_config.php
```

and templates:

```text
src/config/pg_config-local-template.php
src/config/pg_config-web-template.php
```

When connecting from a tool running **inside Docker**, use:

```text
Host: postgres
Port: 5432
Database: stridebr
User: stridebr
Password: stridebr_dev
```

When connecting from the **host machine**, use:

```text
Host: 127.0.0.1
Port: 5432
Database: stridebr
User: stridebr
Password: stridebr_dev
```

The difference is important:

```text
PHP container ──> postgres:5432
Host machine  ──> 127.0.0.1:5432
```

## Resetting the Development Database

To remove the containers and keep the database:

```bash
docker compose down
```

To remove the database volume as well:

```bash
docker compose down -v
```

> **Warning:** `docker compose down -v` permanently removes the PostgreSQL data stored in the Compose volume.

After that, start the environment again:

```bash
docker compose up -d --build
```

PostgreSQL will create a fresh `stridebr` database.

## Updating the Project

After pulling changes from Git:

```bash
git pull
```

Rebuild the environment when the Docker configuration or dependencies changed:

```bash
docker compose up -d --build
```

If only PHP/application files changed, Docker's mounted development files should normally make the changes available without rebuilding.

## Useful Commands

### Start

```bash
docker compose up -d
```

### Rebuild

```bash
docker compose up -d --build
```

### Stop

```bash
docker compose down
```

### Container status

```bash
docker compose ps
```

### Application shell

```bash
docker compose exec app bash
```

### PostgreSQL shell

```bash
docker compose exec postgres psql -U stridebr -d stridebr
```

### Application logs

```bash
docker compose logs -f app
```

### PostgreSQL logs

```bash
docker compose logs -f postgres
```

### All logs

```bash
docker compose logs -f
```

## Production

The current Docker setup is primarily intended for **development and self-hosting**.

It is not yet a production deployment specification.

Before exposing StrideBR to the internet, the environment should be reviewed for:

- HTTPS/TLS
- production database credentials
- secret management
- PHP/Apache hardening
- database backups
- error reporting
- file permissions
- container security
- resource limits
- reverse proxy configuration

Do not use the default development password in a public production deployment.

## License

This project is under license. See **[LICENSE](LICENSE)** for more details.