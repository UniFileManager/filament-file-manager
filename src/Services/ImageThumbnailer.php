<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Services;

use Illuminate\Filesystem\FilesystemAdapter;

final class ImageThumbnailer
{
    public function create(FilesystemAdapter $disk, string $sourcePath, string $root, string $visibility): ?string
    {
        if (! config('filament-file-manager.thumbnails.enabled')
            || ! function_exists('imagecreatefromstring')
            || ! function_exists('imagejpeg')) {
            return null;
        }

        $contents = $disk->get($sourcePath);
        $dimensions = @getimagesizefromstring($contents);
        if ($dimensions === false || ($dimensions[0] * $dimensions[1]) > (int) config('filament-file-manager.thumbnails.max_source_pixels')) {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return null;
        }

        $maximum = (int) config('filament-file-manager.thumbnails.max_dimension');
        $scale = min(1, $maximum / max($dimensions[0], $dimensions[1]));
        $width = max(1, (int) round($dimensions[0] * $scale));
        $height = max(1, (int) round($dimensions[1] * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);
        imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 255, 255, 255));
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);

        ob_start();
        imagejpeg($thumbnail, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($thumbnail);
        imagedestroy($source);

        if ($jpeg === false) {
            return null;
        }

        $path = $this->path($sourcePath, $root);
        $disk->put($path, $jpeg, ['visibility' => $visibility]);

        return $path;
    }

    public function path(string $sourcePath, string $root): string
    {
        $root = trim($root, '/');
        $directory = trim((string) config('filament-file-manager.thumbnails.directory'), '/');

        return ($root === '' ? $directory : $root.'/'.$directory)
            .'/'.hash('sha256', $sourcePath).'.jpg';
    }
}
