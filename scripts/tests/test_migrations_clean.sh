#!/bin/sh
set -eu

database=${STRIDEBR_TEST_DB_NAME:-stridebr_alpha_migrations_test}
case "$database" in
  *test*|*alpha*) ;;
  *) echo "Banco de teste recusado: $database" >&2; exit 2 ;;
esac

docker compose up -d postgres >/dev/null
cleanup() {
  docker compose exec -T postgres dropdb -U stridebr --if-exists "$database" >/dev/null
}
trap cleanup EXIT INT TERM

cleanup
docker compose exec -T postgres createdb -U stridebr "$database"
docker compose run --rm -T -e STRIDEBR_DB_NAME="$database" migrate >/dev/null
first="$(docker compose exec -T postgres psql -U stridebr -d "$database" -Atc 'SELECT count(*) FROM public.stridebr_schema_migrations')"
expected="$(find src/database/migrations -maxdepth 1 -name '*.sql' | wc -l | tr -d ' ')"
[ "$first" = "$expected" ] || { echo "registro de migrations incompleto: $first/$expected" >&2; exit 1; }
docker compose run --rm -T -e STRIDEBR_DB_NAME="$database" migrate >/dev/null
second="$(docker compose exec -T postgres psql -U stridebr -d "$database" -Atc 'SELECT count(*) FROM public.stridebr_schema_migrations')"
[ "$second" = "$expected" ] || { echo "segunda execução reaplicou migrations: $second/$expected" >&2; exit 1; }
docker compose exec -T postgres psql -U stridebr -d "$database" -Atc "SELECT to_regclass('stridebr.usuarios'), to_regclass('stridebr.rotas_atividade')" | grep -q 'usuarios|rotas_atividade'
