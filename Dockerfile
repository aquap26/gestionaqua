FROM php:8.2-apache

# Instalar extensiones necesarias de PHP
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

WORKDIR /var/www/html
