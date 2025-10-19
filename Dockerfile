FROM serversideup/php:8.3-fpm-apache

USER root

COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d/


RUN install-php-extensions bcmath intl

USER www-data