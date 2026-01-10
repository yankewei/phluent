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
