#/usr/bin/env bash

# This script is used to reload the Apache configuration after making changes to the configuration files.

docker exec -it app apachectl -k graceful