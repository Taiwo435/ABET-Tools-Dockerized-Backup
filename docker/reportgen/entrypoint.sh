#!/bin/sh
set -e

echo "Running seed_programs.py..."
python /usr/src/app/seed_programs.py

echo "Starting main process..."
exec "$@"