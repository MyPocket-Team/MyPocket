FROM php:8.2-cli

# Dépendances système + ffmpeg (indispensable pour le traitement audio IA)
RUN apt-get update && apt-get install -y \
    git curl zip unzip ffmpeg \
    libpng-dev libonig-dev libxml2-dev libzip-dev default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --no-scripts --ignore-platform-reqs --no-interaction

RUN php artisan config:clear

EXPOSE 8000

CMD php artisan migrate --force && php artisan storage:link --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
