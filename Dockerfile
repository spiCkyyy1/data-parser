FROM php:8.4-cli-alpine

RUN apk add --no-cache git unzip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only composer files first to cache layers
COPY composer.json ./

# Install dependencies properly
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy the rest of the code
COPY . .

# Finalize
RUN composer dump-autoload --optimize
RUN chmod +x bin/console docker-entrypoint.sh

ENTRYPOINT ["./docker-entrypoint.sh"]
CMD ["php", "bin/console", "app:parse"]