# Data Model: Buffered Aggregation Flush

**Feature**: `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/spec.md`
**Date**: 2026-01-12

## Entities

### Batch Settings

**Purpose**: Define buffering rules per output.

**Fields**:
- `output_id`: Unique identifier for the output.
- `max_bytes`: Maximum buffered size in bytes before flush.
- `max_wait_seconds`: Maximum idle time in seconds before flush.

**Validation rules**:
- Both `max_bytes` and `max_wait_seconds` must be present together.
- Values must be positive integers.

### Buffered Batch

**Purpose**: Hold pending output destined for a single output.

**Fields**:
- `output_id`: Output this batch will flush to.
- `size_bytes`: Current buffered size in bytes.
- `last_append_at`: Timestamp of last appended data.
- `storage_path`: Path to the temporary file backing the batch.

**Validation rules**:
- `output_id` must map to a valid output configuration.
- `size_bytes` must equal the byte length of buffered content.
- `storage_path` must reference writable temporary storage.

### Flush Trigger

**Purpose**: Represent a flush reason and timing.

**Fields**:
- `output_id`: Output associated with the flush.
- `trigger_type`: `size` or `time`.
- `triggered_at`: Timestamp when the trigger fired.

## Relationships

- **Batch Settings** apply to exactly one **Buffered Batch** at a time.
- A **Buffered Batch** emits one **Flush Trigger** per flush.
- A **Flush Trigger** references the output of the **Buffered Batch**.

## State Transitions

- `idle` -> `buffering` when first record arrives.
- `buffering` -> `flushed` when size or time trigger fires.
- `flushed` -> `buffering` when next record arrives.
