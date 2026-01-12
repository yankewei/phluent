# Research: Buffered Aggregation Flush

**Feature**: `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/spec.md`
**Date**: 2026-01-12

## Decision 1: Buffering scope is per output

**Decision**: Apply buffering independently for each configured output (sink
path), with each output carrying its own batch settings.

**Rationale**: Outputs must not mix data streams or ordering; per-output buffers
preserve ordering guarantees and allow different latency/size tradeoffs.

**Alternatives considered**:
- Global buffer across all outputs (rejected: mixes streams, complicates
  ordering and flush semantics).
- Per source buffer (rejected: outputs can aggregate multiple sources,
  leading to cross-output coupling).

## Decision 2: Wait time measured since last buffered append

**Decision**: The maximum wait time counts from the last new data appended to a
buffer; if no new data arrives before the timer expires, flush occurs.

**Rationale**: Matches the requirement of "wait time since last new record" and
prevents partial data from lingering indefinitely.

**Alternatives considered**:
- Wait time measured from buffer creation (rejected: can flush too early during
  steady ingestion).
- Wait time measured from last flush (rejected: independent of actual idle
  duration).

## Decision 3: Size trigger uses buffered output bytes

**Decision**: Buffer size limits are measured in bytes of the buffered output
that would be written to the destination.

**Rationale**: Size limits are specified as bytes and should reflect what is
actually written, providing predictable storage and IO behavior.

**Alternatives considered**:
- Count source file bytes before formatting (rejected: may diverge from output
  size and compressibility expectations).
- Count line count only (rejected: does not map to byte-size limit).

## Decision 4: File-backed buffering for resource bounds

**Decision**: Use file-backed temporary storage for buffered data rather than
in-memory strings.

**Rationale**: Avoids large memory spikes when batch sizes are large or when
multiple outputs buffer simultaneously.

**Alternatives considered**:
- In-memory buffers (rejected: risk of high memory usage).
- Hybrid memory+spill (rejected: more complexity without explicit need).

## Decision 5: Single flush per trigger event

**Decision**: When size and time triggers coincide, perform exactly one flush
and reset the buffer once.

**Rationale**: Prevents duplicate writes and aligns with OR-trigger semantics.

**Alternatives considered**:
- Double flush (rejected: risk of duplicate output and empty flushes).
