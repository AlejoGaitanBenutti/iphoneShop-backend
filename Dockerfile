# Imagen base de PHP con Apache
FROM php:8.2-apache

# Instala dependencias necesarias
RUN apt-get update \
    && apt-get install -y unzip curl git zip \
    && docker-php-ext-install pdo pdo_mysql \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Establece el directorio de trabajo en el contenedor
WORKDIR /var/www/html

# Copia certificados SSL antes que el resto para que no se pisen
COPY certificados /var/www/html/certificados

# Copia el resto del proyecto
COPY . .

# Instala dependencias de Composer (si composer.json existe)
RUN composer install --no-dev --optimize-autoloader || true

# Habilita mod_rewrite (útil para URLs amigables en Apache)
RUN a2enmod rewrite

# Expone el puerto 80
EXPOSE 80

# Inicia Apache en primer plano
CMD ["apache2-foreground"]
