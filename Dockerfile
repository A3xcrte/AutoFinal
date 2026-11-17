FROM php:8.2-apache

# Extensión de MySQL, y permitir los .htaccess que protegen las carpetas
RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Límites de subida de fotos
RUN printf   'upload_max_filesize=20M\npost_max_size=40M\nmax_file_uploads=10\n' > /usr/local/etc/php/conf.d/uploads.ini

# Al iniciar: asegura que uploads y storage se puedan escribir
RUN printf '#!/bin/sh\nmkdir -p /var/www/html/uploads /var/www/html/storage\nchmod -R 777 /var/www/html/uploads /var/www/html/storage 2>/dev/null || true\nexec apache2-foreground\n' > /usr/local/bin/entrypoint.sh \
 && chmod +x /usr/local/bin/entrypoint.sh

CMD ["/usr/local/bin/entrypoint.sh"]
