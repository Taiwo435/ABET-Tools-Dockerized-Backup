#!/usr/bin/env bash

set -euo pipefail

echo "WARNING: This script will deploy the current state of the repository to the server, overwriting any existing files. Make sure this is stable and ready to be deployed before proceeding."
read -r -p "Are you sure you want to continue? [y/N]: " response

HOSTNAME=35.148.167.72.host.secureserver.net
REMOTE="osburn@${HOSTNAME}:/home/osburn/abet_docker"
REPO_ROOT=$(git rev-parse --show-toplevel)

case "$response" in
    [yY][eE][sS]|[yY])
        echo "Proceeding..."

        cd "$REPO_ROOT"
        
        echo "Deploying to $REMOTE..."

        # COPY DATABASE CONFIG FILES
        git ls-files -z . | rsync -avz --delete --files-from=- --from0 \
        ./ "$REMOTE"
        
        echo "Deployment complete."
        
        ;;
    *)
        echo "Aborted. Not copying from server."
        exit 1
        ;;
esac

