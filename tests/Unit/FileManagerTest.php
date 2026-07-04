<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use UniFileManager\FilamentFileManager\Exceptions\FolderNotEmpty;
use UniFileManager\FilamentFileManager\Exceptions\InvalidFilePath;
use UniFileManager\FilamentFileManager\Exceptions\UnsafeDiskConfiguration;
use UniFileManager\FilamentFileManager\Services\FileManager;

beforeEach(function (): void {
    Storage::fake('testing');
});

it('lists only files below its configured root', function (): void {
    Storage::disk('testing')->put('tenant-a/contracts/terms.txt', 'terms');
    Storage::disk('testing')->put('outside.txt', 'private');

    $items = app(FileManager::class)->list((object) ['id' => 1]);

    expect($items)->toHaveCount(1)
        ->and($items[0])->toMatchArray(['name' => 'contracts', 'path' => 'contracts', 'type' => 'directory']);
});

it('rejects a public disk configured for private storage', function (): void {
    config()->set('filament-file-manager.storage_areas.private.disk', 'public');

    app(FileManager::class)->list((object) ['id' => 1]);
})->throws(UnsafeDiskConfiguration::class, 'File Manager must use a private disk. The public disk and web-served local storage roots are not supported.');

it('rejects directory traversal in browser supplied paths', function (): void {
    app(FileManager::class)->list((object) ['id' => 1], '../outside');
})->throws(InvalidFilePath::class);

it('never allows deleting the configured root', function (): void {
    app(FileManager::class)->delete((object) ['id' => 1], '');
})->throws(InvalidFilePath::class);

it('never allows renaming the configured root', function (): void {
    app(FileManager::class)->rename((object) ['id' => 1], '', 'renamed-root');
})->throws(InvalidFilePath::class, 'The configured root cannot be renamed.');

it('never allows moving the configured root', function (): void {
    app(FileManager::class)->move((object) ['id' => 1], '', 'destination');
})->throws(InvalidFilePath::class, 'The configured root cannot be moved.');

it('does not move a folder into one of its own subfolders', function (): void {
    Storage::disk('testing')->makeDirectory('tenant-a/folder/child');

    app(FileManager::class)->move((object) ['id' => 1], 'folder', 'folder/child');
})->throws(InvalidFilePath::class, 'A folder cannot be moved into itself or one of its subfolders.');

it('requires an existing folder as a move destination', function (): void {
    Storage::disk('testing')->put('tenant-a/document.txt', 'contents');

    app(FileManager::class)->move((object) ['id' => 1], 'document.txt', 'missing-folder');
})->throws(InvalidFilePath::class, 'The destination must be an existing folder.');

it('does not delete a folder that contains files or folders', function (): void {
    Storage::disk('testing')->put('tenant-a/occupied/document.txt', 'contents');
    Storage::disk('testing')->makeDirectory('tenant-a/occupied/nested');

    app(FileManager::class)->delete((object) ['id' => 1], 'occupied');
})->throws(FolderNotEmpty::class, 'This folder is not empty. Delete or move its contents first.');

it('creates sequentially named folders without overwriting an existing folder', function (): void {
    Storage::disk('testing')->makeDirectory('tenant-a/New folder');

    $path = app(FileManager::class)->createNewDirectory((object) ['id' => 1], '');

    expect($path)->toBe('New folder (2)')
        ->and(Storage::disk('testing')->directoryExists('tenant-a/New folder (2)'))->toBeTrue();
});

it('limits folder nesting to the configured maximum depth', function (): void {
    $parentPath = '';

    for ($level = 1; $level <= 7; $level++) {
        $name = 'Level '.$level;
        app(FileManager::class)->createDirectory((object) ['id' => 1], $parentPath, $name);
        $parentPath = $parentPath === '' ? $name : $parentPath.'/'.$name;
    }

    app(FileManager::class)->createDirectory((object) ['id' => 1], $parentPath, 'Level 8');
})->throws(InvalidFilePath::class, 'Folders can be nested up to 7 levels deep.');

it('allows saving a rename without changing the item name', function (): void {
    Storage::disk('testing')->makeDirectory('tenant-a/New folder');

    $path = app(FileManager::class)->rename((object) ['id' => 1], 'New folder', 'New folder');

    expect($path)->toBe('New folder')
        ->and(Storage::disk('testing')->directoryExists('tenant-a/New folder'))->toBeTrue();
});

it('renames a file within the configured root', function (): void {
    Storage::disk('testing')->put('tenant-a/old-name.txt', 'contents');

    $path = app(FileManager::class)->rename((object) ['id' => 1], 'old-name.txt', 'new-name.txt');

    expect($path)->toBe('new-name.txt')
        ->and(Storage::disk('testing')->exists('tenant-a/new-name.txt'))->toBeTrue()
        ->and(Storage::disk('testing')->exists('tenant-a/old-name.txt'))->toBeFalse();
});

it('preserves upload names and adds a suffix instead of overwriting a file', function (): void {
    Storage::disk('testing')->put('tenant-a/report.pdf', 'existing');

    $path = app(FileManager::class)->upload(
        (object) ['id' => 1],
        UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
    );

    expect($path)->toBe('report (2).pdf')
        ->and(Storage::disk('testing')->exists('tenant-a/report.pdf'))->toBeTrue()
        ->and(Storage::disk('testing')->exists('tenant-a/report (2).pdf'))->toBeTrue();
});

it('preserves normal client filename characters such as plus signs', function (): void {
    $path = app(FileManager::class)->upload(
        (object) ['id' => 1],
        UploadedFile::fake()->create('Amperative_Blue+Icon_filled_RGB-1920w.png', 10, 'image/png'),
    );

    expect($path)->toBe('Amperative_Blue+Icon_filled_RGB-1920w.png');
});
