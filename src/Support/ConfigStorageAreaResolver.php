<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Support;

use UniFileManager\FilamentFileManager\Contracts\StorageAreaResolver;

final class ConfigStorageAreaResolver implements StorageAreaResolver
{
    public function areas(): array
    {
        $areas = config('filament-file-manager.storage_areas', []);

        return is_array($areas) ? $areas : [];
    }

    public function resolve(string $area): ?array
    {
        $configuration = $this->areas()[$area] ?? null;

        return is_array($configuration) ? $configuration : null;
    }
}
