#!/bin/sh
set -eu
case "${STRIDEBR_APP_ENV:-development}" in
  development) ;;
  staging|production) php /var/www/html/scripts/config_check.php ;;
  *) echo 'Invalid APP_ENV' >&2; exit 1 ;;
esac
exec docker-php-entrypoint "$@"
