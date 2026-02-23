FROM php:8.4-fpm

# Install system dependencies (same as php service)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    cron \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create non-root user (same as php service)
RUN useradd -ms /bin/bash symfony \
    && chown -R symfony:symfony /var/www/html

# Install crontab for symfony user
COPY docker/cron/crontab /tmp/crontab
RUN crontab -u symfony /tmp/crontab && rm /tmp/crontab

# Cron must run as root to manage user crontabs
CMD ["cron", "-f"]
