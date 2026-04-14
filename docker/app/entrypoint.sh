#!/bin/bash

set -a # automatically export all variables
source "/home/docker/.env" > /dev/null
set +a

cp -rn /var/www/vendor "$ABET_PRIVATE_DIR/vendor"
exec apache2-foreground