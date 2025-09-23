# PHP + Apache image for 3DCart → NetSuite integration
FROM php:8.1-apache-bookworm

# Enable Apache modules
RUN a2enmod rewrite

# Install system libs and PHP extensions required by the project (using HTTPS apt sources)
RUN set -eux; \
  if [ -f /etc/apt/sources.list ]; then \
    sed -i -e 's|http://deb.debian.org|https://deb.debian.org|g' \
           -e 's|http://security.debian.org|https://security.debian.org|g' /etc/apt/sources.list; \
  fi; \
  if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
    sed -i -E 's|http://|https://|g' /etc/apt/sources.list.d/debian.sources; \
  fi; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libgmp-dev; \
  docker-php-ext-configure intl; \
  docker-php-ext-configure gd --with-freetype --with-jpeg; \
  docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    intl \
    gd \
    gmp; \
  rm -rf /var/lib/apt/lists/*

# Set Apache DocumentRoot to /var/www/html/public and allow .htaccess overrides
RUN set -eux; \
  sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' /etc/apache2/sites-available/000-default.conf; \
  printf "<Directory /var/www/html/>\n    AllowOverride All\n    Require all granted\n</Directory>\n" > /etc/apache2/conf-available/root.conf; \
  a2enconf root

# Workdir
WORKDIR /var/www/html

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
  && rm composer-setup.php

# Install PHP dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

# Copy the rest of the application
COPY . .

# Ensure writable directories
RUN mkdir -p logs uploads \
  && chown -R www-data:www-data logs uploads

# Expose Apache port (in-container)
EXPOSE 80

# Apache is the default CMD in this base image