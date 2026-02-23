FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    zip \
    unzip 

RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite

# the reverse proxy will handle the SSL, so we don't need to set up certificates here
RUN a2enmod ssl

# change php.ini based on environment variable
RUN if [ "$BUILD_ENV" = "development" ]; then mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; else mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; fi

#RUN mkdir -p /var/www/
WORKDIR /var/www

