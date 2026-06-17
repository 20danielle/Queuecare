FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev \
    && docker-php-ext-install pdo pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

EXPOSE 80
CMD ["sh", "-lc", "php -S 0.0.0.0:${PORT:-80} -t /var/www/html/www /var/www/html/router.php"]
