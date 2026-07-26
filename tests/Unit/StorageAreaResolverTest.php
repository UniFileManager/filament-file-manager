<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\FilamentFileManager\Filament\Forms\Components\UniFilePicker;
use UniFileManager\Core\Services\FileManager;

beforeEach(function (): void {
    Storage::fake('testing');
});

it('uses the configured storage-area resolver for File Manager paths', function (): void {
    Storage::disk('testing')->put('tenant-a/private.txt', 'tenant a');
    Storage::disk('testing')->put('tenant-b/private.txt', 'tenant b');

    app()->forgetInstance(FileManager::class);
    app()->bind(StorageAreaResolver::class, static fn (): StorageAreaResolver => new class () implements StorageAreaResolver {
        public function areas(): array
        {
            return [
                'private' => [
                    'enabled' => true,
                    'disk' => 'testing',
                    'root' => 'tenant-b',
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

    $items = app(FileManager::class)->list((object) ['id' => 1]);

    expect($items)->toHaveCount(1)
        ->and($items[0])->toMatchArray(['name' => 'private.txt', 'path' => 'private.txt', 'type' => 'file']);
});

it('uses the configured storage-area resolver when resolving a File Picker area', function (): void {
    app()->bind(StorageAreaResolver::class, static fn (): StorageAreaResolver => new class () implements StorageAreaResolver {
        public function areas(): array
        {
            return [
                'public' => [
                    'enabled' => true,
                    'disk' => 'testing',
                    'root' => 'tenant-b/media',
                    'visibility' => 'public',
                ],
            ];
        }

        public function resolve(string $area): ?array
        {
            $configuration = $this->areas()[$area] ?? null;

            return is_array($configuration) ? $configuration : null;
        }
    });

    expect(UniFilePicker::make('thumbnail')->getStorageArea())->toBe('public');
});
