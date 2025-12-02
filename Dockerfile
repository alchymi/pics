FROM php:8.2-apache

# Install required libs for GD
RUN apt-get update && apt-get install -y \
    libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install gd \
 && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy app
COPY public/ /var/www/html/
COPY php.ini /usr/local/etc/php/conf.d/zz-custom.ini

# Create upload folders
RUN mkdir -p /var/www/html/pictures \
    /var/www/html/thumbs \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html

# Declare persistent folders
VOLUME ["/var/www/html/pictures", "/var/www/html/thumbs"]

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
 CMD curl -fsS http://localhost/ || exit 1