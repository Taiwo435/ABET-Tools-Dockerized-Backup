
set -eEuxo pipefail
trap 'echo "[ERROR] in ${BASH_SOURCE[0]} at line $LINENO: $BASH_COMMAND"' ERR
PARENT_PATH=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )

# similar to load_env(envfile)
set -a # automatically export all variables
source "$PARENT_PATH/../../docker/.env" > /dev/null
set +a

mysql --user="$MYSQL_USER" --password="$MYSQL_PASS" "$MYSQL_DATABASE" < "$PARENT_PATH/../../scripts/server/drop.sql"