FROM php:8.5.12-fpm

# Install system dependencies. No opcache: PHP 8.5 builds it in, and installing it fails the build.
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        intl \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

# Set working directory
WORKDIR /var/www/html

# Create non-root user
RUN useradd -ms /bin/bash symfony \
    && chown -R symfony:symfony /var/www/html

# Configure PHP-FPM to run as symfony user
RUN sed -i 's/www-data/symfony/g' /usr/local/etc/php-fpm.d/www.conf

USER symfony

EXPOSE 9000

CMD ["php-fpm"]
