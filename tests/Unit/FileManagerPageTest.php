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
