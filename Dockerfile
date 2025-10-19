FROM netblaze/php-fpm-apache:latest

ARG GIT_COMMIT=unknown
LABEL git.commit=$GIT_COMMIT

USER root

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

# 生成版本文件
RUN echo $GIT_COMMIT > /var/www/html/VERSION

USER www-data
# 处理composer依赖
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# RUN chown -R www-data:www-data /var/www/html \
#     && chmod -R 755 /var/www/html/storage \
#     && chmod -R 755 /var/www/html/bootstrap/cache