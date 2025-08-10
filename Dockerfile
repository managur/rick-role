FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libzip-dev \
    sqlite \
    postgresql-dev \
    mysql-client \
    make

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip \
    && docker-php-ext-enable \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip

# Install PIE (PHP Installer for Extensions)
RUN curl -sSL https://github.com/php/pie/releases/latest/download/pie.phar -o /usr/local/bin/pie \
    && chmod +x /usr/local/bin/pie

# Install Xdebug using PIE for better PHP 8.4 support
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pie install xdebug/xdebug \
    && apk del .build-deps

# Configure Xdebug for coverage only (debug mode disabled for CI)
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=no" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files and install dependencies first for better cache
COPY --chown=rickrole:rickrole composer.json ./
# Skip composer script hooks here because bin/ is not yet copied; run-time scripts are not needed during build
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

# Copy the rest of the application
COPY --chown=rickrole:rickrole . .

# Create non-root user (will be overridden by docker-compose user mapping)
RUN addgroup -g 1000 rickrole && adduser -u 1000 -G rickrole -D rickrole

# Change ownership of /app to rickrole user for write permissions
RUN chown -R rickrole:rickrole /app

# Set user (will be overridden by docker-compose user mapping)
USER rickrole

# Expose port
EXPOSE 9000

CMD ["php-fpm"] 