<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Support;

use UniFileManager\FilamentFileManager\Contracts\FileManagerAuthorizer;

final class DefaultFileManagerAuthorizer implements FileManagerAuthorizer
{
    public function can(mixed $user, string $operation, string $path = ''): bool
    {
        return is_object($user)
            && method_exists($user, 'can')
            && $user->can('manageFileManager');
    }
}
