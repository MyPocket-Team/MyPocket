# ==========================================
# Étape 1 : Installation des dépendances avec Composer
# ==========================================
FROM composer:latest AS vendor
WORKDIR /app

# Copie uniquement des fichiers de configuration Composer pour optimiser le cache Docker
COPY composer.json composer.lock* ./

# Installation propre, sans limite de mémoire et sans vérifier les extensions locales
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# ==========================================
# Étape 2 : Image finale de l'application
# ==========================================
FROM php:8.2-fpm

# Installation des dépendances système (incluant ffmpeg pour l'audio) et extensions PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    ffmpeg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

# Copie de tout le code source de l'application
COPY . .

# Injection du dossier vendor pré-compilé lors de l'Étape 1
COPY --from=vendor /app/vendor/ /var/www/vendor/

# Exposition du port
EXPOSE 8000

# Commande de démarrage du serveur Laravel
CMD php artisan serve --host=0.0.0.0 --port=8000
