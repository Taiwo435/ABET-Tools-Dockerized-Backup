#!/usr/bin/env bash

set -euo pipefail

REPO_ROOT=$(git rev-parse --show-toplevel)
# build the .htaccess file
cd "$REPO_ROOT"
rm -rf docker/app/build || true
mkdir -p docker/app/build
cp src/public/.htaccess docker/app/build/.htaccess
python3 scripts/deployment/generate_env.py docker/.env >> docker/app/build/.htaccess
echo "[INFO] .htaccess file generated"

# docker compose up with staging file
