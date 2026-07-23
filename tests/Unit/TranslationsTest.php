<?php

declare(strict_types=1);

it('loads the package translations', function (): void {
    app()->setLocale('en');

    expect(__('filament-file-manager::file-manager.upload_files'))->toBe('Upload files')
        ->and(trans_choice('filament-file-manager::file-manager.select_up_to_files', 2, ['count' => 2]))->toBe('You can select up to 2 files');
});
