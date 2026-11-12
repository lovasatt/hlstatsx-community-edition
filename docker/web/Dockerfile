FROM php:8.4-apache

RUN export DEBIAN_FRONTEND=noninteractive && \
    apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    unzip \
    git \
    cron \
    tzdata \
    && apt-get clean \
    && apt-get autoclean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/cache/apt/archives/*

RUN sed -i '/pam_loginuid.so/s/^/#/' /etc/pam.d/cron

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo \
        pdo_mysql \
        zip \
        gd \
        bcmath \
        intl \
        soap \
        sockets \
        mbstring \
        curl \
        xml

ENV APACHE_DOCUMENT_ROOT /var/www
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN sed -i '/<Directory \/var\/www/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN a2enmod rewrite headers

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    sed -i 's/display_errors = On/display_errors = Off/g' "$PHP_INI_DIR/php.ini" && \
    sed -i 's/display_startup_errors = On/display_startup_errors = Off/g' "$PHP_INI_DIR/php.ini"

COPY src/web /var/www/
RUN find /var/www -type f -name "*.php" -exec sed -i 's/\r$//' {} + && \
    chown -R www-data:www-data /var/www/ \
    && chmod -R 755 /var/www

COPY src/heatmaps /opt/heatmaps/
COPY docker/web/hlx-heatmap-cron /etc/cron.d/hlx-heatmap-cron

RUN find /opt/heatmaps -type f \( -name "*.php" -o -name "*.sh" \) -exec sed -i 's/\r$//' {} + && \
    sed -i 's/\r$//' /etc/cron.d/hlx-heatmap-cron && \
    chmod 0644 /etc/cron.d/hlx-heatmap-cron && \
    mkdir -p /opt/heatmaps/cache && \
    chown -R www-data:www-data /opt/heatmaps && \
    chmod -R 755 /opt/heatmaps

WORKDIR /var/www/

CMD sed -i "s|defineVar(\"DB_ADDR\", '[^']*');|defineVar(\"DB_ADDR\", 'hlx-db');|g" /var/www/config.php && \
    sed -i "s|defineVar(\"DB_USER\", '[^']*');|defineVar(\"DB_USER\", '$DB_USER');|g" /var/www/config.php && \
    sed -i "s|defineVar(\"DB_PASS\", '[^']*');|defineVar(\"DB_PASS\", '$DB_PASS');|g" /var/www/config.php && \
    sed -i "s|defineVar(\"DB_NAME\", '[^']*');|defineVar(\"DB_NAME\", '$DB_NAME');|g" /var/www/config.php && \
    sed -i "s|defineVar(\"HLX_TRUSTED_PROXIES\", '[^']*');|defineVar(\"HLX_TRUSTED_PROXIES\", '$HLX_TRUSTED_PROXIES');|g" /var/www/config.php && \
    sed -i "s|name=\"masterserver\" value=\"[^\"]*\"|name=\"masterserver\" value=\"$DAEMON_HOST\"|g" /var/www/pages/admintasks/tools_perlcontrol.php && \
    sed -i "s|defineVar('DB_HOST',\s*'[^']*');|defineVar('DB_HOST', 'hlx-db');|g" /opt/heatmaps/config.inc.php && \
    sed -i "s|defineVar('DB_USER',\s*'[^']*');|defineVar('DB_USER', '$DB_USER');|g" /opt/heatmaps/config.inc.php && \
    sed -i "s|defineVar('DB_PASS',\s*'[^']*');|defineVar('DB_PASS', '$DB_PASS');|g" /opt/heatmaps/config.inc.php && \
    sed -i "s|defineVar('DB_NAME',\s*'[^']*');|defineVar('DB_NAME', '$DB_NAME');|g" /opt/heatmaps/config.inc.php && \
    sed -i "s|defineVar('HLXCE_WEB',\s*'[^']*');|defineVar('HLXCE_WEB', '/var/www');|g" /opt/heatmaps/config.inc.php && \
    sed -i "s|defineVar('HUD_URL',\s*'[^']*');|defineVar('HUD_URL', '${HUD_URL:-http://localhost}');|g" /opt/heatmaps/config.inc.php && \
    chown www-data:www-data /var/www/config.php /opt/heatmaps/config.inc.php && \
    cron && \
    exec apache2-foreground