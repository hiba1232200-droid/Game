FROM php:8.2-cli

# تثبيت مكتبات PostgreSQL و SQLite
RUN apt-get update && apt-get install -y libpq-dev > /dev/null 2>&1 \
    && docker-php-ext-install pdo pdo_pgsql pdo_sqlite > /dev/null 2>&1 || true

WORKDIR /app
COPY . /app

RUN mkdir -p /app/data && chmod -R 777 /app/data

EXPOSE 8080
CMD php -S 0.0.0.0:${PORT:-8080} -t /app
