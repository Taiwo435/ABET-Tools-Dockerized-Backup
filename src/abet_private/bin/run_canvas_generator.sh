#!/usr/bin/env bash
# Note from Danny: I have no idea what this script is even for
set -euo pipefail

# Note: we should NOT hardcode the path to abet_private. 
SCRIPT_DIR="/home/osburn/abet_private/canvas_tools"
PYTHON_BIN="/opt/python38/bin/python3.8"

echo "Wrapper starting..."
echo "Working dir: ${SCRIPT_DIR}"

# Required env vars from PHP backend (do not print token)
: "${canvas_access_token:?Missing canvas_access_token}"
: "${CANVAS_SOURCE_COURSE_ID:?Missing CANVAS_SOURCE_COURSE_ID}"
: "${CANVAS_DEST_COURSE_ID:?Missing CANVAS_DEST_COURSE_ID}"
: "${CANVAS_SEMESTER:?Missing CANVAS_SEMESTER}"
: "${CANVAS_YEAR:?Missing CANVAS_YEAR}"

export HOME="${HOME:-/home/osburn}"
export PATH="/usr/local/bin:/usr/bin:/bin:${PATH:-}"
export PYTHONUNBUFFERED=1

cd "${SCRIPT_DIR}"

echo "Python executable: ${PYTHON_BIN}"
echo "Python version:"
"${PYTHON_BIN}" --version

echo "Running create_modules.py..."
"${PYTHON_BIN}" create_modules.py