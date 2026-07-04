<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Support;

use UniFileManager\FilamentFileManager\Exceptions\InvalidFilePath;

final class DirectoryScope
{
    public static function normalise(?string $path): string
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');

        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        if (in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || $segments[0] === (string) config('filament-file-manager.thumbnails.directory')) {
            throw new InvalidFilePath('The File Picker directory must stay within the configured root.');
        }

        return implode('/', $segments);
    }

    public static function contains(string $directory, string $path): bool
    {
        return $directory === '' || $path === $directory || str_starts_with($path.'/', $directory.'/');
    }
}
