<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use UniFileManager\FilamentFileManager\Filament\Pages\FileManager;
use UniFileManager\FilamentFileManager\Livewire\FilePickerExplorer;
use UniFileManager\FilamentFileManager\Livewire\UniFilePickerUploader;

beforeEach(function (): void {
    config()->set('livewire.temporary_file_upload.disk', 's3');
    config()->set('filesystems.disks.s3.driver', 's3');
    config()->set('filament-file-manager.max_upload_files', 10);

    auth()->setUser(new GenericUser(['id' => 1]));
});

it('limits direct picker browser uploads to one file when Livewire uses S3 temporary uploads', function (): void {
    Livewire::test(UniFilePickerUploader::class, [
        'pickerId' => 'avatar-picker',
        'multiple' => true,
        'maxFiles' => 5,
    ])
        ->assertSet('multiple', true)
        ->assertSet('maxFiles', 5)
        ->call('supportsMultipleTemporaryUploads')
        ->assertReturned(false)
        ->call('maximumUploadFiles')
        ->assertReturned(1);
});

it('limits picker library browser uploads to one file when Livewire uses S3 temporary uploads', function (): void {
    Livewire::test(FilePickerExplorer::class, [
        'pickerId' => 'gallery-picker',
        'multiple' => true,
        'maxFiles' => 5,
    ])
        ->assertSet('multiple', true)
        ->assertSet('maxFiles', 5)
        ->call('supportsMultipleTemporaryUploads')
        ->assertReturned(false)
        ->call('maximumUploadFiles')
        ->assertReturned(1);
});

it('limits File Manager browser uploads to one file when Livewire uses S3 temporary uploads', function (): void {
    Livewire::test(FileManager::class)
        ->call('supportsMultipleTemporaryUploads')
        ->assertReturned(false)
        ->call('maximumUploadFiles')
        ->assertReturned(1);
});
