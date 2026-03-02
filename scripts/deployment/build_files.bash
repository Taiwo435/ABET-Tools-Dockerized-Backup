#!/usr/bin/env bash

set -euo pipefail

# build the .htaccess file
cd ../../
rm -rf docker/app/build || true
mkdir -p docker/app/build
cp src/public/.htaccess docker/app/build/.htaccess
python3 scripts/deployment/generate_env.py docker/.env >> docker/app/build/.htaccess

# docker compose up with staging file
