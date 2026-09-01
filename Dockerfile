FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install bcmath intl mbstring pdo_mysql xml zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/lafim-entrypoint

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x /usr/local/bin/lafim-entrypoint

EXPOSE 80

ENTRYPOINT ["lafim-entrypoint"]
