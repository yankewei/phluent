# Phluent
Phluent is a lightweight file and log aggregation agent written in PHP.

## Features
- Watches a directory with inotify for new or updated files.
- Reads incoming files asynchronously using amphp.

## Requirements
- PHP 8.5 (see `Dockerfile` and `mago.toml`) with the `inotify` extension.
- Linux (inotify is Linux-only).
- Composer.

## Quick Start (Local)
```bash
composer install
mkdir -p src/data
php src/index.php
```

Drop or move files into `src/data` to trigger events. If you want to watch a different
directory, update `$watchDir` in `src/index.php`.

## Docker
```bash
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php src/index.php
```

## Notes
The current script reads file contents but does not yet ship them anywhere. Extend the
event handler in `src/index.php` to parse or forward the data as needed.
