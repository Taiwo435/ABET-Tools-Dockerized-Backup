#!/usr/bin/env bash

set -eEuo pipefail
trap 'echo "[ERROR] in ${BASH_SOURCE[0]} at line $LINENO: $BASH_COMMAND"' ERR

echo "WARNING: This script will upload the local scripts directory to the server, potentially overwriting other people's sciripts. "
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
        
        echo "Copying scripts to $REMOTE..."

        # Transfer scripts into remote scripts directory
        rsync -avz --delete "$REPO_ROOT/scripts/" "$REMOTE/scripts"
        echo "[INFO] Copied scripts to server."
        
        ;;
    *)
        echo "[INFO] Aborted. Not copying from server."
        exit 1
        ;;
esac

