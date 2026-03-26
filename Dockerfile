# Folosim imaginea oficiala PHP cu Apache
FROM php:8.2-apache

# Instalam extensiile necesare pentru MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Instalam Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiem tot proiectul in Apache
COPY . /var/www/html/

# Setam folderul de lucru
WORKDIR /var/www/html

# Instalam dependentele Composer
RUN composer install --no-dev --optimize-autoloader

# Permisiuni pentru folderul de imagini
RUN chmod -R 777 /var/www/html/assets/images/products/

# Expunem portul 80
EXPOSE 80