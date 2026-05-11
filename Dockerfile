FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

FROM php:8.4-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/app/public

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends openssl libicu-dev libzip-dev \
    && docker-php-ext-install pdo_mysql intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite headers expires \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf 'Listen 8080\n' > /etc/apache2/ports.conf \
    && install -d -m 0755 -o www-data -g www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/starter-back-entrypoint

RUN chmod +x /usr/local/bin/starter-back-entrypoint \
    && mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data /app /var/run/apache2 /var/lock/apache2 /var/log/apache2

EXPOSE 8080

ENTRYPOINT ["starter-back-entrypoint"]
CMD ["apache2-foreground"]
