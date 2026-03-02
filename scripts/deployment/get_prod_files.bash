#!/usr/bin/env bash

set -euo pipefail

echo "WARNING: This script will fetch sensitive production files from the server and copy them to your local repository. Make sure you understand the implications of this before proceeding, as these files may contain secrets or other sensitive information."
read -r -p "Are you sure you want to continue? [y/N]: " response

# for easier to read commands
HOSTNAME=35.148.167.72.host.secureserver.net
# root path on the remote server
REMOTE_PATH="/home/osburn/abet_docker"
# ssh endpoint 
REMOTE="osburn@${HOSTNAME}:${REMOTE_PATH}"
# root path on our local repo
REPO_ROOT=$(git rev-parse --show-toplevel)

case "$response" in
    [yY][eE][sS]|[yY])
        echo "Proceeding..."

        cd "$REPO_ROOT"
        
        echo "Fetching from $REMOTE..."

        # Transfer sensitive files that are not tracked by git
        rsync -avz --delete "$REMOTE/docker/.env" "$REPO_ROOT/docker/prod.env"
        echo "[INFO] Sensitive .env file copied from server."

        echo "[INFO] Files fetched successfully."
        
        ;;
    *)
        echo "[ERROR] Aborted, Operation not completed."
        exit 1
        ;;
esac

