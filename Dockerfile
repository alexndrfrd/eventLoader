# Use PHP 8.5 with FPM
FROM php:8.5-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    icu-libs \
    oniguruma-dev \
    postgresql-dev \
    postgresql-libs \
    linux-headers \
    autoconf \
    dpkg-dev \
    dpkg \
    file \
    g++ \
    gcc \
    libc-dev \
    make \
    pkgconf \
    re2c \
    $PHPIZE_DEPS

# Install PHP extensions
# pdo_pgsql (PostgreSQL driver)
RUN docker-php-ext-install pdo_pgsql

# bcmath (for arbitrary precision mathematics)
RUN docker-php-ext-install bcmath

# intl (requires icu configuration)
RUN docker-php-ext-configure intl && \
    docker-php-ext-install intl

# zip (requires libzip configuration)
RUN docker-php-ext-configure zip && \
    docker-php-ext-install zip

# Note: opcache is already compiled into PHP 8.5, no need to enable it
# It's configured via php.ini (see docker/php/php.ini)

# Install Xdebug for development
RUN pecl install xdebug-3.5.0 && \
    docker-php-ext-enable xdebug

# Configure Xdebug
RUN echo "xdebug.mode=coverage,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/symfony

# Copy application files
COPY . /var/www/symfony

# Set permissions
RUN chown -R www-data:www-data /var/www/symfony

# Expose port 9000
EXPOSE 9000

CMD ["php-fpm"]
