FROM php:8.3-apache
# PHP 8 понимает caching_sha2_password — способ входа по умолчанию в MySQL 8
RUN docker-php-ext-install pdo_mysql
COPY ./app/ /var/www/html/
COPY ./index.html /var/www/html/index.html
# Приложение отдаётся по HTTP на 80-м порту: так его ждут Service и
# readinessProbe в app-deployment.yaml, а HTTPS при необходимости
# добавляется снаружи (Ingress / балансировщик).
EXPOSE 80
