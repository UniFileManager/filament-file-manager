<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Tests;

use BladeUI\Icons\BladeIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Livewire\LivewireServiceProvider;
use UniFileManager\FilamentFileManager\Contracts\FileManagerAuthorizer;
use UniFileManager\FilamentFileManager\FilamentFileManagerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            LivewireServiceProvider::class,
            FilamentFileManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filament-file-manager.disk', 'testing');
        $app['config']->set('filament-file-manager.root', 'tenant-a');
        $app['config']->set('filament-file-manager.storage_areas.private', [
            'enabled' => true,
            'disk' => 'testing',
            'root' => 'tenant-a',
            'visibility' => 'private',
        ]);
        $app['config']->set('filesystems.disks.testing', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/testing')]);
        $app->bind(FileManagerAuthorizer::class, static fn (): FileManagerAuthorizer => new class () implements FileManagerAuthorizer {
            public function can(mixed $user, string $operation, string $path = ''): bool
            {
                return $user !== null;
            }
        });
    }
}
