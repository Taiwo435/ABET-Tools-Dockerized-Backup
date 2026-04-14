#!/bin/bash

set -a # automatically export all variables
source "/home/docker/.env" > /dev/null
set +a

install -d -o www-data -g www-data -m 0766 "${ABET_PRIVATE_DIR}/report_jobs" 
install -d -o www-data -g www-data -m 0766 "${ABET_PRIVATE_DIR}/var" 
cp -rn /var/www/vendor "$ABET_PRIVATE_DIR/vendor"
exec apache2-foreground