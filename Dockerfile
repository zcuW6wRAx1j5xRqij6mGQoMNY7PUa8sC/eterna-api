############################################
# Base Image
############################################

FROM serversideup/php:8.4-fpm-apache AS base

USER root

# Install required PHP extensions
RUN install-php-extensions bcmath intl

WORKDIR /var/www/html

############################################
# Development Image
############################################
FROM base AS development

ARG USER_ID
ARG GROUP_ID

# Switch to root so we can set the user ID and group ID
USER root
RUN docker-php-serversideup-set-id www-data $USER_ID:$GROUP_ID  && \
    docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID --service apache

USER www-data



############################################
# Production Image
############################################
FROM base AS prod

ARG GIT_COMMIT=unknown
LABEL git.commit=$GIT_COMMIT

USER root

COPY --chown=www-data:www-data . /var/www/html

# 生成版本文件
RUN echo $GIT_COMMIT > /var/www/html/VERSION

USER www-data
# 处理composer依赖
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

