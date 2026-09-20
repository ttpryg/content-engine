FROM php:8.1-fpm

# Install system deps and PHP extensions commonly needed
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql mbstring zip gd

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create app directory
WORKDIR /var/www/html

# Copy default php.ini
# COPY php.ini /usr/local/etc/php/php.ini

# Set user and permissions
RUN useradd -G www-data,root -u 1000 -m developer
RUN chown -R developer:developer /var/www/html

USER developer

EXPOSE 9000

CMD ["php-fpm"]
