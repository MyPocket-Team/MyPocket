# Étape 1 : Compilation des dépendances avec Composer
FROM composer:latest AS vendor
WORKDIR /app

# Copie des fichiers de configuration
COPY composer.json composer.lock* ./

# Installation en mode production pure
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# Étape 2 : Image finale de l'application
FROM php:8.2-fpm

# Installation des dépendances système (ffmpeg compris)
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

# Copie du code source et des dépendances
COPY . .
COPY --from=vendor /app/vendor/ /var/www/vendor/

EXPOSE 8000

# Démarrage avec la bonne interprétation du port dynamique de Render
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
