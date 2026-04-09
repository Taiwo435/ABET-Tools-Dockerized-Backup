#!/usr/bin/env bash



# for easier to read commands
HOSTNAME=35.148.167.72.host.secureserver.net
# root path on the remote server
REMOTE_PATH="/home/osburn/abet_docker"
# ssh endpoint 
REMOTE="osburn@${HOSTNAME}:${REMOTE_PATH}"
# root path on our local repo
REPO_ROOT=$(git rev-parse --show-toplevel)


if [ ! -f "$REPO_ROOT/docker/prod.env" ]; then
    echo "docker/prod.env was expected, but it does not exist. Exiting."
    exit 1
fi

scp ${REMOTE}/docker/.env ${REPO_ROOT}/docker/temp.prod.env

echo "=============================================="
echo "Showing Difference in files:"
echo "< marks your changes"
echo "> marks remote env"
echo "=============================================="

diff ${REPO_ROOT}/docker/prod.env ${REPO_ROOT}/docker/temp.prod.env 

if [ $? -eq 0 ]; then
    echo "Files are already the same! Exiting early to save you time!"
    # echo "Removing temp file"
    # rm ${REPO_ROOT}/docker/temp.prod.env
    exit 0
fi

set -euo pipefail
echo "WARNING: This script will replace the remote .env with this .env. Are you sure?"

read -r -p "Are you sure you want to continue? [y/N]: " response

case "$response" in
    [yY][eE][sS]|[yY])
        echo "Proceeding..."

        scp ${REPO_ROOT}/docker/prod.env ${REMOTE}/docker/.env
        
        ;;
    *)
        echo "Aborted. Not copying to the server (or error...)"
        exit 1
        ;;
esac
echo "Removing temp file"
rm ${REPO_ROOT}/docker/temp.prod.env

