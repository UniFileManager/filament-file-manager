<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use UniFileManager\FilamentFileManager\Http\Controllers\FilePreviewController;

Route::middleware(['web', 'auth', 'throttle:filament-file-manager-previews'])->get('/filament-file-manager/preview', FilePreviewController::class)
    ->name('filament-file-manager.preview');
