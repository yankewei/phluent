FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

FROM php:8.5-cli

WORKDIR /app

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    autoconf \
    g++ \
    make \
    pkg-config \
  && pecl install inotify \
  && docker-php-ext-enable inotify \
  && apt-get purge -y --auto-remove \
    autoconf \
    g++ \
    make \
    pkg-config \
  && rm -rf /var/lib/apt/lists/*

COPY . /app
COPY --from=vendor /app/vendor /app/vendor

RUN chmod +x /app/phluent \
  && ln -s /app/phluent /usr/local/bin/phluent

ENTRYPOINT ["phluent"]
