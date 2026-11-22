# Panteen Rent: PHP 8.3 + Apache. The database runs as a separate container (see docker-compose.yml).
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" gd pdo_mysql opcache \
 && apt-get purge -y --auto-remove libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
 && apt-get install -y --no-install-recommends libjpeg62-turbo libpng16-16 libwebp7 libfreetype6 \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite expires headers remoteip

COPY docker/php.ini /usr/local/etc/php/conf.d/panteen.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/panteen-entrypoint
RUN chmod +x /usr/local/bin/panteen-entrypoint

WORKDIR /var/www/panteen
COPY --chown=www-data:www-data . .
RUN mkdir -p storage/cache storage/condition storage/logs storage/slips \
 && chown -R www-data:www-data storage public/media

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 CMD php -r 'exit(@file_get_contents("http://127.0.0.1/healthz") === "ok" ? 0 : 1);'
ENTRYPOINT ["panteen-entrypoint"]
CMD ["apache2-foreground"]
