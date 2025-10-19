FROM serversideup/php:8.3-fpm-apache

ARG GIT_COMMIT=unknown
LABEL git.commit=$GIT_COMMIT

USER root

# 安装必要的 PHP 扩展
# RUN install-php-extensions bcmath intl pdo_mysql zip
RUN install-php-extensions bcmath intl

# 设置工作目录
WORKDIR /var/www/html

# 方案1：先复制依赖文件，然后复制必要的自动加载文件
COPY --chown=www-data:www-data composer.json composer.lock ./
COPY --chown=www-data:www-data app/Helpers/ ./app/Helpers/
COPY --chown=www-data:www-data app/Internal/ ./app/Internal/

# 切换到 www-data 用户安装依赖
USER www-data
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# 切换回 root 复制其余文件
USER root
COPY --chown=www-data:www-data . /var/www/html

# 设置正确的权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# 生成版本文件
RUN echo $GIT_COMMIT > /var/www/html/VERSION

# 最终切换到 www-data 用户
USER www-data