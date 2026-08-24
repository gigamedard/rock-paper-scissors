#!/bin/bash
# db-entrypoint.sh — Reads MySQL root password from Docker secret (/run/secrets/mysql_password)
# and passes it to the official MySQL entrypoint via MYSQL_ROOT_PASSWORD env var.
#
# This avoids putting the password in docker-compose.yml's `environment:` block
# (which would be visible via `docker inspect`). The secret is mounted as a file
# by Docker Compose `secrets:` and read here at startup time.

if [ -f /run/secrets/mysql_password ]; then
    export MYSQL_ROOT_PASSWORD="$(cat /run/secrets/mysql_password)"
else
    echo "❌ ERROR: /run/secrets/mysql_password not found. Mount it via docker-compose secrets:" >&2
    echo "   secrets:" >&2
    echo "     - mysql_password" >&2
    exit 1
fi

# Execute the official MySQL entrypoint with the password now set in env
exec docker-entrypoint.sh "$@"