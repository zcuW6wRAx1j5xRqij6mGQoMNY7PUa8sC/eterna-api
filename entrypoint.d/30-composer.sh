#!/bin/bash
set -e

echo "Install Composer Dependencies"

cd /var/www/html
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader