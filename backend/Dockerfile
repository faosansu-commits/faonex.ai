FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev default-mysql-client libzip-dev libpng-dev libjpeg-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install curl pdo_mysql zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite

COPY composer.json /var/www/html/composer.json
RUN composer install --no-dev --optimize-autoloader --no-interaction --working-dir=/var/www/html

COPY public /var/www/html/public
COPY src /var/www/html/src
COPY fonts /var/www/fonts

# post_max_size ต้องรองรับรูปภาพที่แนบมาเป็น base64 ใน JSON body
# Timeout ของ Apache ต้องยาวพอสำหรับการดาวน์โหลดโมเดล AI ตอนเลือกใช้ครั้งแรก (อาจใช้เวลาหลายนาที)
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\nTimeout 1800\n' > /etc/apache2/conf-available/app.conf \
    && a2enconf app \
    && printf 'post_max_size=24M\nupload_max_filesize=24M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/app-uploads.ini \
    && printf 'output_buffering=Off\nzlib.output_compression=Off\nimplicit_flush=On\n' > /usr/local/etc/php/conf.d/app-streaming.ini \
    && mkdir -p /var/www/fontcache \
    && chown -R www-data:www-data /var/www/fontcache

EXPOSE 80
