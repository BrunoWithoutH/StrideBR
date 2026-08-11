# StrideBR

![GitHub repo size](https://img.shields.io/github/repo-size/BrunoWithoutH/StrideBR?style=for-the-badge)
![GitHub forks](https://img.shields.io/github/forks/BrunoWithoutH/StrideBR?style=for-the-badge)
![Bitbucket open issues](https://img.shields.io/bitbucket/issues/BrunoWithoutH/StrideBR?style=for-the-badge)
![Bitbucket open pull requests](https://img.shields.io/bitbucket/pr-raw/BrunoWithoutH/StrideBR?style=for-the-badge)  

<img src="public/assets/img/logos/stridebr-banner.svg" width="100%" style="max-width:600px;">

> **StrideBR** is a platform built to help athletes and sports enthusiasts track, organize and improve their training over time.


## Overview

StrideBR is currently focused on making training organization and activity tracking simple and practical.

### Features

- [x] Physical activity tracking
- [x] Weekly training schedule
- [x] Workout tools
- [ ] Sports event calendar

### Tech Stack

| Technology | Purpose |
|---|---|
| PHP 8.5 | Application backend |
| Apache 2.4 | Web server |
| PostgreSQL 18 | Database |
| Docker Compose | Development environment |
| Composer | PHP dependency management |
| JavaScript | Client-side interactions |
| HTML / CSS | User interface |

## Getting Started

### Prerequisites

Before you begin, make sure you have:

- [Git](https://git-scm.com/)
- [Docker](https://www.docker.com/)
- Docker Compose

The recommended development environment is Linux.

### Installation

Clone the repository:

```bash
git clone https://github.com/BrunoWithoutH/StrideBR.git
cd StrideBR
```

Start the environment:

```bash
docker compose up -d --build
```

On the first startup, Docker will build the application environment, start PostgreSQL and create the development database.

Once the containers are running, open:

```text
http://localhost
```

Check the status of the services:

```bash
docker compose ps
```

### Development

The repository is mounted into the application container, so the source code can be edited directly from the host.

For example, with VS Code:

```bash
code .
```

Changes made to PHP, HTML, CSS or JavaScript files are available to the running application without rebuilding the image.

To open a shell inside the application container:

```bash
docker compose exec app bash
```

If Bash is not available:

```bash
docker compose exec app sh
```

### Dependencies

PHP dependencies are managed with Composer.

The project currently uses:

```text
hidehalo/nanoid-php
```

Dependencies are installed automatically when the development environment is initialized.

To install them manually:

```bash
docker compose exec app composer install
```

## Database

StrideBR uses PostgreSQL 18 in a dedicated Docker container.

The application connects to PostgreSQL through the Docker Compose service name:

```text
postgres
```

The default development configuration is:

| Setting | Value |
|---|---|
| Host | `postgres` |
| Port | `5432` |
| Database | `stridebr` |
| User | `stridebr` |
| Password | `stridebr_dev` |

The database data is persisted in the Docker volume:

```text
stridebr-postgres-data
```

### PostgreSQL shell

To access the database directly:

```bash
docker compose exec postgres psql -U stridebr -d stridebr
```

Then:

```sql
\conninfo
```

Exit with:

```sql
\q
```

### Database configuration

The application reads the following environment variables:

```text
STRIDEBR_DB_HOST
STRIDEBR_DB_NAME
STRIDEBR_DB_USER
STRIDEBR_DB_PASSWORD
```

Inside Docker, they should resolve to:

```text
STRIDEBR_DB_HOST=postgres
STRIDEBR_DB_NAME=stridebr
STRIDEBR_DB_USER=stridebr
STRIDEBR_DB_PASSWORD=stridebr_dev
```

The PostgreSQL session timezone is configured as:

```text
America/Sao_Paulo
```

and the application uses:

```text
stridebr, public
```

as its PostgreSQL search path.

### Database files

SQL files used by the project are located at:

```text
src/database/
```

Current files include:

```text
src/database/stridebr.sql
src/database/stridebr_activities_schema.sql
```

The database name and PostgreSQL service name are part of the current application configuration. If they are changed, the PHP configuration and any database-related tooling must be updated accordingly.

## Project Structure

```text
StrideBR/
├── public/                  # Apache document root
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
│   ├── database/            # SQL schemas and database files
│   ├── function/            # Application functions
│   ├── includes/            # Shared includes
│   └── layout/              # Shared layout components
│
├── vendor/                  # Composer dependencies
├── composer.json            # PHP dependencies
├── package.json             # Frontend package metadata
├── compose.yaml             # Docker environment
└── README.md
```

## Docker Services

The development environment consists of two services:

```text
                    ┌──────────────────────┐
                    │       StrideBR       │
                    │                      │
                    │   Apache + PHP       │
                    │                      │
                    │   http://localhost   │
                    └──────────┬───────────┘
                               │
                               │ Docker network
                               ▼
                    ┌──────────────────────┐
                    │      PostgreSQL      │
                    │                      │
                    │       stridebr       │
                    └──────────────────────┘
```

The application and database are isolated into separate containers while remaining connected through Docker Compose.

## Common Commands

### Start

```bash
docker compose up -d
```

### Rebuild

```bash
docker compose up -d --build
```

### Stop and remove containers

```bash
docker compose down
```

### Stop without removing containers

```bash
docker compose stop
```

### Start existing containers

```bash
docker compose start
```

### Check services

```bash
docker compose ps
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

## Resetting the Database

To remove the containers while keeping the database:

```bash
docker compose down
```

To remove the containers **and the PostgreSQL volume**:

```bash
docker compose down -v
```

> **Warning:** `docker compose down -v` permanently deletes the development database stored in the Docker volume.

Start the environment again to create a fresh database:

```bash
docker compose up -d --build
```

## Updating the Project

Pull the latest changes:

```bash
git pull
```

If the Docker configuration or dependencies changed:

```bash
docker compose up -d --build
```

For normal source-code changes, rebuilding is usually unnecessary because the project files are mounted into the application container.

## pgAdmin and Other Database Tools

Database tools running **inside Docker** should connect using:

```text
Host: postgres
Port: 5432
Database: stridebr
User: stridebr
Password: stridebr_dev
```

Tools running directly on the host should use:

```text
Host: 127.0.0.1
Port: 5432
Database: stridebr
User: stridebr
Password: stridebr_dev
```

The project includes database configuration files under:

```text
src/config/
```

including:

```text
pg_config.php
pg_config-local-template.php
pg_config-web-template.php
```

## Production

The current Docker configuration is intended primarily for development and self-hosting.

Before exposing StrideBR to the public internet, review at least:

- HTTPS/TLS
- production database credentials
- secret management
- Apache/PHP hardening
- database backups
- error handling
- file permissions
- container security
- resource limits
- reverse proxy configuration

Do not use the default development credentials in a public production deployment.

## License

This project is under license. See **[LICENSE](LICENSE)** for more details.