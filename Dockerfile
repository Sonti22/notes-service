FROM node:22-alpine AS frontend
WORKDIR /ui
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM php:8.4-apache AS app
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev libonig-dev libxml2-dev unzip curl \
    && docker-php-ext-install pdo_sqlite mbstring dom xml xmlwriter \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /ui/dist/ ./public/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/notes-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/notes-entrypoint && chmod +x /usr/local/bin/notes-entrypoint
ENTRYPOINT ["notes-entrypoint"]
CMD ["apache2-foreground"]
