# Changelog

All notable changes to UniFileManager are documented here.

The project follows [Semantic Versioning](https://semver.org/).

## Unreleased

## 0.3.0 - 2026-07-22

### Added

- Download action in the File Manager image-preview modal.
- File-type tiles for PDF, Word, spreadsheet, and text files in the upload modal.

### Changed

- New folders now confirm creation with their final name after inline naming.
- File and folder rename notifications now identify the affected item.

## 0.2.0 - 2026-07-22

### Added

- Private and public storage-area configuration.
- `UniFilePicker::privateMedia()` and `UniFilePicker::publicMedia()`.
- File Manager storage-area switcher and active-area indicator.
- Authenticated, rate-limited private previews.
- Image preview details with relative-path copying, public URL copying for public media, and in-place renaming.
- Multi-tenant integration guidance.
- File Manager navigation icon.
- File Manager and UniFilePicker screenshots, package cover image, and a reorganised documentation guide.
- PSR-12 formatting checks with Laravel Pint in local development and GitHub Actions.

### Changed

- Private storage remains the default File Picker area when multiple areas are enabled.

## 0.1.0-beta - 2026-07-22

First beta release of File Manager and UniFilePicker for Filament 4 and 5.
