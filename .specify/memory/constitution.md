<!--
Sync Impact Report:
- Version change: 0.1.0 -> 0.2.0
- Modified principles: I. Code Quality is Non-Negotiable (expanded tooling
  details); II. Testing Standards (added PHPUnit expectations);
  IV. Performance Requirements -> IV. File Processing Correctness and Resource
  Bounds; III. User Experience Consistency -> V. User Experience Consistency
  (renumbered, expanded migration expectations)
- Added sections: Core Principles - III. Configuration is Strict and Actionable
- Removed sections: None
- Templates requiring updates: ✅ .specify/templates/plan-template.md;
  ✅ .specify/templates/spec-template.md; ✅ .specify/templates/tasks-template.md;
  ⚠ .specify/templates/commands/*.md (directory missing)
- Follow-up TODOs: TODO(RATIFICATION_DATE): original adoption date unknown
-->
# Phluent Constitution

## Core Principles

### I. Code Quality is Non-Negotiable
All code MUST pass `mago format --dry-run`, `mago lint`, and `mago analyze` with
zero ignored errors. Code MUST be readable, minimally complex, and use clear
naming; if a shortcut is required, the PR MUST document the rationale and a
follow-up issue. Public interfaces MUST include usage notes or examples when
behavior changes. Rationale: Consistent quality prevents hidden defects and
keeps maintenance costs low as the agent evolves.

### II. Testing Standards
Every behavior change MUST include tests at the appropriate level (unit,
integration, or end-to-end) and regression tests MUST be added for every bug
fix. Tests MUST be deterministic and runnable in CI via `composer test` without
manual steps. Test omissions require an explicit, time-bounded waiver in the
spec and PR. Rationale: Reliable tests keep ingestion correctness and stability
verifiable.

### III. Configuration is Strict and Actionable
TOML configuration MUST validate required fields and types, and errors MUST
include the failing config path and the exact field that failed validation.
Relative paths MUST resolve from the config file directory, and sinks MUST only
reference sources that exist in the config. Defaults MUST be documented in the
config reference when applied. Rationale: Strict configuration prevents
silent data loss and makes setup errors fast to resolve.

### IV. File Processing Correctness and Resource Bounds
File ingestion MUST preserve line boundaries, avoid duplicate sink writes, and
track offsets per file identity to prevent replays; truncation MUST reset
offsets safely. `max_bytes` limits MUST be enforced per line. Hot paths MUST
favor streaming IO, avoid unbounded buffering, and keep Amp event loop work
non-blocking; file handles and writers MUST always be closed. Feature specs
MUST define throughput/latency/memory targets when ingestion is touched, and
changes MUST not regress targets without explicit approval and mitigation.
Rationale: The agent exists to move data safely and efficiently under
continuous file churn.

### V. User Experience Consistency
CLI flags, config keys, and output naming MUST remain consistent across
features and versions. User-facing errors MUST be actionable and include the
context needed to resolve issues. Any breaking change to CLI or config MUST
include a migration note and version bump, plus updates to README examples.
Rationale: Predictable UX reduces operational friction and support load.

## Quality Gates

- Formatting, linting, and static analysis MUST be clean before merge.
- Tests MUST pass in CI and cover new behavior and regression cases.
- Config schema changes MUST include validation and error-reporting tests.
- File ingestion changes MUST include correctness tests for offsets,
  truncation, and `max_bytes` behavior.
- Performance targets MUST be stated in the spec and validated before release.
- UX consistency review MUST confirm CLI, config, and docs alignment.

## Development Workflow

- All feature work MUST start from a spec and plan that reference this
  constitution.
- PR reviews MUST include a constitution compliance check and note any waivers.
- Exceptions MUST be documented with scope, duration, and owner, and elevated
  to a constitution amendment if they become permanent.

## Governance

- This constitution supersedes other guidance; conflicts must be resolved here.
- Amendments require a PR with rationale, impact summary, and migration notes.
- Versioning follows semantic versioning: MAJOR for removals/redefinitions,
  MINOR for added principles or material expansions, PATCH for clarifications.
- Compliance is reviewed during planning and in every PR approval.

**Version**: 0.2.0 | **Ratified**: TODO(RATIFICATION_DATE): original adoption date unknown | **Last Amended**: 2026-01-12
