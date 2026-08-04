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

# Démarrage :
# - recrée le lien symbolique public/storage -> storage/app/public à chaque démarrage
#   (nécessaire pour que les photos de profil et autres fichiers publics soient servis ;
#   le dépôt contient un dossier public/storage vide qui doit être remplacé par le lien) ;
# - lance le planificateur Laravel (`schedule:work`) en tâche de fond pour que les
#   commandes programmées (rappels de planning, alertes de seuil) s'exécutent réellement,
#   ce qui n'était pas le cas auparavant faute de tout déclencheur de `schedule:run` ;
# - lance enfin le serveur HTTP avec la bonne interprétation du port dynamique de Render.
CMD ["sh", "-c", "rm -rf public/storage && php artisan storage:link && (php artisan schedule:work &) && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
