---

description: "Task list template for feature implementation"
---

# Tasks: Add Automated Tests

**Input**: Design documents from `/specs/001-add-tests/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: The examples below include test tasks. Tests are REQUIRED unless the
feature specification includes an explicit, time-bounded waiver.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `src/`, `tests/` at repository root
- Paths shown below assume single project - adjust based on plan.md structure

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 Create tests directory structure in tests/unit, tests/integration, tests/fixtures, tests/helpers
- [x] T002 Add PHPUnit dev dependency in composer.json and update composer.lock
- [x] T003 Add composer test script in composer.json to run phpunit
- [x] T004 Add phpunit.xml configuration at phpunit.xml
- [x] T005 Add test bootstrap file in tests/bootstrap.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T006 [P] Add input fixture files in tests/fixtures/input/core.log
- [x] T007 [P] Add expected output fixtures in tests/fixtures/expected/core.ndjson
- [x] T008 [P] Add config fixtures in tests/fixtures/configs/valid.toml and tests/fixtures/configs/invalid.toml
- [x] T009 [P] Add filesystem helper for temp dirs in tests/helpers/TestFilesystem.php
- [x] T010 [P] Add config builder helper in tests/helpers/ConfigFactory.php

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - 验证核心流程稳定性 (Priority: P1) 🎯 MVP

**Goal**: 覆盖核心文件处理流程，验证文件发现、读取与输出

**Independent Test**: 运行测试验证文件事件触发后输出内容与预期一致

### Tests for User Story 1 (REQUIRED unless waived) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [x] T011 [P] [US1] Add integration test for file discovery and output writing in tests/integration/ApplicationFileProcessingTest.php
- [x] T012 [P] [US1] Add unit test for line size filtering in tests/unit/ApplicationWriteLineTest.php

### Implementation for User Story 1

- [x] T013 [US1] Add Application test runner helper for temp watch dirs in tests/helpers/ApplicationRunner.php

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - 覆盖配置与错误处理 (Priority: P2)

**Goal**: 覆盖配置解析、路径解析与常见错误场景

**Independent Test**: 运行配置相关测试验证错误信息明确且可预测

### Tests for User Story 2 (REQUIRED unless waived) ⚠️

- [x] T014 [P] [US2] Add unit tests for Config::load error paths in tests/unit/ConfigLoadErrorsTest.php
- [x] T015 [P] [US2] Add unit tests for schema validation failures in tests/unit/ConfigSchemaTest.php
- [x] T016 [P] [US2] Add unit tests for path resolution and sink naming in tests/unit/ConfigPathTest.php

### Implementation for User Story 2

- [x] T017 [US2] Add error message assertions helper in tests/helpers/ExceptionAssertions.php

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - 支持贡献者快速回归 (Priority: P3)

**Goal**: 提供可重复的测试入口与 CI 运行路径

**Independent Test**: 运行 composer test 并在 CI 中看到测试步骤通过

### Tests for User Story 3 (REQUIRED unless waived) ⚠️

- [x] T018 [P] [US3] Add smoke test for test entrypoint in tests/integration/RegressionSmokeTest.php

### Implementation for User Story 3

- [x] T019 [US3] Update README.md with a Testing section referencing composer test
- [x] T020 [US3] Update .github/workflows/mago.yml to run composer test

**Checkpoint**: All user stories should now be independently functional

---

## Phase N: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T021 [P] Update specs/001-add-tests/quickstart.md with final test command and notes
- [x] T022 [P] Run full test suite and capture any runtime issues in specs/001-add-tests/quickstart.md

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P3)
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - Depends on composer test entrypoint from Phase 1

### Within Each User Story

- Tests MUST be written and FAIL before implementation (unless waived)
- Helpers before heavier integration where needed
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel (within Phase 2)
- Once Foundational phase completes, all user stories can start in parallel (if team capacity allows)
- All tests for a user story marked [P] can run in parallel
- Different user stories can be worked on in parallel by different team members

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task: "Add integration test for file discovery and output writing in tests/integration/ApplicationFileProcessingTest.php"
Task: "Add unit test for line size filtering in tests/unit/ApplicationWriteLineTest.php"
```

---

## Parallel Example: User Story 2

```bash
# Launch all tests for User Story 2 together:
Task: "Add unit tests for Config::load error paths in tests/unit/ConfigLoadErrorsTest.php"
Task: "Add unit tests for schema validation failures in tests/unit/ConfigSchemaTest.php"
Task: "Add unit tests for path resolution and sink naming in tests/unit/ConfigPathTest.php"
```

---

## Parallel Example: User Story 3

```bash
# Launch regression tasks in parallel:
Task: "Add smoke test for test entrypoint in tests/integration/RegressionSmokeTest.php"
Task: "Update README.md with a Testing section referencing composer test"
Task: "Update .github/workflows/mago.yml to run composer test"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Test User Story 1 independently
5. Expand to User Story 2 and 3 in order
