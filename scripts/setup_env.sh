#!/usr/bin/env sh
set -eu
root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$root"
env_file="${STRIDEBR_ENV_FILE:-$root/.env}"
mode=development
app_url=http://localhost:8080
while [ "$#" -gt 0 ]; do
  case "$1" in
    --production) mode=production; app_url= ;;
    --staging) mode=staging; app_url= ;;
    --url) shift; [ "$#" -gt 0 ] || exit 2; app_url="$1" ;;
    *) echo 'Usage: setup_env.sh [--staging|--production] [--url HTTPS_ORIGIN]' >&2; exit 2 ;;
  esac
  shift
done
if [ -f "$env_file" ]; then
  echo '[env] Existing file preserved; no values changed.'
  exit 0
fi
case "$mode:$app_url" in
  development:http://*|development:https://*|staging:https://*|production:https://*) ;;
  *) echo '[env] Published environments require --url https://origin' >&2; exit 1 ;;
esac
umask 077
# Use the canonical example. Credentials remain empty for Infra to supply later.
STRIDEBR_SETUP_MODE="$mode" STRIDEBR_SETUP_URL="$app_url" awk '
  /^STRIDEBR_APP_ENV=/ {print "STRIDEBR_APP_ENV=" ENVIRON["STRIDEBR_SETUP_MODE"]; next}
  /^STRIDEBR_APP_URL=/ {print "STRIDEBR_APP_URL=" ENVIRON["STRIDEBR_SETUP_URL"]; next}
  /^STRIDEBR_DB_(HOST|NAME|USER|PASSWORD)=/ && ENVIRON["STRIDEBR_SETUP_MODE"] != "development" {split($0,a,"="); print a[1] "="; next}
  /^STRIDEBR_MAIL_TRANSPORT=/ && ENVIRON["STRIDEBR_SETUP_MODE"] != "development" {print "STRIDEBR_MAIL_TRANSPORT=smtp"; next}
  /^STRIDEBR_ROBOTS_NOINDEX=/ && ENVIRON["STRIDEBR_SETUP_MODE"] == "staging" {print "STRIDEBR_ROBOTS_NOINDEX=1"; next}
  {print}
' .env.example > "$env_file"
echo '[env] Template created without credentials. Run php scripts/config_check.php after configuration.'
