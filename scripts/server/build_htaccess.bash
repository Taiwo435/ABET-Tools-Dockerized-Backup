#!/usr/bin/env bash

set -eEuo pipefail

trap 'echo "[ERROR] in ${BASH_SOURCE[0]} at line $LINENO: $BASH_COMMAND"' ERR
PARENT_PATH=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )
cd "$PARENT_PATH/../.." # change to repo root

##################################################
# build the .htaccess file
##################################################

rm -rf docker/app/build || true
mkdir -p docker/app/build

cp src/public/.htaccess docker/app/build/.htaccess
python3 scripts/deployment/generate_env.py docker/.env >> docker/app/build/.htaccess

echo "[INFO] .htaccess file generated"

# TODO: if we have other files that need to be built in the future, we can add them here
