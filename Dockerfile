FROM php:8.2-apache

RUN apt-get update && apt-get install -y git unzip zip \
    && docker-php-ext-install pdo pdo_mysql mysqli

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Creaza folderul daca nu exista si seteaza permisiuni
RUN mkdir -p /var/www/html/assets/images/products/ \
    && chmod -R 777 /var/www/html/assets/images/products/

EXPOSE 80