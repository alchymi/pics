# Dockerfile
FROM php:8.2-apache

# PHP extensions utiles si besoin plus tard (gd pour images, mbstring…)
RUN apt-get update && apt-get install -y libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install gd \
 && rm -rf /var/lib/apt/lists/*

# Config Apache
RUN a2enmod rewrite headers

# Copie du code
COPY public/ /var/www/html/
COPY php.ini /usr/local/etc/php/conf.d/zz-custom.ini

# Dossier d'upload
RUN mkdir -p /var/www/html/pictures \
 && chown -R www-data:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} \; \
 && find /var/www/html -type f -exec chmod 644 {} \;

# Port interne
EXPOSE 80

# Healthcheck simple
HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD curl -fsS http://localhost/ || exit 1