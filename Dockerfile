FROM php:8.2-apache

RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
 && a2enmod mpm_prefork

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/www|g' \
        /etc/apache2/sites-available/000-default.conf \
 && sed -i 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf

RUN echo '<Directory /var/www/html/www>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN mkdir -p /var/www/html/www/web/public/qrcodes \
             /var/www/html/www/web/public/uploads/medecins \
             /var/www/html/www/download \
 && chown -R www-data:www-data /var/www/html/www

WORKDIR /var/www/html