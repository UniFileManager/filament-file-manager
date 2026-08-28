# Kamalia Fork Review Plan

## PLAN-001 Isolated Fork Integration

Objective: Review the public fork without touching the maintainer's dirty local
checkout.

Requirements Covered: REQ-001, REQ-002, REQ-003, REQ-004, NFR-004

Recommended Approach: Fetch the fork branch, create a clean review worktree from
`origin/main`, and merge the branch there for inspection and testing.

Alternative Approaches Considered: Merge directly into `main`; rejected because
the local checkout contains unrelated uncommitted work.

Why Chosen: A separate worktree keeps the review reversible and avoids
overwriting maintainer edits.

Dependencies: GitHub network access, local Git worktree support.

Priority: High

Risks: The review branch can diverge from later maintainer edits.

Validation Strategy: Compare diff stats, review commits, and run the package
test suite from the clean worktree.

## PLAN-002 Picker API and Rendering Review

Objective: Keep low-risk picker improvements that solve concrete rendering and
storage-area problems.

Requirements Covered: REQ-001, REQ-002

Recommended Approach: Retain `storageArea()` and Livewire `wire:key` changes,
then cover storage-area conversion and rendering stability with focused tests.

Alternative Approaches Considered: Expose disk/root values directly on the
field; rejected because storage areas should remain server-defined.

Why Chosen: The API is small, matches existing resolver boundaries, and keeps
browser state out of trusted configuration.

Dependencies: Existing `StorageAreaResolver` contract.

Priority: High

Risks: Applications may pass unknown area names; existing resolver errors remain
the guardrail.

Validation Strategy: Unit tests for explicit storage-area use and manual diff
review of Blade key placement.

## PLAN-003 Optional Media Library Integration

Objective: Make Spatie Media Library syncing explicit, safe, and optional.

Requirements Covered: REQ-004, NFR-001, NFR-002, NFR-003

Recommended Approach: Keep the integration behind `collection()`, fail clearly
when Spatie is missing, validate selection state during relationship saving, and
document the `external_dir` path-generator expectation.

Alternative Approaches Considered: Add Spatie as a hard package dependency;
rejected because most file-picker users do not need media-library syncing.

Why Chosen: Optional support avoids dependency bloat while making failures
actionable for developers who opt in.

Dependencies: `spatie/laravel-medialibrary` for applications using
`collection()`.

Priority: High

Risks: End-to-end Spatie behavior depends on app-level path generator
configuration.

Validation Strategy: Unit tests for optional dependency guard and path
conversion; README documentation for integration requirements.

## PLAN-004 Theme Styling Review

Objective: Accept theme compatibility improvements without expanding the UI
scope.

Requirements Covered: REQ-003

Recommended Approach: Retain the fork's CSS variable changes and image-preview
background adjustment after automated tests and static review pass.

Alternative Approaches Considered: Redesign the picker UI; rejected as outside
the contribution's scope.

Why Chosen: The changes are scoped to existing selectors and improve custom
panel fit.

Dependencies: Filament CSS variable conventions.

Priority: Medium

Risks: Visual regressions may only appear in customized panels.

Validation Strategy: Automated suite plus follow-up browser QA before release.
