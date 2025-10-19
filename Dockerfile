FROM serversideup/php:8.3-fpm-apache

USER root

RUN install-php-extensions bcmath intl

USER www-data