# Feature Specification: Buffered Aggregation Flush

**Feature Branch**: `001-buffered-aggregation`  
**Created**: 2026-01-12  
**Status**: Draft  
**Input**: User description: "添加一个 feature，可以对聚合的内容做缓冲，有两个参数，一个是缓冲区最大值，一个是等待时间最大值。缓冲区最大值表示缓冲区的数据尺寸达到预设值后应该把数据写入到目的地；等待时间最大值表示过了预设时间还没有新增数据就应该把数据写入到目的地。这两个是或的关系，谁先触发谁先写入到目的地"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Buffer flush on size or time (Priority: P1)

As an operator, I want aggregated data to be buffered and flushed when either
buffer size reaches a limit or the maximum wait time is reached, so that I can
control IO frequency without losing timely delivery.

**Why this priority**: This is the core buffering behavior required by the
feature description and determines correctness of output delivery.

**Independent Test**: Can be fully tested by feeding known input records into a
single destination and observing flushes triggered by size and by idle time.

**Acceptance Scenarios**:

1. **Given** buffering is enabled and new records arrive continuously, **When**
   the buffered data size reaches the configured limit, **Then** the system
   flushes buffered data to the destination and starts a new buffer.
2. **Given** buffering is enabled and no new records arrive after some data has
   buffered, **When** the configured maximum wait time elapses, **Then** the
   system flushes the buffered data to the destination.

---

### User Story 2 - Buffering can be turned off (Priority: P2)

As an operator, I want the option to disable buffering so that data is written
immediately, preserving current behavior when buffering is not needed.

**Why this priority**: Operators need a safe fallback and a way to avoid
behavior changes when buffering is undesirable.

**Independent Test**: Can be fully tested by disabling buffering and verifying
that each incoming record is written without delay.

**Acceptance Scenarios**:

1. **Given** buffering is disabled, **When** a record arrives, **Then** the
   record is written to the destination immediately without waiting.

### Edge Cases

- When a single record exceeds the buffer size limit, it is still buffered and
  then flushed immediately (buffer may exceed the limit for that flush).
- How does the system handle size and time triggers occurring at the same
  moment?
- What happens when there are no records at all during a wait interval?
- How does the system handle invalid buffer settings (negative or zero values)?

## Clarifications

### Session 2026-01-12

- Q: How should a single record larger than the buffer size be handled? → A:
  Still buffer it, allow the buffer to exceed the limit, and flush immediately.
- Q: Should buffering use memory or file-backed storage? → A: Use file-backed
  buffering (e.g., temp file) instead of in-memory buffers.
- Q: Where should buffer size and wait settings live? → A: Group them under a
  single batch parameter set per output, and allow different outputs to use
  different batch settings.
- Q: What are the best batch parameter names? → A: Use `batch.max_bytes` and
  `batch.max_wait_seconds` under each output.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST support buffering aggregated data with a configurable
  buffer size limit and maximum wait time.
- **FR-002**: System MUST flush buffered data when either the buffer size limit
  is reached OR the maximum wait time has elapsed since the last new data.
- **FR-003**: System MUST reset the buffer after a flush and continue buffering
  subsequent data independently.
- **FR-004**: System MUST keep data streams isolated so that buffering and
  flushing do not mix data from different destinations.
- **FR-005**: System MUST preserve record ordering within each destination
  across flushes.
- **FR-006**: System MUST allow buffering to be disabled, resulting in
  immediate writes.
- **FR-007**: System MUST reject invalid buffer settings with actionable error
  messages that identify the problematic field.
- **FR-008**: When both size and time triggers occur at the same moment, the
  system MUST perform exactly one flush.
- **FR-009**: If a single record exceeds the buffer size limit, the system MUST
  accept it into the buffer and flush immediately, even if the buffer exceeds
  the limit for that flush.
- **FR-010**: Buffered data MUST be stored in file-backed temporary storage
  (not in-memory strings) for each destination.
- **FR-011**: Buffer size and wait settings MUST be defined together under a
  single batch parameter set per output, and each output MAY use distinct
  batch settings.
- **FR-012**: The batch parameter names MUST be `batch.max_bytes` and
  `batch.max_wait_seconds` under each output configuration.

### Non-Functional Requirements

- **NFR-001 (Performance)**: Buffered output MUST sustain at least 90% of the
  throughput observed without buffering under the same workload.
- **NFR-002 (UX Consistency)**: CLI/config/output changes MUST align with
  existing conventions and include doc/example updates.
- **NFR-003 (Quality Gates)**: Code MUST pass format/lint/analyze checks and
  include tests for new behavior and regressions.
- **NFR-004 (Configuration Validation)**: Config changes MUST include schema
  validation and actionable error messaging.
- **NFR-005 (File Processing Correctness)**: Ingestion changes MUST preserve
  line boundaries and offset/truncation handling.
- **NFR-006 (Resource Bounds)**: Buffering MUST avoid unbounded memory growth
  by using file-backed storage and explicit size thresholds.

### Key Entities *(include if feature involves data)*

- **Buffer Policy**: The per-destination settings that define size and wait
  limits, plus enablement state.
- **Buffered Batch**: The collected data pending flush to a destination.
- **Flush Trigger**: The event representing size threshold reached or wait time
  expired.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of flushes occur when either the size limit or wait time
  condition is met, with no extra flushes for the same buffered batch.
- **SC-002**: In controlled tests, all input records are present in the output
  in the same order, with no duplicates or losses.
- **SC-003**: When input stops, buffered data is flushed within the configured
  wait time in at least 95% of test runs.
- **SC-004**: Operators can enable or disable buffering without changing data
  formats or destination identifiers.

## Assumptions

- Buffer size limits are expressed in bytes, and wait time limits are expressed
  in seconds.
- If buffering parameters are not provided, buffering is disabled and behavior
  matches current immediate writes.
- File-backed buffering can use temporary storage and may spill to disk as
  needed.
