#!/usr/bin/env bash

set -eEuo pipefail

REPO_ROOT=$(git rev-parse --show-toplevel)

##################################################
# build the .htaccess file
##################################################
set -eEuo pipefail

REPO_ROOT=$(git rev-parse --show-toplevel)

##################################################
# build the .htaccess file
##################################################
>>>>>>> main
cd "$REPO_ROOT"
rm -rf docker/app/build || true
mkdir -p docker/app/build
cp src/public/.htaccess docker/app/build/.htaccess
python3 scripts/deployment/generate_env.py docker/prod.env >> docker/app/build/.htaccess

echo "[INFO] .htaccess file generated"

# TODO: if we have other files that need to be built in the future, we can add them here
