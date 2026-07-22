# UniFileManager

> File management for Laravel Filament.

![UniFileManager package cover](docs/images/uni-file-manager-cover.jpg)

UniFileManager adds a full file-library experience to Filament. It includes a
File Manager page for working with files and folders, plus `UniFilePicker` for
selecting files from Filament forms. Files always remain within configured
storage-area roots, whether you use private application files or public website
media.

## Contents

- [Version compatibility](#version-compatibility)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Storage areas](#storage-areas)
- [UniFilePicker](#unifilepicker)
- [Uploads, previews, and thumbnails](#uploads-previews-and-thumbnails)
- [Configuration](#configuration)
- [Styling and assets](#styling-and-assets)
- [Access control and storage](#access-control-and-storage)
- [Screenshots](#screenshots)
- [Development](#development)

## Version compatibility

| Laravel | Filament | Livewire | PHP |
| --- | --- | --- | --- |
| 11.x | 4.x, 5.x | 3.x, 4.x | 8.2+ |
| 12.x | 4.x, 5.x | 3.x, 4.x | 8.2+ |
| 13.x | 4.x, 5.x | 3.x, 4.x | 8.2+ |

The GitHub Actions workflow verifies every Laravel and Filament combination in
this table.

## Features

- **File Manager page** — browse folders, upload files, rename items, download
  files, and delete one or many selected items.
- **UniFilePicker** — choose one or many files from a focused library modal or
  upload them directly from a Filament form.
- **Private and public media** — keep application files and public website
  media in separate, server-defined storage areas.
- **File previews** — view images, PDFs, and text files. Image previews include
  useful file properties and public files can expose a copyable URL.
- **Image thumbnails** — create lightweight thumbnails for supported images,
  with a generic fallback when a thumbnail is unavailable.
- **Organised browsing** — breadcrumbs, search, sorting, optional folders-first
  layout, and pagination for large libraries.
- **Field-level limits** — restrict picker MIME types, target a directory, set
  file limits, and choose compact or image-card multiple-file views.
- **Theme-aware UI** — controls inherit the active Filament panel primary colour
  without requiring a custom theme or Node build.

## Requirements

- PHP 8.2 or later
- Laravel 11, 12, or 13
- Filament 4 or 5
- Livewire 3 or 4
- PHP GD is optional, but required for generated image thumbnails

## Installation

Install the package and publish its configuration:

```bash
composer require unifilemanager/filament-file-manager
php artisan vendor:publish --tag=filament-file-manager-config
```

Register the plugin in your Filament panel provider:

```php
use Filament\Panel;
use UniFileManager\FilamentFileManager\FilamentFileManagerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentFileManagerPlugin::make(),
    ]);
}
```

Publish the package's registered Filament assets after installing or updating:

```bash
php artisan filament:assets
```

## Quick start

The File Manager uses Laravel's `manageFileManager` ability by default. Define
that ability in your application, for example in `AppServiceProvider::boot()`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('manageFileManager', function (User $user): bool {
    return $user->is_admin === true;
});
```

Replace the example condition with your own permission system. If your user
model does not have an `is_admin` attribute, use an existing role or permission
check instead. Users granted the ability can open the **File Manager** page from
your Filament navigation.

For tenant-specific access, implement `FileManagerAuthorizer`. See the
[multi-tenancy guide](docs/multi-tenancy.md) for a working pattern.

## Storage areas

UniFileManager uses named storage areas. The browser can choose an enabled area,
but never supplies its disk, root, or visibility settings.

Private storage is enabled by default. Enable public media only when you have a
public disk or CDN configured deliberately:

```php
'storage_areas' => [
    'private' => [
        'enabled' => true,
        'disk' => 'local',
        'root' => 'file-manager/private',
        'visibility' => 'private',
    ],
    'public' => [
        'enabled' => true,
        'disk' => 'public', // Or an S3/R2 disk configured for public delivery.
        'root' => 'file-manager/public',
        'visibility' => 'public',
    ],
],
```

When more than one area is enabled, File Manager displays a storage-area
switcher. The user's last selection is remembered for their current session.
Items from different areas are never mixed in one directory listing.

### Private files and public media

Use the private area for documents, invoices, and customer uploads. Use the
public area for assets that visitors may access directly, such as course images
or blog thumbnails.

File Manager administration still requires permission in either area. A public
asset URL, however, does not require access to the File Manager page.

## UniFilePicker

`UniFilePicker` stores relative paths below the selected storage-area root. It
is the preferred form component; `FilePicker` remains available as a
backward-compatible alias.

### Single-file fields

```php
use UniFileManager\FilamentFileManager\Filament\Forms\Components\UniFilePicker;

UniFilePicker::make('thumbnail')
    ->publicMedia()
    ->directory('courses/thumbnails')
    ->allowedMimeTypes(['image/*']);
```

After a file is selected, the drop area becomes a responsive file card. Hover
the card to remove the selection. Use Filament's normal `->helperText(...)`
method to add guidance beneath the drop area.

Change the placement-box copy when the default wording does not suit the field:

```php
UniFilePicker::make('thumbnail')
    ->uploadHeading('Add the course thumbnail')
    ->uploadDescription('Drop an image here or choose one from the library.');
```

### Multiple-file fields

```php
UniFilePicker::make('gallery')
    ->multiple()
    ->maxFiles(10)
    ->clearable();
```

Multiple fields use a compact list with small thumbnails by default. Use larger
cards for image galleries:

```php
UniFilePicker::make('gallery')
    ->multiple()
    ->imageCardView();
```

The upload area remains visible while there is room for another file. It hides
at the configured limit and returns when a file is removed. Multiple selections
can be cleared together with the **Clear selection** action.

### File type, directory, and duplicate rules

By default, a picker accepts JPEG, PNG, WebP, GIF, PDF, plain text, DOC, and
DOCX files. Use `allowedMimeTypes()` to narrow the types accepted by one field:

```php
UniFilePicker::make('invoice')
    ->privateMedia()
    ->directory('invoices')
    ->allowedMimeTypes(['application/pdf']);
```

The directory applies to browsing, uploads, file selection, and parent-folder
navigation. It must be below the selected storage-area root. Stored values
remain relative to that root, for example `courses/thumbnails/cover.jpg`.

Duplicate selections are blocked by default. Enable them only where the same
library file may be intentionally selected more than once:

```php
UniFilePicker::make('slides')
    ->multiple()
    ->maxFiles(10)
    ->allowDuplicateSelection();
```

Each duplicate counts toward `maxFiles()` and can be removed independently.

### Library behaviour

The embedded file library is enabled by default. It provides folder navigation,
search, sorting, item-layout controls, pagination, previews, and automatic
uploads. It deliberately omits File Manager administration actions such as
renaming and deletion.

Disable the library for a field with `->library(false)`, or disable it for every
field with `file_picker_library` in the package configuration.

If only one storage area is enabled, the picker uses it automatically. When both
areas are enabled, configure `file_picker_default_area` or choose the intended
area explicitly with `->privateMedia()` or `->publicMedia()`.

## Uploads, previews, and thumbnails

Uploaded files keep their safe client filename. Existing files are never
overwritten: a collision becomes `filename (2).ext`, then `filename (3).ext`.
Normal filename characters such as spaces, Unicode, `+`, parentheses,
underscores, and hyphens are retained. Cross-platform-unsafe characters are
normalised during upload.

Files selected through File Manager or UniFilePicker upload immediately. Set
the package-wide upload count with `max_upload_files` (default: `10`). The
package also respects PHP's `max_file_uploads` limit.

Image, PDF, and plain-text previews are available through the package preview
route. Image previews show the file type, size, modified date, and relative
path. Public-media images also include a copyable public URL.

Image thumbnails are generated with PHP GD and stored in an internal
`.thumbnails` directory. If GD is unavailable, or an image exceeds
`thumbnails.max_source_pixels` (default: `8,000,000`), the package uses a
lightweight generic image tile instead. Thumbnail generation is synchronous and
does not need Laravel queue configuration.

## Configuration

Publish the config file first, then change only the values your application
needs:

```bash
php artisan vendor:publish --tag=filament-file-manager-config
```

| Setting | Default | Purpose |
| --- | --- | --- |
| `max_upload_size` | `10240` KiB | Maximum upload size |
| `max_upload_files` | `10` | Maximum files accepted in one upload |
| `folders_per_page` | `10` | Folder page size in folders-first layout |
| `files_per_page` | `10` | File page size in folders-first layout |
| `items_per_page` | `20` | Page size when all items are together |
| `max_directory_depth` | `7` | Folder levels below the configured root |
| `show_item_layout` | `false` | Show the folders-first and all-items-together control |
| `preview_rate_limit` | `60` | Preview and thumbnail requests per minute |
| `thumbnails.max_source_pixels` | `8000000` | Largest source image considered for thumbnail generation |

The File Manager and File Picker Explorer show all items together by default.
Set `show_item_layout` to `true` when users should be able to choose **Folders
first**. Their choice is remembered for the current session.

`file_picker_manager_url` is retained only for legacy integrations. It is `null`
by default because panel URLs vary by application. A field can override it with
`->managerUrl('/custom-file-manager')`; this does not open a new tab.

## Styling and assets

The package ships with its own scoped stylesheet. It does not require a custom
Filament theme, Tailwind configuration, or Node.js build in the consuming
application. Buttons, links, focus states, upload states, pagination, and
picker selection indicators inherit the active panel primary colour.

Run this command during deployment after installing or updating the package:

```bash
php artisan filament:assets
```

The stylesheet is loaded in Filament's document head to avoid an unstyled flash
when opening File Manager.

## Access control and storage

- The default area uses Laravel's private `local` disk. It refuses the standard
  `public` disk and local roots below `public/` or `storage/app/public` when an
  area is configured as private.
- Replace the default `manageFileManager` check with an application-specific
  `FileManagerAuthorizer` when access depends on tenants or paths. Read the
  [multi-tenancy guide](docs/multi-tenancy.md) before enabling the package in a
  tenant application.
- Keep the package MIME and extension lists narrow for your use case. The
  picker can apply a narrower list with `allowedMimeTypes()`.
- The configured root cannot be renamed, moved, or deleted. A folder cannot be
  moved into itself or one of its descendants.
- Preview and thumbnail responses require authentication, are rate-limited per
  user or guest IP address, and send private no-store and `nosniff` headers.
- Add malware scanning in your application before making new uploads available
  to other users.

If you are upgrading from an earlier public-disk configuration, follow the
[upgrade notes](docs/upgrading.md) before deployment.

## Screenshots

<details>
<summary>View File Manager and UniFilePicker screenshots</summary>

### File Manager overview

![File Manager overview](docs/screenshots/file-manager-overview.png)

### Browse files and folders

![File Explorer](docs/screenshots/file-explorer.png)

### Upload files

![Upload files modal](docs/screenshots/file-upload-modal.png)

### Image preview and file details

![Image preview and file details](docs/screenshots/image-preview.png)

### Public and private storage areas

![Public and private storage areas](docs/screenshots/public-and-private-storage.png)

### Item layout and sorting

![Item layout and sorting](docs/screenshots/item-layout-and-sorting.png)

### Folders-first layout

![Folders-first layout](docs/screenshots/item-layout-folders-first.png)

### Separate pagination for folders and files

![Separate folder and file pagination](docs/screenshots/separate-pagination.png)

### Bulk delete

![Bulk delete](docs/screenshots/bulk-delete.png)

### UniFilePicker

![UniFilePicker field](docs/screenshots/uni-file-picker.png)

### Selected files in UniFilePicker

![Selected files in UniFilePicker](docs/screenshots/uni-file-picker-selected-files.png)

### Choose files from the library

![File Picker library](docs/screenshots/file-picker-library.png)

</details>

## Development

Run the formatting check and package tests before opening a pull request:

```bash
composer lint
composer test
```

Use `composer format` to apply the package's PSR-12 formatting rules.

## Project documents

- [Changelog](CHANGELOG.md)
- [Upgrade notes](docs/upgrading.md)
- [Multi-tenancy guide](docs/multi-tenancy.md)
- [Release policy](docs/releasing.md)
- [MIT License](LICENSE)

## Maintainer

UniFileManager is maintained by Sampath Arachchige.
