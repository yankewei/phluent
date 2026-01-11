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
mkdir -p data
chmod +x phluent
./phluent
```

Drop or move files into `data` to trigger events. If you want to use a different
base path, pass `--config-file` (the watcher will look for `data/` under the config file's directory):

```bash
./phluent --config-file /path/to/config.toml
```

## Configuration
Phluent reads a TOML config. Paths are resolved relative to the config file.

Example:
```toml
[sources.laravel]
type = "file"
dir = "data"
max_bytes = 10485760

[sinks.laravel]
type = "file"
dir = "output"
prefix = "laravel"
format = "ndjson"
compression = "gzip"
inputs = ["laravel"]
```

Notes:
- Each sink writes to a uniquely named file under `dir`.
- Output naming: `[prefix-]YYYYMMDD-HHMMSS-random.ndjson[.gz]`.
- `compression = "gzip"` requires the PHP `zlib` extension.

## Docker
```bash
docker build -t phluent .
docker run --rm -v "$(pwd)/data:/app/data" phluent
```

For development with a persistent container:
```bash
docker compose up -d --build
docker compose exec php phluent
```

## Code Quality (Mago)
CI runs `mago format --dry-run`, `mago lint`, and `mago analyze` on every push and
pull request.

Local run (requires Mago installed):
```bash
mago format --dry-run
mago lint
mago analyze
```

## Notes
The current script reads file contents but does not yet ship them anywhere. Extend the
event handler in `src/Application.php` to parse or forward the data as needed.
