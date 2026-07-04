<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UniFileManager\FilamentFileManager\Services\FileManager;

final class FilePreviewController
{
    public function __invoke(Request $request, FileManager $fileManager): StreamedResponse
    {
        $path = (string) $request->query('path', '');
        $fileManager = $fileManager->forArea((string) $request->query('area', 'private'));

        return $request->boolean('thumbnail')
            ? $fileManager->thumbnail($request->user(), $path)
            : $fileManager->preview($request->user(), $path);
    }
}
