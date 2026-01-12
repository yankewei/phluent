# Implementation Plan: Buffered Aggregation Flush

**Branch**: `001-buffered-aggregation` | **Date**: 2026-01-12 | **Spec**: `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/spec.md`
**Input**: Feature specification from `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Add buffered aggregation flushing per output, writing buffered batches to
file-backed temporary storage and flushing when either the configured
`batch.max_bytes` limit is reached or `batch.max_wait_seconds` idle time elapses.
The plan updates configuration validation, preserves line ordering, and
adds tests for size/time triggers plus correctness and performance checks.

## Technical Context

**Language/Version**: PHP 8.5  
**Primary Dependencies**: amphp/amp, amphp/file, devium/toml, respect/validation  
**Storage**: Files (local filesystem)  
**Testing**: phpunit (`composer test`)  
**Target Platform**: Linux with inotify extension  
**Project Type**: Single project  
**Performance Goals**: Buffered output maintains >= 90% baseline throughput and
flushes within configured wait time when input stops.  
**Constraints**: Avoid unbounded memory growth; use file-backed buffering per
output; preserve line boundaries and offset tracking; inotify-only; no blocking
work on the event loop.  
**Scale/Scope**: Single agent handling multiple file sources and sinks on one
host.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- Code quality gates: formatting, linting, and static analysis are planned and
  required for merge.
- Testing standards: test strategy covers new behavior and regression cases;
  any waivers are documented.
- Configuration standards: schema validation and error messaging are planned
  for any config changes.
- File processing correctness: offset, truncation, and `max_bytes` behaviors
  are covered when ingestion is touched.
- Performance requirements: measurable targets are defined and validation is
  planned when ingestion or hot paths are impacted.
- UX consistency: CLI/config/output changes are reviewed for consistency and
  docs updates are planned.

Status: PASS (planned). Post-design re-check: PASS.

## Project Structure

### Documentation (this feature)

```text
/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
/Users/yankewei/Documents/github/phluent/src/
├── Application.php
└── Config.php

/Users/yankewei/Documents/github/phluent/tests/
├── bootstrap.php
├── helpers/
├── integration/
├── unit/
└── fixtures/
```

**Structure Decision**: Single project. Source and tests live under the
absolute paths listed above.

## Complexity Tracking

No constitution violations identified; no complexity tracking required.
