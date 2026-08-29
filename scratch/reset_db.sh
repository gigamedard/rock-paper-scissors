#!/bin/sh
export APP_KEY="$(cat /run/secrets/app_key)"
export DB_PASSWORD="$(cat /run/secrets/db_app_password)"
export DB_APP_PASSWORD="$(cat /run/secrets/db_app_password)"
export MYSQL_PASSWORD="$(cat /run/secrets/mysql_password)"
export INTERNAL_API_SECRET="$(cat /run/secrets/internal_api_secret)"
export REVERB_APP_KEY="$(cat /run/secrets/reverb_app_key)"
export REVERB_APP_SECRET="$(cat /run/secrets/reverb_app_secret)"
php artisan migrate:fresh --seed --force