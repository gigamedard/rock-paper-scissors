FROM serversideup/php:8.3-fpm-nginx

# Installer les dépendances système nécessaires
RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl unzip libzip-dev libicu-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copier les fichiers de l'application
COPY --chown=www-data:www-data . /var/www/html

# Installer les dépendances PHP
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Note: We do NOT run config:cache here because .env variables are not available at build time.
# The serversideup/php image handles caching on startup if configured.

# Donner les bonnes permissions au dossier storage (Crucial)
RUN chmod -R 775 storage bootstrap/cache