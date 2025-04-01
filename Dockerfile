FROM php:8.2-apache

# Instalar extensiones necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Copiar tu código PHP
COPY . /var/www/html/

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Permitir uso de .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 80
