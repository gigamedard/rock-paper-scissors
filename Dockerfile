# ==========================================
# ÉTAPE 1 : BUILDER NODE (Compilation Frontend)
# ==========================================
FROM node:20-slim AS frontend-builder

# Définir le dossier de travail
WORKDIR /app

# Copier les fichiers de dépendances Node
COPY package.json package-lock.json ./

# Installer les dépendances NPM (ci permet une installation propre et déterministe)
RUN npm ci

# Copier le reste des fichiers du projet (nécessaire pour Tailwind et Vite)
COPY . .

# Compiler les assets pour la production (génère le dossier public/build)
RUN npm run build


# ==========================================
# ÉTAPE 2 : IMAGE FINALE PHP (Backend)
# ==========================================
FROM serversideup/php:8.3-cli

# Revenir en root pour installer des dépendances système PHP manquantes
USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl unzip libzip-dev libicu-dev libgmp-dev \
    && install-php-extensions gmp pcntl openswoole \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Revenir à l'utilisateur www-data (recommandé par serversideup pour la sécurité)
USER www-data

# Copier les fichiers de base de l'application Laravel
COPY --chown=www-data:www-data . /var/www/html

# Installer les dépendances PHP via Composer (sans les paquets de dev)
# RUN composer install --no-interaction --optimize-autoloader --no-dev

# RÉCUPÉRATION DU FRONTEND : Copier le dossier public/build depuis l'étape 1
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Corriger les permissions pour Laravel (le serveur doit pouvoir écrire dans storage)
RUN chmod -R 775 storage bootstrap/cache