FROM php:8.2-apache

# Copiar todos los archivos al contenedor
COPY . /var/www/html/

# Habilitar mod_rewrite (útil si usás .htaccess)
RUN a2enmod rewrite

# Permitir acceso a .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Puerto expuesto (Render lo usa automáticamente)
EXPOSE 80
