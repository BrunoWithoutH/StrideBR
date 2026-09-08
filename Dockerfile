FROM postgres:17-alpine AS migrations
WORKDIR /workspace
COPY scripts/migrate_product.sh ./scripts/migrate_product.sh
COPY src/database ./src/database
ENTRYPOINT ["sh", "./scripts/migrate_product.sh"]
CMD ["apply"]

FROM php:8.4-apache AS app
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev libicu-dev libcurl4-openssl-dev libpng-dev libjpeg62-turbo-dev libwebp-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql mbstring intl curl gd \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
COPY . .
COPY docker/apache.conf /etc/apache2/conf-available/stridebr-public.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/stridebr.ini
COPY docker/app-entrypoint.sh /usr/local/bin/stridebr-entrypoint
RUN a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && a2enconf stridebr-public \
    && mkdir -p public/uploads \
    && chown www-data:www-data public/uploads \
    && chmod 755 /usr/local/bin/stridebr-entrypoint
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 CMD php -r 'exit(@file_get_contents("http://127.0.0.1/health.php") === "OK\n" ? 0 : 1);'
ENTRYPOINT ["stridebr-entrypoint"]
CMD ["apache2-foreground"]
