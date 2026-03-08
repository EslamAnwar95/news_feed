FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    bash \
    nodejs \
    npm \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    zip \
    libzip-dev \
    unzip \
    $PHPIZE_DEPS

# Install PHP extensions including Redis
RUN docker-php-ext-install pdo pdo_mysql bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]