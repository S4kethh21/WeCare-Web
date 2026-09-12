# WeCare Hospital — Production Dockerfile
# Based on official PHP 8.2 with Apache web server
FROM php:8.2-apache

# 1. Install and enable required MySQL and PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo pdo_mysql

# 2. Enable essential Apache modules (mod_rewrite, mod_headers, mod_mime)
RUN a2enmod rewrite headers mime

# 3. Configure production PHP settings
RUN { \
    echo 'session.save_path="/tmp"'; \
    echo 'session.gc_maxlifetime=86400'; \
    echo 'session.cookie_httponly=1'; \
    echo 'upload_max_filesize=20M'; \
    echo 'post_max_size=25M'; \
    echo 'memory_limit=256M'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'date.timezone="UTC"'; \
} > /usr/local/etc/php/conf.d/wecare.ini

# 4. Copy project files into Apache web root
WORKDIR /var/www/html
COPY . /var/www/html/

# 5. Set correct web server permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 6. Setup dynamic port entrypoint (Render, Railway, Fly.io, Cloud Run)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
