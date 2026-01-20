FROM php:8.3-cli

# Install system dependencies and PHP extensions required by Laravel
RUN apt-get update \
    && apt-get install -y \
       git \
       unzip \
       libzip-dev \
       libonig-dev \
       libxml2-dev \
       libpng-dev \
       libjpeg-dev \
       libfreetype6-dev \
       libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mbstring xml zip gd curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Copy dependency definition and install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

# Copy the rest of the application source
COPY . .

# Set proper permissions
RUN chmod -R 755 storage bootstrap/cache || true

# Remove .env if it exists (will be replaced by Docker environment variables)
RUN rm -f .env || true

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
