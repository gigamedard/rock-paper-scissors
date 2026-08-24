#!/bin/sh
# security-entrypoint.sh — Reads Docker secrets from /run/secrets/ and exports them
# as environment variables before starting the application. This keeps secrets
# out of .env.staging (which is loaded via env_file and visible in docker inspect).
#
# Secret files are mounted by docker-compose `secrets:` block. Each secret file
# contains the raw value (no newline). The entrypoint maps them to env vars.

# Map Docker secret files to environment variables
if [ -f /run/secrets/app_key ]; then
    export APP_KEY="$(cat /run/secrets/app_key)"
fi
if [ -f /run/secrets/mysql_password ]; then
    export MYSQL_PASSWORD="$(cat /run/secrets/mysql_password)"
fi
if [ -f /run/secrets/db_app_password ]; then
    export DB_PASSWORD="$(cat /run/secrets/db_app_password)"
    export DB_APP_PASSWORD="$(cat /run/secrets/db_app_password)"
fi
if [ -f /run/secrets/internal_api_secret ]; then
    export INTERNAL_API_SECRET="$(cat /run/secrets/internal_api_secret)"
fi
if [ -f /run/secrets/reverb_app_key ]; then
    export REVERB_APP_KEY="$(cat /run/secrets/reverb_app_key)"
fi
if [ -f /run/secrets/reverb_app_secret ]; then
    export REVERB_APP_SECRET="$(cat /run/secrets/reverb_app_secret)"
fi

# Execute the original command
exec "$@"