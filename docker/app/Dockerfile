FROM composer:2 as vendor

WORKDIR /app

COPY ../../src/abet_private .

RUN composer install --no-dev --prefer-dist --no-interaction

################################3
# ORIGNAL BUILD STEP
################################
FROM php:8.3-apache 

ARG ABET_PRIVATE_DIR
ENV ABET_PRIVATE_DIR=$ABET_PRIVATE_DIR

WORKDIR $ABET_PRIVATE_DIR

COPY --from=vendor /app/vendor $ABET_PRIVATE_DIR/vendor

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    curl \ 
    zip \
    unzip 

# Stuff I want to eventually get rid of
RUN apt-get install -y \
    python3 \
    python3-pip \
    libreoffice-writer


RUN apt-get clean && rm -rf /var/lib/apt/lists/* 

RUN pip3 install --no-cache-dir --break-system-packages \
    python-docx \
    python-dotenv \
    mysql-connector-python \
    openai

RUN docker-php-ext-install pdo pdo_mysql

# the reverse proxy will handle the SSL, so we don't need to set up certificates here
RUN a2enmod rewrite
RUN a2enmod headers
RUN a2enmod proxy
RUN a2enmod proxy_http

COPY docker/app/config/000-default.conf /etc/apache2/sites-enabled/000-default.conf

#RUN if [ "$BUILD_ENV" = "development" ]; then echo "Development environment"; else echo "Production environment"; fi

# change php.ini based on environment variable
RUN if [ "$BUILD_ENV" = "development" ]; then mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; else mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; fi


#RUN mkdir -p /var/www/
WORKDIR /var/www
