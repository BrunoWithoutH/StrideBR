#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

remote="${STRIDEBR_DEPLOY_REMOTE:-stridebr@ssh-stridebr.alwaysdata.net}"
destination="${STRIDEBR_DEPLOY_PATH:-~/www/stridebr/}"

if [[ "${STRIDEBR_SKIP_MIGRATIONS:-0}" != "1" ]]; then
  echo "[deploy] aplicando migrations pendentes..."
  if ! ./scripts/migrate_product.sh; then
    echo "[deploy] ERRO: migration falhou; nenhum arquivo foi enviado ao servidor." >&2
    echo "[deploy] corrija a migration e execute o deploy novamente." >&2
    exit 1
  fi
else
  echo "[deploy] migrations ignoradas por STRIDEBR_SKIP_MIGRATIONS=1"
fi

build="$(date -u +%Y%m%d-%H%M%S)"
if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  commit="$(git rev-parse --short HEAD 2>/dev/null || true)"
  if [[ -n "$commit" ]]; then build="${build}-${commit}"; fi
fi
printf '%s\n' "$build" > .stridebr-build
echo "[deploy] build: $build"

echo "[deploy] enviando arquivos..."
rsync -avz --delete --progress \
  --exclude='.git/' \
  --exclude='.env' \
  --exclude='compose.yaml' \
  --exclude='Dockerfile' \
  --exclude='stridebr.sql' \
  --exclude='*.dump' \
  --exclude='node_modules/' \
  --filter='protect public/uploads/***' \
  --include='public/uploads/' \
  --include='public/uploads/.htaccess' \
  --include='public/uploads/avatars/' \
  --include='public/uploads/avatars/.htaccess' \
  --include='public/uploads/avatars/index.html' \
  --include='public/uploads/events/' \
  --exclude='public/uploads/***' \
  ./ "${remote}:${destination}"

echo "[deploy] concluído."
