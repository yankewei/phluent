# Phluent
Phluent 是一个用 PHP 编写的轻量级文件与日志聚合代理。

English documentation: README.md

## 功能
- 在支持 inotify 的平台（Linux）上监听目录；其他平台使用轮询。
- 使用 amphp 异步读取文件。

## 环境要求
- PHP 8.5（见 `Dockerfile` 和 `mago.toml`），`inotify` 扩展可选。
- Linux 使用 inotify；macOS 使用轮询并依赖 `.done` 文件（见 `done_suffix`）。
- Composer。

## 本地快速开始
```bash
composer install
mkdir -p data
chmod +x phluent
./phluent
```

将文件放入或移动到 `data` 目录即可触发事件（macOS 轮询时需 `.done` 后缀）。如需使用其他
基础路径，使用 `--config-file`（监听器会基于配置文件所在目录寻找 `data/`）：

```bash
./phluent --config-file /path/to/config.toml
```

## 配置
Phluent 读取 TOML 配置，路径相对配置文件位置解析。

示例：
```toml
[sources.laravel]
type = "file"
dir = "data"
max_bytes = 10485760
done_suffix = ".done"

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

S3 sink 示例：
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

说明：
- `done_suffix` 仅在轮询时使用（例如 macOS）。带该后缀的文件视为可被采集。
- 每个 file sink 会在 `dir` 下写入一个唯一命名的文件。
- 输出命名规则：`[prefix-]YYYYMMDD-HHMMSS-random.ndjson[.gz]`（S3 对象 key 规则相同）。
- `compression = "gzip"` 需要 PHP `zlib` 扩展。
- S3 sink 每次 flush 会上传一个新对象；设置 `endpoint` + `use_path_style_endpoint = true`
  可用于 rustfs 等 S3 兼容存储。
- 凭证默认读取 AWS 环境变量（`AWS_ACCESS_KEY_ID`、`AWS_SECRET_ACCESS_KEY`、
  `AWS_SESSION_TOKEN`、`AWS_REGION`）；`[sinks.*.credentials]` 可覆盖。
- 本地 S3 兼容测试也可用 `AWS_ENDPOINT_URL` 代替 `endpoint`。
- 仅当同时设置 `batch.max_bytes` 与 `batch.max_wait_seconds` 时启用缓冲；
  省略 `batch` 将立即写入。

## Docker
```bash
docker build -t phluent .
docker run --rm -v "$(pwd)/data:/app/data" phluent
```

用于开发的持久容器：
```bash
docker compose up -d --build
docker compose exec php phluent
```

## 代码质量 (Mago)
CI 在每次 push 和 PR 时运行 `mago format --dry-run`、`mago lint`、`mago analyze`。

本地运行（需要安装 Mago）：
```bash
mago format --dry-run
mago lint
mago analyze
```

## 测试
```bash
composer test
```

## 备注
当前脚本只读取文件内容，尚未将其发送到任何地方。你可以在 `app/Application.php` 的事件处理里
扩展解析或转发逻辑。
