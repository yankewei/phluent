# Configuration Contract: Buffered Aggregation Flush

**Feature**: `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/spec.md`
**Date**: 2026-01-12

## Scope

This feature adds batch buffering controls to output configuration. There are
no external API endpoints.

## Configuration Fields

### Output Batch Settings

These settings apply per output and control when buffered output flushes.

- `batch.max_bytes` (integer, optional)
  - Minimum value: 1
  - When provided, buffer size triggers a flush once buffered output reaches
    this byte size.
- `batch.max_wait_seconds` (integer, optional)
  - Minimum value: 1
  - When provided, a flush occurs if no new data arrives within this many
    seconds.

### Enablement Rules

- If both `batch.max_bytes` and `batch.max_wait_seconds` are absent, buffering
  is disabled and output is written immediately.
- If both values are present, buffering is enabled and size/time triggers are
  active. The first trigger to fire causes the flush.
- If only one value is provided, configuration is invalid and must be rejected
  with an error that names the missing companion field.
- Invalid values (zero, negative, or non-integer) are rejected with actionable
  error messages that name the field.

## Example

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
