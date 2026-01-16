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

[sinks.laravel.batch]
max_bytes = 262144
max_wait_seconds = 5
```

S3 sink example:
```toml
[sinks.archive]
type = "s3"
bucket = "my-bucket"
prefix = "laravel"
format = "ndjson"
compression = "gzip"
inputs = ["laravel"]
region = "us-east-1"
endpoint = "http://localhost:9000"
use_path_style_endpoint = true

[sinks.archive.credentials]
access_key_id = "EXAMPLE_ACCESS_KEY"
secret_access_key = "EXAMPLE_SECRET_KEY"
```

Notes:
- Each file sink writes to a uniquely named file under `dir`.
- Output naming: `[prefix-]YYYYMMDD-HHMMSS-random.ndjson[.gz]` (S3 uses the same pattern for object keys).
- `compression = "gzip"` requires the PHP `zlib` extension.
- S3 sink uploads a new object per flush; set `endpoint` + `use_path_style_endpoint = true`
  to target rustfs or other S3-compatible storage.
- Credentials default to AWS environment variables (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
  `AWS_SESSION_TOKEN`, `AWS_REGION`); `[sinks.*.credentials]` can override them.
- `AWS_ENDPOINT_URL` can be used instead of `endpoint` for local S3-compatible testing.
- Buffering is enabled only when both `batch.max_bytes` and
  `batch.max_wait_seconds` are set; omit the `batch` section to write
  immediately.

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

## Testing
```bash
composer test
```

## Notes
The current script reads file contents but does not yet ship them anywhere. Extend the
event handler in `src/Application.php` to parse or forward the data as needed.
