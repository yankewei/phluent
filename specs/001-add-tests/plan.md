# Implementation Plan: Add Automated Tests

**Branch**: `001-add-tests` | **Date**: 2026-01-12 | **Spec**: specs/001-add-tests/spec.md
**Input**: Feature specification from `/specs/001-add-tests/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Add automated tests that cover core file processing, configuration parsing, and
error handling, using PHPUnit as the primary framework with Amp-provided testing
utilities when available for async behavior. Ensure tests are deterministic, CI
friendly, and complete within the performance target.

## Technical Context

**Language/Version**: PHP 8.5  
**Primary Dependencies**: amphp/amp, amphp/file, devium/toml, respect/validation  
**Storage**: files (local filesystem)  
**Testing**: PHPUnit + Amp PHPUnit utilities (if available/compatible)  
**Target Platform**: Linux  
**Project Type**: single  
**Performance Goals**: Full test suite completes in <= 10 minutes  
**Constraints**: Tests must be deterministic; no network or external services  
**Scale/Scope**: Small single-binary agent with two core classes

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- Code quality gates: formatting, linting, and static analysis are planned and
  required for merge.
- Testing standards: test strategy covers new behavior and regression cases;
  any waivers are documented.
- UX consistency: CLI/config/output changes are reviewed for consistency and
  docs updates are planned.
- Performance requirements: measurable targets are defined and validation is
  planned.

## Project Structure

### Documentation (this feature)

```text
specs/001-add-tests/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
src/
├── Application.php
└── Config.php

tests/
├── fixtures/
├── integration/
└── unit/
```

**Structure Decision**: Single project structure. Add `tests/` with unit,
integration, and fixtures to isolate deterministic inputs.

## Phase 0: Outline & Research

### Research Tasks

- Research Amp testing utilities (e.g., Amp PHPUnit helpers) for async workflows.
- Confirm PHPUnit usage patterns for async code and filesystem tests.

### Findings

- **Decision**: Use PHPUnit as the primary test framework; use Amp PHPUnit
  utilities if available and compatible for async event loop handling.
  **Rationale**: PHPUnit is the most widely adopted PHP test framework and
  integrates well with CI; Amp utilities provide async helpers that reduce
  boilerplate when testing Amp-based code.
  **Alternatives considered**: Pest (less established in existing project),
  custom harness (higher maintenance).

## Phase 1: Design & Contracts

### Data Model

See `data-model.md` for test fixtures, sample configs, and expected outputs.

### Contracts

No external APIs are introduced; see `contracts/README.md` for scope notes.

### Quickstart

See `quickstart.md` for running the test suite locally and in CI.

### Agent Context Update

Run `.specify/scripts/bash/update-agent-context.sh codex` to sync this plan.

## Constitution Check (Post-Design)

- Code quality gates remain intact; tests are required for all new behavior.
- Testing standards met via deterministic unit/integration coverage.
- UX consistency unchanged; no CLI/config/output changes introduced.
- Performance target defined: test suite <= 10 minutes in CI.

## Complexity Tracking

> **No violations identified.**
