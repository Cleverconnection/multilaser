FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www

