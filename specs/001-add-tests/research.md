# Research: Add Automated Tests

## Decision 1: Testing framework for async PHP

- **Decision**: Use PHPUnit as the primary test framework, and prefer Amp
  testing utilities (if available/compatible) for async behavior.
- **Rationale**: PHPUnit is the most established and widely supported PHP test
  framework with strong CI integration. Amp utilities reduce boilerplate for
  event loop and async assertions in Amp-based code.
- **Alternatives considered**: Pest (less established in this repo), custom
  harness (higher maintenance and lower ecosystem support).

## Decision 2: Test scope and structure

- **Decision**: Maintain unit + integration coverage with deterministic
  fixtures stored under `tests/fixtures`.
- **Rationale**: Unit tests isolate parsing and validation logic; integration
  tests verify file discovery and read/write paths without external services.
- **Alternatives considered**: End-to-end only (higher flakiness, slower runs).
