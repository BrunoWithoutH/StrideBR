#!/bin/sh
set -eu
exec php "$(dirname "$0")/sync_integrations.php" "$@"
