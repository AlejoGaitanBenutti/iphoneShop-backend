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

# Copia los archivos de la app al contenedor
COPY . .

# Instala las dependencias de Composer
RUN composer install --no-dev --optimize-autoloader || true

# Habilita mod_rewrite de Apache (si usás URLs amigables)
RUN a2enmod rewrite

# Expone el puerto 80
EXPOSE 80

# Comando para iniciar Apache en primer plano
CMD ["apache2-foreground"]