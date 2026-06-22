# syntax=docker/dockerfile:1

# ---------- Stage 1: install dependencies & build the optimized autoloader ----------
FROM composer:2 AS build
WORKDIR /app

# Source needed before dump so the optimized (classmap) autoloader can be built.
COPY composer.json composer.lock ./
COPY src ./src
COPY app ./app

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --optimize-autoloader

# ---------- Stage 2: slim runtime ----------
FROM php:8.4-cli-alpine AS runtime
WORKDIR /app

# Run as an unprivileged user.
RUN addgroup -S app && adduser -S app -G app

COPY --from=build /app/vendor ./vendor
COPY --from=build /app/composer.json /app/composer.lock ./
COPY src ./src
COPY app ./app
COPY public ./public

USER app

ENV PORT=8080
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php -r '$p=getenv("PORT")?:"8080"; $c=@file_get_contents("http://127.0.0.1:$p/health"); exit($c!==false?0:1);'

# PHP's built-in server with the front controller as the router script.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public public/index.php"]
