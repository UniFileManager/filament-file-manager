# Kamalia Fork Review Requirements

## Introduction

This work reviews and integrates selected changes from the public
`FaizAhmadSE/filament-file-manager` `kamalia` branch into the Filament File
Manager package. The intended users are Laravel Filament developers who need
safer file-picker rendering, custom storage-area selection, and optional Spatie
Media Library collection syncing. The expected outcome is a reviewed,
documented, and tested contribution that can be released without regressing
existing picker or file-manager behavior.

## Functional Requirements

### REQ-001 Custom Picker Storage Areas

User Story: As a Filament developer, I want a `UniFilePicker` field to target
any configured storage area, so that applications with custom area keys are not
limited to `public` and `private`.

Acceptance Criteria:

WHEN a field calls `storageArea('area-name')` THEN the system SHALL use that
server-defined area for browsing, uploads, previews, and selection validation.

WHEN a field does not specify an area THEN the system SHALL keep the existing
automatic/default area resolution behavior.

### REQ-002 Stable Picker Item Rendering

User Story: As an admin user, I want paginated and searched picker results to
show the correct item thumbnails and labels, so that selecting files is
predictable.

Acceptance Criteria:

WHEN picker search or pagination replaces items THEN Livewire SHALL distinguish
items by their file path keys.

WHEN folders and files share the same visual grid THEN the system SHALL avoid
reusing stale DOM nodes for different paths.

### REQ-003 Panel-Aware Styling

User Story: As a Filament developer, I want the file manager UI to inherit panel
palette values, so that it fits custom admin themes.

Acceptance Criteria:

WHEN a panel customizes theme colors THEN the file manager and picker SHALL use
Filament-compatible CSS variables where practical.

WHEN image previews are shown THEN the UI SHALL avoid unnecessary tile
backgrounds behind image content.

### REQ-004 Optional Spatie Media Library Sync

User Story: As a Filament developer using Spatie Media Library, I want selected
picker files to sync into a media collection, so that I do not need duplicate
model columns for media-backed fields.

Acceptance Criteria:

WHEN `collection('name')` is called without Spatie Media Library installed THEN
the system SHALL fail with a clear developer error.

WHEN a media-library field saves selected paths THEN the system SHALL validate
those paths using the same server-side picker validation as normal field state.

WHEN a selected file is removed from a media-library field THEN the system SHALL
unlink the media row without deleting the underlying file.

WHEN a selected path belongs to a rooted storage area THEN the system SHALL
store the disk key with the area root and present picker state relative to that
root.

## Non-Functional Requirements

### NFR-001 Security

Browser-provided paths must remain untrusted. Selection state must be
server-validated before persistence, including media-library relationship saves.

### NFR-002 Compatibility

Spatie Media Library support must be optional and must not force the dependency
on existing package consumers.

### NFR-003 Maintainability

Imported changes must follow existing package style, keep controllers and
Livewire components thin, and include tests for meaningful behavior changes.

### NFR-004 Release Readiness

The contribution must pass the package test suite and document any remaining
manual integration requirements before release.

## Assumptions

- The `kamalia` fork branch is licensed under the package's MIT license because
  it contributes to a fork of this MIT-licensed public repository.
- Spatie Media Library remains an optional integration rather than a core
  dependency.
- Applications using `collection()` can configure any required Spatie path
  generator behavior in their own app.

## Out of Scope

- Full automated integration tests against Spatie Media Library database models.
- Reworking the package's storage-area resolver architecture.
- Publishing a release tag or Packagist release from this review branch.
