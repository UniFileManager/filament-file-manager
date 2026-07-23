<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Contracts;

interface StorageAreaResolver
{
    /**
     * Return every storage area available for the current server-side context.
     *
     * @return array<string, array{enabled?: bool, disk?: string, root?: string, visibility?: string}>
     */
    public function areas(): array;

    /**
     * Return the requested storage area, or null when it is unavailable.
     *
     * @return array{enabled?: bool, disk?: string, root?: string, visibility?: string}|null
     */
    public function resolve(string $area): ?array;
}
