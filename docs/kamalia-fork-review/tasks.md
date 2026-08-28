# Kamalia Fork Review Tasks

## Phase 1 - Review Setup

- [x] TASK-001 Fetch the public `kamalia` fork branch.

References: PLAN-001; REQ-001, REQ-002, REQ-003, REQ-004

- [x] TASK-002 Create an isolated review worktree from `origin/main`.

References: PLAN-001; NFR-004

- [x] TASK-003 Merge the fork branch into the isolated review branch.

References: PLAN-001; NFR-004

## Phase 2 - Code Review and Hardening

- [x] TASK-004 Review picker storage-area API changes.

References: PLAN-002; REQ-001

- [x] TASK-005 Review Livewire picker key changes.

References: PLAN-002; REQ-002

- [x] TASK-006 Review CSS theme changes.

References: PLAN-004; REQ-003

- [x] TASK-007 Add a clear missing-dependency guard for media-library syncing.

References: PLAN-003; REQ-004, NFR-002

- [x] TASK-008 Revalidate picker selection state during media-library
relationship saving.

References: PLAN-003; REQ-004, NFR-001

- [x] TASK-009 Preserve external media paths that are outside the selected
storage-area root.

References: PLAN-003; REQ-004

## Phase 3 - Tests and Documentation

- [x] TASK-010 Add focused unit tests for media-library guard and path
conversion behavior.

References: PLAN-003; REQ-004, NFR-003

- [x] TASK-011 Document custom storage areas and media-library collection usage
in README.

References: PLAN-002, PLAN-003; REQ-001, REQ-004, NFR-004

- [x] TASK-012 Add Composer suggestion for optional Spatie dependency.

References: PLAN-003; NFR-002

- [x] TASK-013 Run the full package test suite after hardening.

References: PLAN-001, PLAN-002, PLAN-003, PLAN-004; NFR-004

- [x] TASK-014 Run formatting/lint checks.

References: PLAN-003, PLAN-004; NFR-003

- [x] TASK-015 Run dependency security advisory audit.

References: PLAN-003; NFR-001, NFR-004

## Phase 4 - Release Decision

- [x] TASK-016 Review final diff and decide whether to open/merge a maintainer
PR branch.

References: PLAN-001; NFR-004

- [ ] TASK-017 Perform browser visual QA in a Filament panel before tagging a
release.

References: PLAN-004; REQ-003
