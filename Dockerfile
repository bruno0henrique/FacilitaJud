FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.5-apache AS base
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev libzip-dev libicu-dev libsodium-dev libcurl4-openssl-dev python3 ca-certificates unzip \
    && docker-php-ext-install pdo_pgsql mbstring zip intl sodium bcmath curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
RUN a2enmod deflate
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM base AS backend
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN rm -f /app/bootstrap/cache/*.php \
    && composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi

FROM base AS runtime
COPY --from=backend /app /app
COPY --from=frontend /app/public/build /app/public/build
COPY --from=frontend /app/public/fonts /app/public/fonts
COPY docker/php-performance.ini /usr/local/etc/php/conf.d/facilitajud-performance.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/facilitajud-entrypoint
RUN chmod +x /usr/local/bin/facilitajud-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private \
    && chown -R www-data:www-data storage bootstrap/cache
ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["facilitajud-entrypoint"]
CMD ["apache2-foreground"]
