FROM php:8.4-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libssl-dev pkg-config \
    && docker-php-ext-install pdo_pgsql \
    && pecl install mongodb-2.1.4 \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
COPY php.ini /usr/local/etc/php/conf.d/curso.ini
COPY . /var/www/html/
