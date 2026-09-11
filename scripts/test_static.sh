#!/bin/sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$project_root"

if ! command -v php >/dev/null 2>&1; then
  echo 'php não encontrado.' >&2
  exit 1
fi

find public src scripts -type f -name '*.php' -not -path '*/vendor/*' -print0 \
  | xargs -0 -n1 php -l >/dev/null
printf '%s\n' '✓ PHP syntax'
php scripts/tests/test_seo.php
php scripts/tests/test_workout_load.php
php scripts/tests/test_deploy_configuration.php
sh -eu -c '. ./.env.example; test "$POLAR_OAUTH_SCOPE" = "training_sessions:read activity:read profile:read"; test "$COROS_MCP_URL" = "https://mcp.coros.com/mcp"; test -z "${GOOGLE_HEALTH_CLIENT_ID:-}"; test -z "${GOOGLE_HEALTH_CLIENT_SECRET:-}"; test -z "${FITBIT_CLIENT_ID:-}"'
printf '%s\n' '✓ canonical env is shell-compatible'

if command -v node >/dev/null 2>&1; then
  find public/assets/js -type f -name '*.js' -print0 | xargs -0 -n1 node --check >/dev/null
  printf '%s\n' '✓ JavaScript syntax'
node scripts/tests/test_ui_boot.js
node scripts/tests/test_activity_route_circuit.js
node scripts/tests/test_share_route_scale.js
node scripts/tests/test_share_route_export_png.js
node scripts/tests/test_share_metric_defaults.js
node scripts/tests/test_activity_sport_context.js
else
  printf '%s\n' '○ JavaScript syntax: node não encontrado, verificação ignorada'
fi

for script in scripts/*.sh scripts/tests/*.sh; do
  [ -f "$script" ] || continue
  sh -n "$script"
done
printf '%s\n' '✓ shell syntax'

if grep -RInE '^(<<<<<<<|>>>>>>>)' --exclude-dir=.git --exclude-dir=vendor . >/tmp/stridebr-conflicts.$$ 2>/dev/null; then
  cat /tmp/stridebr-conflicts.$$ >&2
  rm -f /tmp/stridebr-conflicts.$$
  echo 'Marcador de conflito de merge encontrado.' >&2
  exit 1
fi
rm -f /tmp/stridebr-conflicts.$$
printf '%s\n' '✓ no merge-conflict markers'

for migration in src/database/migrations/*.sql; do
  if ! grep -Fq 'SET search_path TO stridebr, public;' "$migration"; then
    echo "Migration sem search_path explícito: $migration" >&2
    exit 1
  fi
done
printf '%s\n' '✓ migration search_path'

php scripts/tests/test_unit.php
php scripts/tests/test_activity_exchange_parser.php
php scripts/tests/test_activity_sport_context.php
php scripts/tests/test_activity_performance_static.php
php scripts/tests/test_dashboard_ux_static.php
php scripts/tests/test_dashboard_week_consistency.php
php scripts/tests/test_home_week_interactions_static.php
php scripts/tests/test_desktop_release_finish_static.php
php scripts/tests/test_activity_sharing_v2_static.php
php scripts/tests/test_share_architecture_static.php
php scripts/tests/test_share_route_scale_static.php
php scripts/tests/test_share_map_metrics_static.php
php scripts/tests/test_share_route_export_png_static.php
php scripts/tests/test_activity_delete_modal_static.php
php scripts/tests/test_activity_edit_embed_static.php
php scripts/tests/test_gps_web_static.php
php scripts/tests/test_pwa_foundation_static.php
php scripts/tests/test_schedule_mobile_pwa_static.php
php scripts/tests/test_marketing_attribution_static.php
php scripts/tests/test_web1_robustness_static.php
php scripts/tests/test_onboarding_copy_ux_static.php
php scripts/tests/test_onboarding_i18n_static.php
php scripts/tests/test_ux_consistency_static.php
php scripts/tests/test_share_compact_ux_static.php
php scripts/tests/test_activity_workspace_polish_static.php
php scripts/tests/test_activity_segments_v2_static.php
php scripts/tests/test_activity_duration_precision.php
php scripts/tests/test_activity_editor_parity_static.php
php scripts/tests/test_activity_consolidation_static.php
php scripts/tests/test_activity_visibility_static.php
php scripts/tests/test_activity_route_circuit_static.php
php scripts/tests/test_activity_route_circuit_export.php
php scripts/tests/test_map_basemaps_static.php
php scripts/tests/test_activity_manual_save_fake.php
STRIDEBR_FAKE_LEGACY=1 php scripts/tests/test_activity_manual_save_fake.php
php scripts/tests/test_i18n_theme_google_static.php
php scripts/tests/test_production_theme_migrations_static.php
php scripts/tests/test_integrations_foundation_static.php
php scripts/tests/test_integrations_external_rc4.php
php scripts/tests/test_strava_webhooks.php
php scripts/tests/test_core_nav_strava_backfill_static.php
php scripts/tests/test_api_v1_static.php
php scripts/tests/test_ads_placements_static.php
php scripts/tests/test_sport_hub_monetization_static.php
php scripts/tests/test_sport_taxonomy_energy_static.php
php scripts/tests/test_sport_picker_static.php
php scripts/tests/test_manual_strength_ui_static.php
php scripts/tests/test_final_product_polish_static.php
php scripts/tests/test_migration_dependency_static.php
php scripts/tests/test_radius_system_static.php
php scripts/tests/test_design_system_static.php
php scripts/tests/test_dark_contrast_static.php
php scripts/tests/test_visual_consistency_static.php
php scripts/tests/test_schedule_hierarchy_static.php
php scripts/tests/test_admin_feedback_static.php
php scripts/tests/test_ui_consolidation_static.php
php scripts/tests/test_templates.php

php scripts/tests/test_planning_week.php
php scripts/tests/test_i18n_coverage.php
php scripts/tests/test_transactional_email_i18n.php
