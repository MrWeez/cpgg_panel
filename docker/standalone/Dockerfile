FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    nano \
    curl \
    git \
    libcurl4-openssl-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo \
        pdo_mysql \
        intl \
        zip \
        gd \
        bcmath \
    && rm -rf /tmp/* /var/cache/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Docker defaults so compose works with zero vars set.
# Installer-typed credentials live in .env file, these only fix service hostnames.
ENV DB_HOST=mariadb \
    DB_PORT=3306 \
    REDIS_HOST=redis \
    REDIS_PORT=6379 \
    TRUSTED_PROXIES="*"

# Create directory for application
RUN mkdir -p /var/www/html

# Create user and group for Laravel
RUN groupadd -g 1000 laravel \
    && useradd -u 1000 -g laravel -ms /bin/bash laravel \
    && chown -R laravel:laravel /var/www/html

# Copy application files, baked directly, no lazy copy at boot
COPY --chown=laravel:laravel . /var/www/html

# Pristine copy, only used to seed an empty ./panel bind mount (see startup.sh).
# Regular boot serves /var/www/html straight from the image.
COPY --chown=laravel:laravel . /var/panel-seed

# Copy PHP-FPM configuration
COPY --chown=laravel:laravel ./docker/standalone/php/www.conf /usr/local/etc/php-fpm.d/

# Copy Nginx configuration
COPY --chown=laravel:laravel ./docker/standalone/nginx/nginx.conf /etc/nginx/nginx.conf
# Create directory for Nginx logs
RUN mkdir -p /var/log/nginx && chown -R laravel:laravel /var/log/nginx

# Expose plain HTTP only, TLS terminates at reverse proxy / tunnel
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/up || curl -f http://localhost/ || exit 1

# Copy startup script
COPY --chown=laravel:laravel ./docker/standalone/scripts/startup.sh /usr/local/bin/startup-script.sh
# Make startup script executable
RUN chmod +x /usr/local/bin/startup-script.sh

# Set the working directory
WORKDIR /var/www/html

RUN chown -R laravel:laravel /var/www/html

# Start the startup script
CMD ["/usr/local/bin/startup-script.sh"]
