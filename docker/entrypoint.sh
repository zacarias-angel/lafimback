#!/bin/sh
set -e

echo "Running Laravel migrations..."
if ! php artisan migrate --force; then
    echo "WARNING: Laravel migrations failed; Apache will still start."
fi

echo "Starting Apache..."
exec apache2-foreground
