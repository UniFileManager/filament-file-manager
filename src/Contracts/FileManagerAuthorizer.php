<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Contracts;

interface FileManagerAuthorizer
{
    public function can(mixed $user, string $operation, string $path = ''): bool;
}
