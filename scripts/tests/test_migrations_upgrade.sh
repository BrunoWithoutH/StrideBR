#!/bin/sh
set -eu
cd "$(dirname "$0")/../.."
database=stridebr_alpha_planning_upgrade_test
stage=$(mktemp -d /tmp/stridebr-upgrade.XXXXXX)
cleanup() {
    docker compose exec -T postgres dropdb -U stridebr --if-exists "$database" >/dev/null
}
trap cleanup EXIT INT TERM
mkdir -p "$stage/scripts" "$stage/src"
cp scripts/migrate_product.sh "$stage/scripts/"
cp -R src/database "$stage/src/"
# Work only in a disposable copy: simulate the committed state before precision.
mv "$stage/src/database/migrations/20260903_z_activity_duration_precision_ms.sql" "$stage/precision.pending"
docker compose exec -T postgres createdb -U stridebr "$database"
docker compose run --rm -T -v "$stage:/workspace:ro" -e STRIDEBR_DB_NAME="$database" migrate >/dev/null
docker compose exec -T postgres psql -U stridebr -d "$database" -Atc 'SELECT version, applied_at FROM public.stridebr_schema_migrations ORDER BY version' > "$stage/before"
docker compose run --rm -T -e STRIDEBR_DB_NAME="$database" migrate >/dev/null
docker compose exec -T postgres psql -U stridebr -d "$database" -Atc "SELECT version, applied_at FROM public.stridebr_schema_migrations WHERE version <> '20260903_z_activity_duration_precision_ms.sql' ORDER BY version" > "$stage/after"
diff -u "$stage/before" "$stage/after"
for migration in src/database/migrations/*.sql; do basename "$migration"; done | sort > "$stage/expected"
docker compose exec -T postgres psql -U stridebr -d "$database" -Atc 'SELECT version FROM public.stridebr_schema_migrations ORDER BY version' > "$stage/actual"
diff -u "$stage/expected" "$stage/actual"
echo '✓ upgrade applies only pending filenames; prior timestamps unchanged; exact registry matches'
