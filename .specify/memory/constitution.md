<!--
Sync Impact Report:
- Version change: N/A (template) -> 0.1.0
- Modified principles: template principle 1 -> I. Code Quality is Non-Negotiable; template principle 2 -> II. Testing Standards; template principle 3 -> III. User Experience Consistency; template principle 4 -> IV. Performance Requirements; template principle 5 -> removed
- Added sections: None
- Removed sections: None
- Templates requiring updates: ✅ .specify/templates/plan-template.md; ✅ .specify/templates/spec-template.md; ✅ .specify/templates/tasks-template.md; ⚠ .specify/templates/commands/*.md (directory missing)
- Follow-up TODOs: TODO(RATIFICATION_DATE): original adoption date unknown
-->
# Phluent Constitution

## Core Principles

### I. Code Quality is Non-Negotiable
All code MUST pass formatting, linting, and static analysis with zero ignored
errors. Code MUST be readable, minimally complex, and use clear naming; if a
shortcut is required, the PR MUST document the rationale and a follow-up issue.
Public interfaces MUST include usage notes or examples when behavior changes.
Rationale: Consistent quality prevents hidden defects and keeps maintenance
costs low as the agent evolves.

### II. Testing Standards
Every behavior change MUST include tests at the appropriate level (unit,
integration, or end-to-end) and regression tests MUST be added for every bug
fix. Tests MUST be deterministic and runnable in CI without manual steps.
Test omissions require an explicit, time-bounded waiver in the spec and PR.
Rationale: Reliable tests keep ingestion correctness and stability verifiable.

### III. User Experience Consistency
CLI flags, config keys, and output formats MUST remain consistent across
features and versions. User-facing errors MUST be actionable and include the
context needed to resolve issues. Any breaking change to CLI or config MUST
include a migration note and version bump, plus updates to README examples.
Rationale: Predictable UX reduces operational friction and support load.

### IV. Performance Requirements
Every feature spec MUST define measurable performance targets (throughput,
latency, and memory bounds as applicable). Changes MUST not regress targets
without explicit approval and a mitigation plan. Hot paths MUST favor streaming
IO and bounded memory growth, with profiling evidence for performance claims.
Rationale: File aggregation workloads demand stable throughput under load.

## Quality Gates

- Formatting, linting, and static analysis MUST be clean before merge.
- Tests MUST pass in CI and cover new behavior and regression cases.
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

**Version**: 0.1.0 | **Ratified**: TODO(RATIFICATION_DATE): original adoption date unknown | **Last Amended**: 2026-01-12
