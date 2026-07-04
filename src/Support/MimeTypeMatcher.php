<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Support;

final class MimeTypeMatcher
{
    public const DEFAULT_FILE_PICKER_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** @param list<string> $allowedMimeTypes */
    public static function allows(string $mimeType, array $allowedMimeTypes): bool
    {
        $mimeType = strtolower(trim($mimeType));

        if ($mimeType === '') {
            return false;
        }

        foreach ($allowedMimeTypes as $allowedMimeType) {
            $allowedMimeType = strtolower(trim($allowedMimeType));

            if ($allowedMimeType === '*/*' || $allowedMimeType === $mimeType) {
                return true;
            }

            if (str_ends_with($allowedMimeType, '/*')
                && str_starts_with($mimeType, substr($allowedMimeType, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
