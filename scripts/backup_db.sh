#!/bin/sh
set -eu

: "${STRIDEBR_DB_HOST:?Defina STRIDEBR_DB_HOST}"
: "${STRIDEBR_DB_NAME:?Defina STRIDEBR_DB_NAME}"
: "${STRIDEBR_DB_USER:?Defina STRIDEBR_DB_USER}"
: "${STRIDEBR_DB_PASSWORD:?Defina STRIDEBR_DB_PASSWORD}"

if ! command -v pg_dump >/dev/null 2>&1; then
  echo 'pg_dump não encontrado.' >&2
  exit 1
fi

port=${STRIDEBR_DB_PORT:-5432}
output=${1:-"backups/stridebr-$(date +%Y%m%d-%H%M%S).dump"}
mkdir -p "$(dirname -- "$output")"
export PGPASSWORD="$STRIDEBR_DB_PASSWORD"

pg_dump \
  -h "$STRIDEBR_DB_HOST" \
  -p "$port" \
  -U "$STRIDEBR_DB_USER" \
  -d "$STRIDEBR_DB_NAME" \
  --format=custom \
  --no-owner \
  --no-privileges \
  --file="$output"

chmod 600 "$output" 2>/dev/null || true
printf 'Backup criado: %s\n' "$output"
