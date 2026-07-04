# Filament File Manager

A Laravel Filament 4/5 package for managing files beneath a configured storage-disk root. It includes a panel page for browsing, uploading, organising, downloading, renaming, and deleting files, plus a form field for storing selected relative paths.

## Install

```bash
composer require unifilemanager/filament-file-manager
php artisan vendor:publish --tag=filament-file-manager-config
```

Register it in a Filament panel provider:

```php
use UniFileManager\FilamentFileManager\FilamentFileManagerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([FilamentFileManagerPlugin::make()]);
}
```

The page is available at your panel's `file-manager` route. Add it to your application's navigation if needed.

## File picker

`UniFilePicker` is the preferred form component. It stores relative paths within
the configured File Manager root.

### Single file

```php
use UniFileManager\FilamentFileManager\Filament\Forms\Components\UniFilePicker;

UniFilePicker::make('attachment')
    ->clearable();
```

After a single file is selected, the drop area is replaced with a responsive file
card. Hover the card to remove that selection. A separate **Clear selection**
button is intentionally not shown for single-file fields.

Use Filament's usual `->helperText(...)` method for upload guidance. The text is
shown immediately below the drop area and is hidden after that area is replaced
by a selected-file card.

Change the placement-box copy when the default wording does not fit the form:

```php
UniFilePicker::make('attachment')
    ->uploadHeading('Add the product image')
    ->uploadDescription('Drop an image here or choose one from the library.');
```

### Allowed MIME types

By default, a picker accepts JPEG, PNG, WebP and GIF images, PDF, plain text,
DOC and DOCX files. Use `allowedMimeTypes()` to narrow the file types for one
field. The restriction applies to library selection and both picker upload
flows; it does not expand the package-wide upload allow-list.

```php
UniFilePicker::make('invoice')
    ->allowedMimeTypes(['application/pdf']);

UniFilePicker::make('product_image')
    ->allowedMimeTypes(['image/*']);
```

### Directory scope

Restrict a picker to one directory below the configured File Manager root. The
scope applies to browsing, uploads, file selection, and parent-folder
navigation; it cannot be bypassed from the browser.

```php
UniFilePicker::make('avatar')
    ->directory('avatars')
    ->allowedMimeTypes(['image/*']);
```

Stored values remain relative to the File Manager root, for example
`avatars/jane.png`. Create the scoped folder before using the field.

### Multiple files and limits

```php
UniFilePicker::make('gallery')
    ->multiple()
    ->maxFiles(10)
    ->clearable();
```

Multiple pickers span the form row by default so the compact list has enough
room. Override that after `multiple()` when a narrower layout is intentional:

```php
UniFilePicker::make('gallery')
    ->multiple()
    ->columnSpan(1);
```

Multiple selections use a compact list with a small thumbnail by default. For
image galleries, opt into larger image cards:

```php
UniFilePicker::make('gallery')
    ->multiple()
    ->imageCardView();
```

When opening the library from a multiple picker, users can select up to the
field's remaining `maxFiles()` capacity across folders and pages, then confirm
all selections with **Use selected files**.

For a multiple field, the drop area remains visible while there is capacity for
another file. It hides when the configured `maxFiles()` limit is reached, then
returns as soon as an item is removed. The **Clear selection** button clears all
currently selected files. The value passed to `maxFiles()` cannot exceed the
package's `max_upload_files` setting, which defaults to `10`.

Duplicate selections are blocked by default. For fields such as a slideshow
where the same library file may appear more than once, enable them explicitly:

```php
UniFilePicker::make('slides')
    ->multiple()
    ->maxFiles(10)
    ->allowDuplicateSelection();
```

Each duplicate counts toward `maxFiles()` and can be removed independently.

The field supports drag-and-drop uploads, choosing files from the focused File
Explorer modal, image preview, and responsive removable thumbnail cards. Its
library includes the upload flow, so users can upload a file and choose it in
one place. `FilePicker` remains available as a backward-compatible alias for
`UniFilePicker`.

### Library configuration

The embedded File Picker library is enabled by default through
`file_picker_library`. Disable it for one field with `->library(false)`, or
disable it package-wide in `config/filament-file-manager.php`.

`file_picker_manager_url` is `null` by default because Filament panel paths are
application-specific. Set it only when a legacy integration needs the metadata,
for example `/management/file-manager`. A field may override it with
`->managerUrl('/custom-file-manager')`; this method is retained for backwards
compatibility and does not open a new tab.

The picker opens a focused File Explorer modal with folder navigation, search,
sorting, item layout controls, pagination, and automatic uploads. It defaults
to a combined layout and remembers each user's layout choice. It intentionally
does not expose panel navigation, deletion, renaming, or other file-management
actions.

## Uploads and previews

Uploads preserve the safe client filename. Existing files are never overwritten: a collision becomes `filename (2).ext`, then `filename (3).ext`.

Normal filename characters such as spaces, Unicode, `+`, parentheses, underscores, and hyphens are preserved. Only cross-platform-unsafe characters (path separators, control characters, and Windows-reserved punctuation) are replaced during upload.

Selected files upload immediately. Set the maximum number of files with
`max_upload_files` (default: `10`) in the package configuration; the package
also honours PHP's `max_file_uploads` limit. Image, PDF, and plain-text previews
are served through an authorised package route, so private disks remain protected.

The File Manager image preview includes the file type, size, modified date, and
relative path. Public-media images also show a copyable public URL. The modal
uses the metadata already loaded for the file card, so opening it does not make
an additional request for file details.

Image thumbnails are generated with PHP's GD extension and stored in an internal,
inaccessible `.thumbnails` directory. If GD is unavailable, the package shows a
lightweight generic image tile.

Thumbnail generation is synchronous and needs no Laravel queue configuration. Images above `thumbnails.max_source_pixels` (default: `8,000,000`) skip generation and show a lightweight generic image tile instead. Increase this limit only when your PHP memory limits and expected upload sizes support it.

## Assets

The package ships its own scoped stylesheet. It does not need a custom Filament theme, Tailwind configuration, or Node.js build in the consuming application.

Buttons, links, focus states, upload states, pagination, and picker selection
indicators inherit the active Filament panel's primary colour automatically. No
package-specific theme configuration is required.

After installing or updating the package, publish registered Filament assets:

```bash
php artisan filament:assets
```

Run this command as part of application deployment too. The stylesheet is loaded in the Filament document head, preventing an unstyled flash when opening the File Manager.

## Pagination

Set `folders_per_page` and `files_per_page` in `config/filament-file-manager.php` to control separate-mode pagination (defaults: `10` each). Set `items_per_page` for all-together mode (default: `20`).

## Item layout

Items are shown together by default. To let users choose **Folders first** or
**All items together** in File Manager and File Picker Explorer, set
`show_item_layout` to `true` in `config/filament-file-manager.php`. When it is
disabled, the layout control is hidden and saved layout preferences are ignored.

## Folder depth

Folders can be nested up to `7` levels below the configured File Manager root.
Change `max_directory_depth` in `config/filament-file-manager.php` only when a
deeper structure is genuinely needed. The same limit is enforced when moving a
folder tree.

## Security checklist

- The package defaults to Laravel's private `local` disk. It refuses the standard `public` disk and local roots below `public/` or `storage/app/public`. Keep the managed root outside web-served storage paths.
- If upgrading from an earlier release, change a published `disk => 'public'` value to `disk => 'local'` or another private disk before deploying.
- Access is denied by default unless the signed-in user has Laravel's `manageFileManager` ability. Replace the authorizer with an application-specific implementation of `FileManagerAuthorizer` to enforce tenant and path rules. See [multi-tenant setup](docs/multi-tenancy.md) before enabling the package for a multi-tenant application.
- Keep MIME and extension allow-lists narrow. Unsafe client filename characters are normalized and upload collisions receive numeric suffixes.
- Keep `visibility` set to `private` and provide downloads through authorization checks.
- Preview and thumbnail routes require authentication, are rate limited per user (or IP), and return private no-store responses with `nosniff` headers. Set `preview_rate_limit` in the package config to tune the default `60` requests per minute.
- The configured root cannot be renamed, moved, or deleted. Folder moves cannot target the folder itself or one of its descendants.
- Add malware scanning in your application's queue before making uploads available to other users.

```php
'authorizer' => App\Support\FileManagerAuthorizer::class,
```

## Public and private media

UniFileManager keeps private files and public website media in separate,
server-configured storage areas. It never accepts a disk, root, or visibility
value from the browser.

Private storage is enabled by default. Enable public media only when you have configured a deliberate public disk or CDN:

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

Use explicit storage-area methods when both areas are enabled:

```php
UniFilePicker::make('invoice')
    ->privateMedia()
    ->directory('invoices');

UniFilePicker::make('thumbnail')
    ->publicMedia()
    ->directory('courses/thumbnails')
    ->allowedMimeTypes(['image/*']);
```

If exactly one area is enabled, `UniFilePicker` selects it automatically. If both are enabled, it uses `file_picker_default_area` (`private` by default). Use `->publicMedia()` for website assets and `->privateMedia()` when you want the field definition to be explicit. You may set the default to `null` to enforce an explicit choice during development.

`public()` and `private()` cannot be used as PHP method names, so the package uses `publicMedia()` and `privateMedia()`.

Public files are reachable from their configured storage disk. File Manager
administration remains permission-protected, but a public asset URL does not
require File Manager access. Keep documents, invoices, customer uploads, and
other sensitive files in the private area.

When more than one storage area is enabled, File Manager displays a storage-area selector. The selected area is remembered for the current user session. Folder navigation, uploads, previews, downloads, renaming, and deletion always stay inside that selected area; private and public files are never mixed in one directory listing.

## Current scope

The panel includes a reusable File Picker field, inline file rename, single-file selection, and multi-select bulk deletion. The service API also supports listing, upload, folder creation, deletion, download, move, and rename.

## Project documents

- [Changelog](CHANGELOG.md)
- [Upgrade notes](docs/upgrading.md)
- [Release policy](docs/releasing.md)
- [MIT License](LICENSE)

## Maintainer

UniFileManager is maintained by Sampath Arachchige.
