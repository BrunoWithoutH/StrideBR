#!/bin/sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$project_root"

full=0
if [ "${1:-}" = "--full" ]; then full=1; fi

php scripts/config_check.php

printf '%s\n' '[release] verificações estáticas'
./scripts/test_static.sh

if command -v git >/dev/null 2>&1 && [ -d .git ]; then
  git diff --check
  printf '%s\n' '✓ git diff --check'
  if git ls-files --error-unmatch .env >/dev/null 2>&1; then
    echo 'ERRO: .env está versionado.' >&2
    exit 1
  fi
  printf '%s\n' '✓ .env não versionado'
fi

if [ "$full" -eq 1 ]; then
  printf '%s\n' '[release] suíte completa PostgreSQL'
  ./scripts/test_all.sh
fi

env_file="${STRIDEBR_ENV_FILE:-.env}"
require_database=0
app_environment=$(php -r 'require "src/includes/environment.php"; echo stridebr_app_env();')
case "$app_environment" in staging|production) require_database=1; php scripts/config_check.php --database ;; esac
if [ -f "$env_file" ] || { [ -n "${STRIDEBR_DB_HOST:-}" ] && [ -n "${STRIDEBR_DB_NAME:-}" ] && [ -n "${STRIDEBR_DB_USER:-}" ] && [ -n "${STRIDEBR_DB_PASSWORD:-}" ]; }; then
  if command -v psql >/dev/null 2>&1; then
    printf '%s\n' '[release] status das migrations'
    migration_status=$(./scripts/migrate_product.sh status)
    printf '%s\n' "$migration_status"
    if printf '%s\n' "$migration_status" | grep -q 'PENDENTE'; then
      echo 'ERRO: existem migrations pendentes.' >&2
      exit 1
    fi
    printf '%s\n' '✓ migrations sem pendências'
  else
    [ "$require_database" -eq 0 ] || { echo 'Required migration status unavailable: psql missing' >&2; exit 1; }
    printf '%s\n' '○ migrations: psql não encontrado'
  fi
else
  [ "$require_database" -eq 0 ] || { echo "Required migration status unavailable" >&2; exit 1; }
  printf '%s\n' "○ migrations: credenciais locais não encontradas em $env_file"
fi

printf '%s\n' ''
printf '%s\n' 'Checks automáticos concluídos.'
printf '%s\n' 'Ainda é obrigatório fazer o smoke test e o teste de restore descritos em docs/V1_RELEASE_CHECKLIST.md.'
