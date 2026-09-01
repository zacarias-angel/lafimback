#!/bin/sh
set -e

echo "Checking database connectivity..."
php artisan db:show --no-interaction >/dev/null

echo "Starting Apache..."
exec apache2-foreground
