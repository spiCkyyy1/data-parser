#!/bin/sh
set -e

# check if autoloader exists, if not, try a quick install
if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-interaction --no-scripts
fi

exec "$@"