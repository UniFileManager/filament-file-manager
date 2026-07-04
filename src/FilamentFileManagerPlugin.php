<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager;

use Filament\Contracts\Plugin;
use Filament\Panel;
use UniFileManager\FilamentFileManager\Filament\Pages\FileManager;

final class FilamentFileManagerPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-file-manager';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FileManager::class]);
    }

    public function boot(Panel $panel): void
    {
    }
}
