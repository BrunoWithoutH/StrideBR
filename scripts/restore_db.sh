#!/bin/sh
set -eu

: "${STRIDEBR_DB_HOST:?Defina STRIDEBR_DB_HOST}"
: "${STRIDEBR_DB_NAME:?Defina STRIDEBR_DB_NAME}"
: "${STRIDEBR_DB_USER:?Defina STRIDEBR_DB_USER}"
: "${STRIDEBR_DB_PASSWORD:?Defina STRIDEBR_DB_PASSWORD}"

if ! command -v pg_restore >/dev/null 2>&1; then
  echo 'pg_restore não encontrado.' >&2
  exit 1
fi

backup=${1:-}
confirm=${2:-}
if [ -z "$backup" ] || [ ! -f "$backup" ]; then
  echo 'Uso: ./scripts/restore_db.sh caminho/backup.dump --yes' >&2
  exit 2
fi
if [ "$confirm" != '--yes' ]; then
  echo "RESTORE NÃO EXECUTADO. Destino: $STRIDEBR_DB_NAME@$STRIDEBR_DB_HOST" >&2
  echo 'Passe --yes como segundo argumento depois de conferir que o destino é o banco correto.' >&2
  exit 2
fi

port=${STRIDEBR_DB_PORT:-5432}
export PGPASSWORD="$STRIDEBR_DB_PASSWORD"

printf 'Restaurando %s em %s@%s...\n' "$backup" "$STRIDEBR_DB_NAME" "$STRIDEBR_DB_HOST"
pg_restore \
  -h "$STRIDEBR_DB_HOST" \
  -p "$port" \
  -U "$STRIDEBR_DB_USER" \
  -d "$STRIDEBR_DB_NAME" \
  --clean \
  --if-exists \
  --no-owner \
  --no-privileges \
  "$backup"
printf '%s\n' 'Restore concluído.'
