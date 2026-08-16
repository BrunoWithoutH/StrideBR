#!/usr/bin/env bash
set -euo pipefail

: "${STRIDEBR_DB_HOST:?Defina STRIDEBR_DB_HOST}"
: "${STRIDEBR_DB_NAME:?Defina STRIDEBR_DB_NAME}"
: "${STRIDEBR_DB_USER:?Defina STRIDEBR_DB_USER}"
: "${STRIDEBR_DB_PASSWORD:?Defina STRIDEBR_DB_PASSWORD}"

port="${STRIDEBR_DB_PORT:-5432}"

for migration in \
  src/database/migrations/20260815_product_foundation.sql \
  src/database/migrations/20260815_alpha_readiness.sql \
  src/database/migrations/20260815_feedback_anonymous.sql \
  src/database/migrations/20260815_fix_cronograma_delete_activity_trigger.sql
do
  PGPASSWORD="$STRIDEBR_DB_PASSWORD" psql \
    -v ON_ERROR_STOP=1 \
    -h "$STRIDEBR_DB_HOST" \
    -p "$port" \
    -U "$STRIDEBR_DB_USER" \
    -d "$STRIDEBR_DB_NAME" \
    -f "$migration"
done
