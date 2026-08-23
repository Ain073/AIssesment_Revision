FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip curl zip libzip-dev libpng-dev libonig-dev \
    nodejs npm \
    && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_PROCESS_TIMEOUT=2000

COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    || composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    || composer install --no-dev --prefer-source --no-interaction --no-progress --optimize-autoloader
RUN npm install
RUN npm run build

EXPOSE 10000

CMD php artisan migrate --force && php artisan db:seed --force && (php artisan storage:link || true) && php artisan optimize && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
