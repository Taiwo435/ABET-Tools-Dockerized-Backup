#!/usr/bin/env bash

set -euo pipefail

echo "WARNING: This script will deploy the current state of the repository to the server, overwriting any existing files. Make sure this is stable and ready to be deployed before proceeding. This will also stop docker services on the server, so ensure that this is the right time to deploy."
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
        
        echo "Deploying to $REMOTE..."

        # build .htaccess with .env secrets
        bash "$REPO_ROOT/scripts/deployment/build_files.bash"
        echo "[INFO] .htaccess file built with environment variables."

        # shut down the docker services on the server to prepare for deployment
        # always try to shut down the services, but if it fails (e.g. because they aren't running), continue with the deployment anyway
        ssh -t osburn@"$HOSTNAME" "cd $REMOTE_PATH/docker && docker compose -f docker-compose-prod.yml down"\
            || true 
        echo "[INFO] Docker services stopped."

        # COPY EVERYTHING tracked by git
        git ls-files -z . | rsync -avz --delete --files-from=- --from0 "$REPO_ROOT/" "$REMOTE"
        echo "[INFO] Git-tracked files copied to server."

        # Transfer sensitive files that are not tracked by git
        rsync -avz --delete "$REPO_ROOT/docker/prod.env" "$REMOTE/docker/.env"
        echo "[INFO] Sensitive .env file copied to server."
        rsync -avz --delete "$REPO_ROOT/docker/app/build/.htaccess" "$REMOTE/src/public/.htaccess"
        echo "[INFO] Files copied to server. Setting up server..."

        # Move the files into the correst locations on the server
        # Yes, this removes the abet.asucapstonetools.com directory
        ssh -t osburn@"$HOSTNAME" "rm -rf /home/osburn/public_html/abet.asucapstonetools.com" || true
        ssh -t osburn@"$HOSTNAME" "mv $REMOTE_PATH/src/public /home/osburn/public_html/abet.asucapstonetools.com"

        # start up the docker services on the server
        ssh -t osburn@"$HOSTNAME" "cd $REMOTE_PATH/docker && docker compose -f docker-compose-prod.yml up -d --build"
        echo "[INFO] Docker services started."

        
        echo "[INFO] Deployment complete."
        
        ;;
    *)
        echo "[ERROR] Aborted. Not copying from server."
        exit 1
        ;;
esac

