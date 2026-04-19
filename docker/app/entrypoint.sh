#!/bin/bash

set -a # automatically export all variables
source "/home/docker/.env" > /dev/null
set +a

install -d -o www-data -g www-data -m 0777 "${ABET_PRIVATE_DIR}/report_jobs" 
install -d -o www-data -g www-data -m 0777 "${ABET_PRIVATE_DIR}/var" 
cp -rn /var/www/vendor "$ABET_PRIVATE_DIR/vendor"
if [ "$APP_ENV" = "dev" ]; then
    chown --recursive 1000:1000 vendor
fi
exec apache2-foreground