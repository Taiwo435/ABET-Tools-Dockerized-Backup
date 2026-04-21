#!/bin/bash

set -a # automatically export all variables
source "/home/docker/.env" > /dev/null
set +a

if [ "$APP_ENV" = "prod" ]; then
    echo "[ERROR]: Apache server not meant to run on prod!"
    exit
fi

# only runs on dev and test anyways
install -d -o 1000 -g www-data -m 0777 "${ABET_PRIVATE_DIR}/report_jobs" 
install -d -o 1000 -g www-data -m 0777 "${ABET_PRIVATE_DIR}/var" 
cp -r /var/www/vendor/. "$ABET_PRIVATE_DIR/vendor"
chown --recursive 1000:33 vendor

exec apache2-foreground