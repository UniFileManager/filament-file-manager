<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use UniFileManager\FilamentFileManager\Filament\Pages\FileManager;

beforeEach(function (): void {
    Storage::fake('testing');
    auth()->setUser(new GenericUser(['id' => 1]));
});

it('removes a new folder when its name is cancelled', function (): void {
    Livewire::test(FileManager::class)
        ->call('createNewDirectory')
        ->assertSet('isCreatingDirectory', true)
        ->call('cancelRename')
        ->assertSet('renamingPath', null)
        ->assertSet('isCreatingDirectory', false);

    expect(Storage::disk('testing')->directoryMissing('tenant-a/New folder'))->toBeTrue();
});

it('keeps an existing folder when its rename is cancelled', function (): void {
    Storage::disk('testing')->makeDirectory('tenant-a/Existing folder');

    Livewire::test(FileManager::class)
        ->call('beginRename', 'Existing folder')
        ->call('cancelRename');

    expect(Storage::disk('testing')->directoryExists('tenant-a/Existing folder'))->toBeTrue();
});

it('uses configured labels and icons for storage areas', function (): void {
    config()->set('filament-file-manager.storage_areas', [
        'private' => ['enabled' => false],
        'documents' => [
            'enabled' => true,
            'label' => 'Documents',
            'icon' => 'heroicon-o-document-text',
            'disk' => 'testing',
            'root' => 'tenant-a/documents',
            'visibility' => 'private',
        ],
    ]);

    $page = new FileManager();

    expect($page->availableStorageAreas())
        ->toBe(['documents' => 'Documents'])
        ->and($page->storageAreaIcon('documents'))
        ->toBe('heroicon-o-document-text');
});

it('selects files on the current page without selecting folders', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');
    Storage::disk('testing')->put('tenant-a/beta.txt', 'beta');
    Storage::disk('testing')->makeDirectory('tenant-a/archive');
    config()->set('filament-file-manager.items_per_page', 1);

    Livewire::test(FileManager::class)
        ->call('selectCurrentPageFiles')
        ->assertSet('selectedPaths', ['alpha.txt']);
});

it('recognises when all files on the current page are selected', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');
    Storage::disk('testing')->put('tenant-a/beta.txt', 'beta');
    config()->set('filament-file-manager.items_per_page', 1);

    $component = Livewire::test(FileManager::class)
        ->call('selectCurrentPageFiles');

    expect($component->instance()->currentPageFilesSelected())->toBeTrue();
});

it('selects all files across pages without selecting folders', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');
    Storage::disk('testing')->put('tenant-a/beta.txt', 'beta');
    Storage::disk('testing')->makeDirectory('tenant-a/archive');
    config()->set('filament-file-manager.items_per_page', 1);

    Livewire::test(FileManager::class)
        ->call('selectAllFiles')
        ->assertSet('selectedPaths', ['alpha.txt', 'beta.txt']);
});

it('recognises when all current files are selected', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');

    $component = Livewire::test(FileManager::class)
        ->call('selectAllFiles');

    expect($component->instance()->allFilesSelected())->toBeTrue();
});

it('selects only files matching the current search when selecting all', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');
    Storage::disk('testing')->put('tenant-a/beta.txt', 'beta');

    Livewire::test(FileManager::class)
        ->set('search', 'alpha')
        ->call('selectAllFiles')
        ->assertSet('selectedPaths', ['alpha.txt']);
});

it('adds all files without clearing manually selected folders', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');
    Storage::disk('testing')->makeDirectory('tenant-a/archive');

    Livewire::test(FileManager::class)
        ->call('toggleSelection', 'archive')
        ->call('selectAllFiles')
        ->assertSet('selectedPaths', ['archive', 'alpha.txt']);
});

it('selects an inclusive file range in either direction and skips folders', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');
    Storage::disk('testing')->makeDirectory('tenant-a/archive');
    Storage::disk('testing')->put('tenant-a/charlie.txt', 'charlie');

    Livewire::test(FileManager::class)
        ->call('selectFileRange', 'charlie.txt', 'alpha.txt')
        ->assertSet('selectedPaths', ['alpha.txt', 'charlie.txt']);
});

it('falls back to normal selection when a range anchor is unavailable', function (): void {
    Storage::disk('testing')->put('tenant-a/alpha.txt', 'alpha');

    Livewire::test(FileManager::class)
        ->call('selectFileRange', 'missing.txt', 'alpha.txt')
        ->assertSet('selectedPaths', ['alpha.txt']);
});

it('ignores a range target that is not a current file', function (): void {
    Storage::disk('testing')->makeDirectory('tenant-a/archive');

    Livewire::test(FileManager::class)
        ->call('selectFileRange', 'missing.txt', 'archive')
        ->assertSet('selectedPaths', []);
});
