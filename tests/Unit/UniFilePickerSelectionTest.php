<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use UniFileManager\FilamentFileManager\Contracts\FileManagerAuthorizer;
use UniFileManager\FilamentFileManager\Filament\Forms\Components\UniFilePicker;
use UniFileManager\FilamentFileManager\Services\FileManager;

function pickerInSchema(UniFilePicker $picker): UniFilePicker
{
    Schema::make()
        ->statePath('data')
        ->components([$picker])
        ->getComponents();

    return $picker;
}

beforeEach(function (): void {
    Storage::fake('testing');
    auth()->setUser(new GenericUser(['id' => 1]));
});

it('accepts a permitted file inside the configured picker directory', function (): void {
    Storage::disk('testing')->put('tenant-a/avatars/jane.jpg', 'image');

    $picker = pickerInSchema(UniFilePicker::make('avatar')
        ->directory('avatars')
        ->allowedMimeTypes(['image/jpeg']));

    expect($picker->mutateDehydratedState('avatars/jane.jpg'))->toBe('avatars/jane.jpg');
});

it('rejects a browser-provided path outside the configured picker directory', function (): void {
    Storage::disk('testing')->put('tenant-a/avatars/jane.jpg', 'image');
    Storage::disk('testing')->put('tenant-a/private/report.pdf', 'document');

    $picker = pickerInSchema(UniFilePicker::make('avatar')
        ->directory('avatars')
        ->allowedMimeTypes(['image/jpeg']));

    $picker->mutateStateForValidation('private/report.pdf');
})->throws(ValidationException::class);

it('rejects a selected file that does not match the picker MIME types', function (): void {
    Storage::disk('testing')->put('tenant-a/avatars/report.pdf', 'document');

    $picker = pickerInSchema(UniFilePicker::make('avatar')
        ->directory('avatars')
        ->allowedMimeTypes(['image/jpeg']));

    $picker->validateSelectionState('avatars/report.pdf');
})->throws(ValidationException::class);

it('rejects a file the current user cannot view', function (): void {
    Storage::disk('testing')->put('tenant-a/avatars/jane.jpg', 'image');
    app()->forgetInstance(FileManager::class);
    app()->bind(FileManagerAuthorizer::class, static fn (): FileManagerAuthorizer => new class () implements FileManagerAuthorizer {
        public function can(mixed $user, string $operation, string $path = ''): bool
        {
            return false;
        }
    });

    $picker = pickerInSchema(UniFilePicker::make('avatar')
        ->directory('avatars')
        ->allowedMimeTypes(['image/jpeg']));

    $picker->validateSelectionState('avatars/jane.jpg');
})->throws(ValidationException::class);

it('enforces the multiple picker maximum when state is provided by the browser', function (): void {
    Storage::disk('testing')->put('tenant-a/images/one.jpg', 'one');
    Storage::disk('testing')->put('tenant-a/images/two.jpg', 'two');

    $picker = pickerInSchema(UniFilePicker::make('images')
        ->multiple()
        ->maxFiles(1)
        ->directory('images')
        ->allowedMimeTypes(['image/jpeg']));

    $picker->validateSelectionState(['images/one.jpg', 'images/two.jpg']);
})->throws(ValidationException::class);
