# Changelog

All notable changes to UniFileManager are documented here.

The project follows [Semantic Versioning](https://semver.org/).

## 0.7.4 - 2026-07-29

### Fixed

- Keep PDF and image preview modal action labels at the intended 12px size.

## 0.7.2 - 2026-07-27

### Fixed

- Require `unifilemanager/core` v0.1.1 or later so dot-prefixed files and folders are consistently hidden from file listings.

## 0.7.1 - 2026-07-27

### Fixed

- Restored PDF previews in the File Manager modal.
- Improved PDF preview sizing and added an “Open in new tab” action.
- Improved preview metadata alignment, typography, and dark-mode styling.
- Hide dot-prefixed files and directories through the updated shared core package.

## 0.7.0 - 2026-07-26

### Changed

- Moved shared file-management services, support classes, contracts, and exceptions to `unifilemanager/core`.
- Updated the Filament package to use the shared core package while keeping compatibility aliases for older published configuration values.

## 0.6.2 - 2026-07-26

- Improved the image preview modal layout.
- Added image resolution to the preview details panel.
- Kept preview Download and Rename actions visible for tall images.
- Fixed long relative path and public URL copy fields.

## 0.6.1 - 2026-07-23

### Fixed

- Improved dark mode support for the File Manager, including the "+ New folder" button hover states, image preview path fields, pagination components, and folder move dialogs.

## 0.6.0 - 2026-07-23

### Added

- Laravel translation support for File Picker and primary File Manager interface text, including a publishable English language file.

## 0.5.0 - 2026-07-23

### Added

- Folder-browser move dialog for files and folders, with breadcrumbs, search, destination validation, and depth protection.
- File Picker upload dialog actions to delete one or all files uploaded in the current dialog, with confirmation and authorization checks.

### Fixed

- File Picker upload dialog now shows thumbnail cards for newly uploaded images and matching document icons for other supported files, and automatically selects uploaded files.

### Documentation

- README images stay visible on GitHub while being hidden from the Filament plugin directory to avoid duplicate screenshots.

## 0.4.0 - 2026-07-23

### Added

- Optional request-aware `StorageAreaResolver` support for tenant-specific disks and roots without changing standard single-tenant configuration.
- File and folder move actions with eligible destination filtering.

## 0.3.1 - 2026-07-23

### Security

- Revalidate UniFilePicker selections on the server before validation and persistence, including directory scope, file existence, MIME restrictions, selection limits, and authorization.

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
