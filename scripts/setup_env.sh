#!/usr/bin/env sh
set -eu

root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$root"

env_file="${STRIDEBR_ENV_FILE:-$root/.env}"
mode="local"
app_url=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    --production)
      mode="production"
      ;;
    --url)
      shift
      [ "$#" -gt 0 ] || { echo "ERRO: --url exige uma URL." >&2; exit 1; }
      app_url="$1"
      ;;
    *)
      echo "Uso: $0 [--production] [--url https://dominio]" >&2
      exit 2
      ;;
  esac
  shift
done

if [ ! -f "$env_file" ]; then
  if [ "$mode" = "production" ]; then
    cat > "$env_file" <<EOF
STRIDEBR_APP_ENV=production
STRIDEBR_APP_URL=${app_url}
STRIDEBR_VERSION=1.0.0-rc.2
STRIDEBR_BUILD=
STRIDEBR_MAIL_FROM=
STRIDEBR_MAIL_FROM_NAME=StrideBR
STRIDEBR_SUPPORT_EMAIL=
STRIDEBR_TERMS_VERSION=2026-08-30-1
STRIDEBR_PRIVACY_VERSION=2026-09-01-1

STRIDEBR_ELEVATION_API_ENABLED=1
STRIDEBR_INTEGRATIONS_SECRET=

GARMIN_OAUTH_CLIENT_ID=
GARMIN_OAUTH_CLIENT_SECRET=
GARMIN_OAUTH_AUTHORIZE_URL=
GARMIN_OAUTH_TOKEN_URL=
GARMIN_OAUTH_SCOPE=
GARMIN_OAUTH_TOKEN_AUTH=basic
GARMIN_OAUTH_INCLUDE_CLIENT_ID=0

STRAVA_CLIENT_ID=
STRAVA_CLIENT_SECRET=

POLAR_CLIENT_ID=
POLAR_CLIENT_SECRET=
POLAR_OAUTH_SCOPE=accesslink.read_all

SUUNTO_CLIENT_ID=
SUUNTO_CLIENT_SECRET=
SUUNTO_OAUTH_AUTHORIZE_URL=https://cloudapi-oauth.suunto.com/oauth/authorize
SUUNTO_OAUTH_TOKEN_URL=https://cloudapi-oauth.suunto.com/oauth/token
SUUNTO_OAUTH_SCOPE=workout
SUUNTO_OAUTH_TOKEN_AUTH=basic
SUUNTO_API_BASE_URL=https://cloudapi.suunto.com
SUUNTO_SUBSCRIPTION_KEY=

FITBIT_CLIENT_ID=
FITBIT_CLIENT_SECRET=
FITBIT_OAUTH_SCOPE=activity profile heartrate location

STRIDEBR_ADS_ENABLED=0
STRIDEBR_ADS_PLACEHOLDERS=0
STRIDEBR_ADS_DEV_PREVIEW=0
STRIDEBR_ADSENSE_CLIENT=
STRIDEBR_ADSENSE_SLOT_FOOTER=
STRIDEBR_ADSENSE_SLOT_RAIL_LEFT=
STRIDEBR_ADSENSE_SLOT_RAIL_RIGHT=

STRIDEBR_DONATION_ENABLED=0
STRIDEBR_DONATION_PIX_KEY=
STRIDEBR_DONATION_PIX_NAME=
STRIDEBR_DONATION_URL=
EOF
    echo "[env] criado arquivo de produção sem sobrescrever configuração de banco do servidor: $env_file"
  else
    cp "$root/.env.example" "$env_file"
    echo "[env] criado ambiente local a partir de .env.example: $env_file"
  fi
else
  echo "[env] preservando arquivo existente: $env_file"
fi

secret="$(grep -E '^STRIDEBR_INTEGRATIONS_SECRET=' "$env_file" 2>/dev/null | tail -n 1 | cut -d= -f2- || true)"
if [ -z "$secret" ]; then
  if command -v openssl >/dev/null 2>&1; then
    generated="$(openssl rand -hex 32)"
  elif command -v php >/dev/null 2>&1; then
    generated="$(php -r 'echo bin2hex(random_bytes(32));')"
  else
    echo "ERRO: instale openssl ou PHP para gerar STRIDEBR_INTEGRATIONS_SECRET." >&2
    exit 1
  fi
  tmp="${env_file}.tmp.$$"
  awk -v value="$generated" '
    BEGIN { found=0 }
    /^STRIDEBR_INTEGRATIONS_SECRET=/ { print "STRIDEBR_INTEGRATIONS_SECRET=" value; found=1; next }
    { print }
    END { if (!found) print "STRIDEBR_INTEGRATIONS_SECRET=" value }
  ' "$env_file" > "$tmp"
  mv "$tmp" "$env_file"
  echo "[env] STRIDEBR_INTEGRATIONS_SECRET gerado. Guarde backup seguro deste segredo."
else
  echo "[env] STRIDEBR_INTEGRATIONS_SECRET já existe; não foi alterado."
fi

chmod 600 "$env_file" 2>/dev/null || true

echo "[env] agora edite: $env_file"
echo "[env] confira sem exibir segredos: php scripts/integrations_status.php"
