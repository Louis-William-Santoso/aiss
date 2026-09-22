FROM php:8.4-fpm-alpine

# Ekstensi yang dibutuhkan untuk koneksi MariaDB lewat PDO
RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

EXPOSE 9000