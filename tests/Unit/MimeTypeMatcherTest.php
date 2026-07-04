<?php

declare(strict_types=1);

use UniFileManager\FilamentFileManager\Support\MimeTypeMatcher;

it('matches exact and wildcard MIME types', function (): void {
    expect(MimeTypeMatcher::allows('image/png', ['image/*']))->toBeTrue()
        ->and(MimeTypeMatcher::allows('application/pdf', ['application/pdf']))->toBeTrue()
        ->and(MimeTypeMatcher::allows('application/pdf', ['image/*']))->toBeFalse();
});

it('rejects a missing MIME type', function (): void {
    expect(MimeTypeMatcher::allows('', ['image/*']))->toBeFalse();
});
