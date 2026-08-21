FROM php:8.2-apache

RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite \
    && docker-php-ext-install pdo_mysql mysqli

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
