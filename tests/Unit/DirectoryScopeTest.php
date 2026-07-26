<?php

declare(strict_types=1);

use UniFileManager\Core\Exceptions\InvalidFilePath;
use UniFileManager\Core\Support\DirectoryScope;

it('normalises a File Picker directory below the configured root', function (): void {
    expect(DirectoryScope::normalise('/avatars/profile-images/'))->toBe('avatars/profile-images');
});

it('rejects directory traversal in a File Picker directory', function (): void {
    DirectoryScope::normalise('../private');
})->throws(InvalidFilePath::class);

it('checks whether a path is contained by a File Picker directory', function (): void {
    expect(DirectoryScope::contains('avatars', 'avatars/profile.png'))->toBeTrue()
        ->and(DirectoryScope::contains('avatars', 'documents/contract.pdf'))->toBeFalse();
});
