<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\FilamentFileManager\Filament\Forms\Components\UniFilePicker;

final class TestableMediaLibraryPicker extends UniFilePicker
{
    public function diskKeyFor(string $path): string
    {
        return $this->toDiskKey($path);
    }

    public function pickerPathFor(string $path): string
    {
        return $this->toPickerPath($path);
    }
}

beforeEach(function (): void {
    Storage::fake('testing');
});

it('requires spatie media library before enabling collection storage', function (): void {
    UniFilePicker::make('images')->collection('gallery');
})->throws(LogicException::class, 'spatie/laravel-medialibrary');

it('converts picker paths through the selected storage area root', function (): void {
    app()->bind(StorageAreaResolver::class, static fn (): StorageAreaResolver => new class () implements StorageAreaResolver {
        public function areas(): array
        {
            return [
                'library' => [
                    'enabled' => true,
                    'disk' => 'testing',
                    'root' => 'tenant-a/media',
                    'visibility' => 'private',
                ],
            ];
        }

        public function resolve(string $area): ?array
        {
            $configuration = $this->areas()[$area] ?? null;

            return is_array($configuration) ? $configuration : null;
        }
    });

    $picker = TestableMediaLibraryPicker::make('images')->storageArea('library');

    expect($picker->diskKeyFor('gallery/photo.jpg'))->toBe('tenant-a/media/gallery/photo.jpg')
        ->and($picker->pickerPathFor('tenant-a/media/gallery/photo.jpg'))->toBe('gallery/photo.jpg');
});

it('leaves media-library paths outside the storage area root unchanged', function (): void {
    app()->bind(StorageAreaResolver::class, static fn (): StorageAreaResolver => new class () implements StorageAreaResolver {
        public function areas(): array
        {
            return [
                'library' => [
                    'enabled' => true,
                    'disk' => 'testing',
                    'root' => 'tenant-a/media',
                    'visibility' => 'private',
                ],
            ];
        }

        public function resolve(string $area): ?array
        {
            $configuration = $this->areas()[$area] ?? null;

            return is_array($configuration) ? $configuration : null;
        }
    });

    $picker = TestableMediaLibraryPicker::make('images')->storageArea('library');

    expect($picker->pickerPathFor('1/photo.jpg'))->toBe('1/photo.jpg');
});
