#!/bin/sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$project_root"

./scripts/test_static.sh

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  echo '✗ integration'
  echo '  Docker Compose não encontrado. Os checks estáticos/unitários passaram, mas os testes PostgreSQL não foram executados.'
  exit 2
fi

database=${STRIDEBR_TEST_DB_NAME:-stridebr_alpha_integration_test}
case "$database" in
  *test*|*alpha*) ;;
  *)
    echo "Banco de teste recusado: $database. O nome precisa conter test ou alpha." >&2
    exit 2
    ;;
esac

cleanup() {
  docker compose exec -T postgres dropdb -U "${STRIDEBR_DB_USER:-stridebr}" --if-exists "$database" >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

docker compose up -d postgres >/dev/null
cleanup
docker compose exec -T postgres createdb -U "${STRIDEBR_DB_USER:-stridebr}" "$database"

docker compose run --rm -T -e STRIDEBR_DB_NAME="$database" migrate >/dev/null
expected=$(find src/database/migrations -maxdepth 1 -name '*.sql' | wc -l | tr -d ' ')
first=$(docker compose exec -T postgres psql -U "${STRIDEBR_DB_USER:-stridebr}" -d "$database" -Atc 'SELECT count(*) FROM public.stridebr_schema_migrations')
[ "$first" = "$expected" ] || { echo "✗ migrations: registro incompleto ($first/$expected)" >&2; exit 1; }

docker compose run --rm -T -e STRIDEBR_DB_NAME="$database" migrate >/dev/null
second=$(docker compose exec -T postgres psql -U "${STRIDEBR_DB_USER:-stridebr}" -d "$database" -Atc 'SELECT count(*) FROM public.stridebr_schema_migrations')
[ "$second" = "$expected" ] || { echo "✗ migrations: segunda execução alterou registro ($second/$expected)" >&2; exit 1; }
printf '%s\n' "✓ migrations ($expected applied, idempotent runner)"

docker compose run --rm -T --no-deps \
  -e STRIDEBR_DB_HOST=postgres \
  -e STRIDEBR_DB_PORT=5432 \
  -e STRIDEBR_DB_NAME="$database" \
  -e STRIDEBR_DB_USER="${STRIDEBR_DB_USER:-stridebr}" \
  -e STRIDEBR_DB_PASSWORD="${STRIDEBR_DB_PASSWORD:-stridebr_dev}" \
  -e STRIDEBR_ELEVATION_API_ENABLED=0 \
  app php scripts/tests/run.php
