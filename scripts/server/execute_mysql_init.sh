#!/usr/bin/env bash

set -eEuo pipefail
trap 'echo "[ERROR] in ${BASH_SOURCE[0]} at line $LINENO: $BASH_COMMAND"' ERR
PARENT_PATH=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )

# similar to load_env(envfile)
set -a # automatically export all variables
source "$PARENT_PATH/../../docker/.env"
set +a

mysql -u "$MYSQL_USER" -p "$MYSQL_PASS" "$MYSQL_DATABASE" < "$PARENT_PATH/../../docker/mysql/init.sql"