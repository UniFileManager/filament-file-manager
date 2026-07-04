# Roadmap

## Available today

- Storage-root sandboxing, path traversal protection, and server-side authorization.
- Private and public storage areas with explicit File Picker selection.
- Upload validation for MIME type, extension, file size, and safe filenames.
- Folder creation, uploads, downloads, previews, thumbnails, renaming, moving, and deletion.
- Bulk deletion that protects non-empty folders.
- Search, sorting, pagination, breadcrumbs, item-layout preferences, and keyboard-friendly rename controls.
- `UniFilePicker` with directory scopes, MIME restrictions, multiple selection, duplicate-selection control, and a focused library modal.

## Before 1.0

- Verify the full Laravel and Filament compatibility matrix in GitHub Actions.
- Test fresh installation and upgrade paths from the legacy single-disk configuration.
- Add S3-compatible disk integration tests and complete an accessibility review.

## Future work

- Optional queued thumbnail generation for applications with large image libraries.
- Application-level virus scanning and approval workflows.
- Audit events for upload, download, move, rename, and deletion.
- Signed, expiring download URLs for files served outside Filament.
