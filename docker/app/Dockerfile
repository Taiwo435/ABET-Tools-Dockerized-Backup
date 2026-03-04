FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    python3 \
    python3-pip \
    libreoffice-writer \
    && rm -rf /var/lib/apt/lists/*

RUN pip3 install --no-cache-dir --break-system-packages \
    python-docx \
    python-dotenv \
    openai

RUN docker-php-ext-install pdo pdo_mysql

# the reverse proxy will handle the SSL, so we don't need to set up certificates here
RUN a2enmod rewrite
RUN a2enmod headers
RUN a2enmod proxy
RUN a2enmod proxy_http

COPY config/000-default.conf /etc/apache2/sites-enabled/000-default.conf

#RUN if [ "$BUILD_ENV" = "development" ]; then echo "Development environment"; else echo "Production environment"; fi

# change php.ini based on environment variable
RUN if [ "$BUILD_ENV" = "development" ]; then mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; else mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; fi


#RUN mkdir -p /var/www/
WORKDIR /var/www

