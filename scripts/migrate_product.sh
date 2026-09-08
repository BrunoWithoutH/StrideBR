#!/bin/sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$project_root"

command="${1:-apply}"
argument="${2:-}"

env_file="${STRIDEBR_ENV_FILE:-$project_root/.env}"
case "$env_file" in /*) ;; *) env_file="$project_root/$env_file" ;; esac
if [ -f "$env_file" ] && [ -z "${STRIDEBR_DB_HOST:-}" ]; then
  set -a
  . "$env_file"
  set +a
fi

: "${STRIDEBR_DB_HOST:?Defina STRIDEBR_DB_HOST ou crie $env_file}"
: "${STRIDEBR_DB_NAME:?Defina STRIDEBR_DB_NAME ou crie $env_file}"
: "${STRIDEBR_DB_USER:?Defina STRIDEBR_DB_USER ou crie $env_file}"
: "${STRIDEBR_DB_PASSWORD:?Defina STRIDEBR_DB_PASSWORD ou crie $env_file}"

port="${STRIDEBR_DB_PORT:-5432}"
export PGPASSWORD="$STRIDEBR_DB_PASSWORD"
export PGSSLMODE="${STRIDEBR_DB_SSLMODE:-prefer}"

run_psql() {
  psql \
    -v ON_ERROR_STOP=1 \
    -h "$STRIDEBR_DB_HOST" \
    -p "$port" \
    -U "$STRIDEBR_DB_USER" \
    -d "$STRIDEBR_DB_NAME" \
    "$@"
}

# One connection holds the lock while the child runner applies SQL using its own
# connections. All replicas/jobs using this runner serialize on the same database.
if [ "${STRIDEBR_MIGRATION_LOCK_HELD:-0}" != "1" ]; then
  export STRIDEBR_MIGRATION_LOCK_HELD=1
  export STRIDEBR_MIGRATION_SCRIPT="$0"
  export STRIDEBR_MIGRATION_COMMAND="$command"
  export STRIDEBR_MIGRATION_ARGUMENT="$argument"
  run_psql <<'SQL'
SELECT pg_advisory_lock(1937011300, 1);
\! sh "$STRIDEBR_MIGRATION_SCRIPT" "$STRIDEBR_MIGRATION_COMMAND" "$STRIDEBR_MIGRATION_ARGUMENT"
\if :SHELL_ERROR
SELECT 1 / 0;
\endif
SELECT pg_advisory_unlock(1937011300, 1);
SQL
  exit $?
fi

ensure_history() {
  run_psql <<'SQL'
CREATE TABLE IF NOT EXISTS public.stridebr_schema_migrations (
  version VARCHAR(120) PRIMARY KEY,
  applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
SQL
}

migration_files() {
  printf '%s\n' src/database/migrations/20260815_product_foundation.sql
  for migration in src/database/migrations/*.sql; do
    [ "$migration" = "src/database/migrations/20260815_product_foundation.sql" ] && continue
    printf '%s\n' "$migration"
  done
}

is_applied() {
  version="$1"
  run_psql -tAc "SELECT EXISTS (SELECT 1 FROM public.stridebr_schema_migrations WHERE version = '$version')" | tr -d '[:space:]'
}


legacy_rc_versions() {
  cat <<'EOF'
20260819_identity_security_hardening.sql
20260819_trainer_monthly_hardening.sql
20260821_schedule_recurrence_exercise_fields.sql
20260822_activities_v2.sql
20260822_dashboard_goals.sql
20260824_workout_logging_v2.sql
20260825_alpha_stability.sql
20260825_daily_use_polish.sql
20260825_performance_pass.sql
20260825_product_polish_v1.sql
20260825_routes_v1.sql
20260825_v1_release.sql
20260826_planning_performance_v2.sql
20260827_activity_workout_library_polish.sql
20260827_schedule_reconciliation.sql
20260828_goals_flexibility_v2.sql
20260828_schedule_editing_hardening.sql
20260828_schedule_history_flexibility.sql
20260829_activity_file_exchange.sql
20260829_performance_round_v2.sql
20260829_strength_activity_fields_v3.sql
20260830_activity_sharing_v2.sql
20260830_desktop_release_candidate_features.sql
20260830_gps_web.sql
20260831_activity_segments_v2.sql
20260831_i18n_theme_google_auth.sql
20260831_triathlon_share_polish.sql
20260901_elevation_visible_by_default.sql
20260901_generic_activity_allows_route.sql
20260901_integrations_foundation.sql
20260901_strength_muscle_metadata.sql
20260901_strength_progress_foundation.sql
20260902_01_sport_taxonomy_v1.sql
20260902_02_activity_energy_v1.sql
20260902_03_sport_performance_metrics.sql
20260902_04_sport_session_details.sql
EOF
}

record_version() {
  version="$1"
  run_psql -v migration_version="$version" <<'SQL'
INSERT INTO public.stridebr_schema_migrations (version)
VALUES (:'migration_version')
ON CONFLICT (version) DO NOTHING;
SQL
}

extract_rc_block() {
  version="$1"
  output="$2"
  awk -v wanted="$version" '
    /^-- Consolidated from: / {
      current=$0
      sub(/^-- Consolidated from: /, "", current)
      active=(current==wanted)
      if (found && !active) exit
      if (active) { found=1; next }
    }
    found && active { print }
  ' src/database/migrations/20260903_v1_rc.sql > "$output"
  [ -s "$output" ]
}

apply_rc_migration() {
  migration="src/database/migrations/20260903_v1_rc.sql"
  total=0
  applied=0
  total="$(legacy_rc_versions | wc -l | tr -d ' ')"
  applied=0
  for version in $(legacy_rc_versions); do
    if [ "$(is_applied "$version")" = "t" ]; then
      applied=$((applied + 1))
    fi
  done

  if [ "$applied" -eq 0 ]; then
    echo "[migrate] aplicando release consolidada: $(basename "$migration")"
    run_psql -f "$migration"
    record_version "$(basename "$migration")"
    return
  fi

  if [ "$applied" -eq "$total" ]; then
    echo "[migrate] migrations intermediárias da RC já aplicadas; registrando release consolidada."
    record_version "$(basename "$migration")"
    return
  fi

  echo "[migrate] detectado banco parcialmente migrado ($applied/$total blocos legados)."
  tmpdir="$(mktemp -d)"
  trap 'rm -rf "$tmpdir"' EXIT INT TERM
  for version in $(legacy_rc_versions); do
    if [ "$(is_applied "$version")" = "t" ]; then
      echo "[migrate] legado já aplicado: $version"
      continue
    fi
    block="$tmpdir/$version"
    if ! extract_rc_block "$version" "$block"; then
      echo "[migrate] bloco legado não encontrado na migration consolidada: $version" >&2
      rm -rf "$tmpdir"
      trap - EXIT INT TERM
      return 1
    fi
    echo "[migrate] aplicando bloco legado pendente: $version"
    run_psql -f "$block"
    record_version "$version"
  done
  rm -rf "$tmpdir"
  trap - EXIT INT TERM
  record_version "$(basename "$migration")"
}

show_status() {
  ensure_history
  printf '%-52s %s\n' "MIGRATION" "STATUS"
  printf '%-52s %s\n' "----------------------------------------------------" "----------"
  migration_files | while IFS= read -r migration; do
    version="$(basename "$migration")"
    if [ "$(is_applied "$version")" = "t" ]; then
      printf '%-52s %s\n' "$version" "aplicada"
    else
      printf '%-52s %s\n' "$version" "PENDENTE"
    fi
  done
}

mark_migration() {
  ensure_history
  version="$(basename "$1")"
  file="src/database/migrations/$version"
  if [ ! -f "$file" ]; then
    echo "[migrate] migration não encontrada no repositório: $version" >&2
    exit 1
  fi
  if [ "$(is_applied "$version")" = "t" ]; then
    echo "[migrate] já registrada: $version"
    exit 0
  fi
  if [ "${STRIDEBR_MIGRATION_MARK_YES:-0}" != "1" ]; then
    printf 'Registrar %s como já aplicada sem executar o SQL? [y/N] ' "$version"
    read -r answer
    case "$answer" in
      y|Y|yes|YES|sim|SIM) ;;
      *) echo "[migrate] cancelado."; exit 1 ;;
    esac
  fi
  run_psql -v migration_version="$version" <<'SQL'
INSERT INTO public.stridebr_schema_migrations (version)
VALUES (:'migration_version')
ON CONFLICT (version) DO NOTHING;
SQL
  echo "[migrate] registrada: $version"
}

apply_migrations() {
  base_ready="$(run_psql -tAc "SELECT to_regclass('stridebr.usuarios') IS NOT NULL" | tr -d '[:space:]')"
  if [ "$base_ready" != "t" ]; then
    echo "[migrate] schema base ausente; inicializando banco..."
    run_psql -f src/database/stridebr.sql
    run_psql -f src/database/stridebr_activities_schema.sql
    run_psql -f src/database/stridebr_seed.sql
  fi

  ensure_history
  migration_files | while IFS= read -r migration; do
    version="$(basename "$migration")"
    if [ "$(is_applied "$version")" = "t" ]; then
      echo "[migrate] já aplicada: $version"
      continue
    fi

    if [ "$version" = "20260903_v1_rc.sql" ]; then
      apply_rc_migration
      continue
    fi

    echo "[migrate] aplicando: $version"
    run_psql -f "$migration"
    record_version "$version"
  done

  echo "[migrate] banco atualizado."
}

case "$command" in
  apply|run|'') apply_migrations ;;
  status) show_status ;;
  mark)
    if [ -z "$argument" ]; then
      echo "Uso: $0 mark NOME_DA_MIGRATION.sql" >&2
      exit 2
    fi
    mark_migration "$argument"
    ;;
  *)
    echo "Uso: $0 [apply|status|mark NOME.sql]" >&2
    exit 2
    ;;
esac
