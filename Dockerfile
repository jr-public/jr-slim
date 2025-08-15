FROM php:8.2-apache

RUN apt-get update
RUN apt-get install -y --no-install-recommends \
    libpq-dev \
    curl \
    unzip \
    git

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN docker-php-ext-install pdo pdo_pgsql

RUN apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/log/jr-slim && chown www-data:www-data /var/log/jr-slim

RUN a2enmod rewrite
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 80
