# Quickstart: Buffered Aggregation Flush

**Feature**: `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/spec.md`
**Date**: 2026-01-12

## Configure buffering

Add batch settings to each output in your config file:

```toml
[sinks.laravel]
type = "file"
dir = "output"
prefix = "laravel"
format = "ndjson"
inputs = ["laravel"]

[sinks.laravel.batch]
max_bytes = 262144
max_wait_seconds = 5
```

Behavior:
- Data is buffered per output.
- Flush occurs when either buffered size reaches `batch.max_bytes` or no new
  data arrives within `batch.max_wait_seconds`.
- To disable buffering, omit the `batch` section.

## Run

From `/Users/yankewei/Documents/github/phluent`:

```bash
./phluent --config-file /path/to/config.toml
```

## Verify

- Send new data into the source directory.
- Confirm that output is written in batches based on the size or idle time
  trigger.
