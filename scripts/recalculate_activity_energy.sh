#!/bin/sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$project_root"

env_file="${STRIDEBR_ENV_FILE:-$HOME/.config/stridebr/db.env}"
if [ -f "$env_file" ]; then
  set -a
  . "$env_file"
  set +a
fi

exec php scripts/recalculate_activity_energy.php "$@"
