FROM php:8.4-apache
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    libxml2-dev libicu-dev unzip && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo_mysql zip gd bcmath intl soap
RUN a2enmod rewrite
COPY src/web /var/www/html/
RUN sed -i "s/'localhost'/'db'/g" /var/www/html/config.php
RUN chown -R www-data:www-data /var/www/html/