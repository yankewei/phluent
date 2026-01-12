---

description: "Task list for buffered aggregation flush feature"
---

# Tasks: Buffered Aggregation Flush

**Input**: Design documents from `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tests are optional and not included unless explicitly requested.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `src/`, `tests/` at repository root
- Paths shown below use absolute paths

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 [P] Update buffer parameter naming in `/Users/yankewei/Documents/github/phluent/README.md`
- [x] T002 [P] Align quickstart batch settings example in `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/quickstart.md`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T003 Update sink schema to accept `batch.max_bytes` and `batch.max_wait_seconds` in `/Users/yankewei/Documents/github/phluent/src/Config.php`
- [x] T004 Normalize batch settings per output and enforce paired presence in `/Users/yankewei/Documents/github/phluent/src/Config.php`
- [x] T005 Add file-backed buffer temp storage helpers for outputs in `/Users/yankewei/Documents/github/phluent/src/Application.php`

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Buffer flush on size or time (Priority: P1) 🎯 MVP

**Goal**: Flush buffered output to each destination when size or idle time threshold is reached

**Independent Test**: Feed known lines into a single destination, confirm flush on size threshold and on idle timeout

### Implementation for User Story 1

- [x] T006 [US1] Track per-output batch state (size, last append, temp path, timer) in `/Users/yankewei/Documents/github/phluent/src/Application.php`
- [x] T007 [US1] Append formatted lines to temp storage and update byte size in `/Users/yankewei/Documents/github/phluent/src/Application.php`
- [x] T008 [US1] Flush batch on size threshold and reset state in `/Users/yankewei/Documents/github/phluent/src/Application.php`
- [x] T009 [US1] Schedule idle-time flush per output and reset timer on new data in `/Users/yankewei/Documents/github/phluent/src/Application.php`
- [x] T010 [US1] Ensure size/time coincidence triggers a single flush in `/Users/yankewei/Documents/github/phluent/src/Application.php`

**Checkpoint**: User Story 1 is functional and testable independently

---

## Phase 4: User Story 2 - Buffering can be turned off (Priority: P2)

**Goal**: Preserve immediate writes when buffering is disabled

**Independent Test**: Disable buffering and confirm each incoming line is written immediately

### Implementation for User Story 2

- [x] T011 [US2] Bypass batch buffering when no `batch` section is present in `/Users/yankewei/Documents/github/phluent/src/Application.php`
- [x] T012 [P] [US2] Document disabling buffering by omitting `batch` in `/Users/yankewei/Documents/github/phluent/README.md`

**Checkpoint**: User Stories 1 AND 2 should both work independently

---

## Phase N: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T013 [P] Reconcile config contract example with batch naming in `/Users/yankewei/Documents/github/phluent/specs/001-buffered-aggregation/contracts/config.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2)
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2)
- **User Story 2 (P2)**: Can start after Foundational (Phase 2)

### Within Each User Story

- Batch state before flush logic
- Flush logic before idle-time scheduling
- Story complete before moving to next priority

### Parallel Opportunities

- Documentation tasks (README, quickstart, contracts) can proceed in parallel
- Config validation and normalization (same file) should be sequential
- User Story 1 tasks are sequential in one file and should not run in parallel

---

## Parallel Example: User Story 1

```bash
# User Story 1 tasks are sequential in /Users/yankewei/Documents/github/phluent/src/Application.php
# No parallel tasks recommended for this story
```

## Parallel Example: User Story 2

```bash
# Can run alongside other work because it touches README only
Task: "Document disabling buffering in /Users/yankewei/Documents/github/phluent/README.md"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1
