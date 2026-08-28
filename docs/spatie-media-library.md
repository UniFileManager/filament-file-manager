# Spatie Media Library

UniFilePicker can sync selected File Manager files into a Spatie Media Library
collection. This is useful when an Eloquent model should expose selected files
through Spatie's media APIs while the actual files remain managed by
UniFileManager storage areas.

## Installation

Install Spatie Media Library in the host Laravel application:

```bash
composer require spatie/laravel-medialibrary
```

Follow Spatie's installation steps for publishing and running its migrations.
The model that owns the picker field must implement Spatie's `HasMedia`
contract.

## Usage

```php
use UniFileManager\FilamentFileManager\Filament\Forms\Components\UniFilePicker;

UniFilePicker::make('gallery')
    ->multiple()
    ->storageArea('public')
    ->collection('gallery');
```

The field writes Spatie media rows for files that already exist in the selected
UniFileManager storage area. Removing a picker selection removes the media row
from the collection, but it does not delete the underlying file-manager object.

## Storage Area Roots

When the selected storage area uses a root prefix, UniFilePicker stores the
original object path in the media row's `external_dir` custom property.
Configure your Spatie path generator to honour that property when resolving
existing file-manager objects. Without that path-generator support, Spatie will
fall back to its default id-based media paths and will not point at the existing
UniFileManager object.

## Notes

- Call `collection()` only when `spatie/laravel-medialibrary` is installed.
- Use `storageArea()` to target a custom configured storage area.
- Use `privateMedia()` or `publicMedia()` for the built-in private and public
  areas.
- Keep sensitive documents in private storage areas.
