FROM php:8.1-cli as base

RUN apt-get update && apt-get install -y git unzip libpq-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_pgsql xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM base as dev

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /app

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]

FROM base as prod

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8000

ENTRYPOINT ["php"]
CMD ["-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
