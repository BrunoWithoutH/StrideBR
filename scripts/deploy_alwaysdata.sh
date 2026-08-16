#!/usr/bin/env bash
set -euo pipefail

remote="${STRIDEBR_DEPLOY_REMOTE:-stridebr@ssh-stridebr.alwaysdata.net}"
destination="${STRIDEBR_DEPLOY_PATH:-~/www/stridebr/}"

rsync -avz --delete --progress \
  --exclude='.git/' \
  --exclude='.env' \
  --exclude='compose.yaml' \
  --exclude='Dockerfile' \
  --exclude='stridebr.sql' \
  --exclude='*.dump' \
  --exclude='node_modules/' \
  ./ "${remote}:${destination}"
