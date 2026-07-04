<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use UniFileManager\FilamentFileManager\Contracts\FileManagerAuthorizer;
use UniFileManager\FilamentFileManager\Exceptions\FolderNotEmpty;
use UniFileManager\FilamentFileManager\Exceptions\InvalidFilePath;
use UniFileManager\FilamentFileManager\Exceptions\UnsafeDiskConfiguration;

final class FileManager
{
    private string $storageArea = 'private';

    public function __construct(
        private readonly FileManagerAuthorizer $authorizer,
        private readonly ImageThumbnailer $thumbnailer,
    )
    {
    }

    /** Return an isolated manager for the given configured storage area. */
    public function forArea(string $area): self
    {
        $manager = clone $this;
        $manager->storageArea = $manager->normaliseStorageArea($area);

        return $manager;
    }

    /** @return list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public function list(mixed $user, string $path = ''): array
    {
        $relativePath = $this->normalisePath($path);
        $this->authorize($user, 'view', $relativePath);

        $items = [];
        foreach ($this->disk()->directories($this->absolutePath($relativePath)) as $directory) {
            if (basename($directory) === config('filament-file-manager.thumbnails.directory')) {
                continue;
            }
            $items[] = [
                'name' => basename($directory),
                'path' => $this->relativeFromAbsolute($directory),
                'type' => 'directory',
                'modified_at' => $this->disk()->lastModified($directory),
            ];
        }

        foreach ($this->disk()->files($this->absolutePath($relativePath)) as $file) {
            $items[] = [
                'name' => basename($file),
                'path' => $this->relativeFromAbsolute($file),
                'type' => 'file',
                'size' => $this->disk()->size($file),
                'modified_at' => $this->disk()->lastModified($file),
                'mime_type' => $this->disk()->mimeType($file),
            ];
        }

        usort($items, static fn (array $left, array $right): int => [$left['type'] !== 'directory', $left['name']] <=> [$right['type'] !== 'directory', $right['name']]);

        return $items;
    }

    public function createDirectory(mixed $user, string $parentPath, string $name): void
    {
        $parentPath = $this->normalisePath($parentPath);
        $directory = $this->validateName($name);
        $this->authorize($user, 'create-directory', $parentPath);
        $path = $this->join($parentPath, $directory);
        $this->assertDirectoryDepth($path);
        $this->disk()->makeDirectory($this->absolutePath($path));
    }

    public function createNewDirectory(mixed $user, string $parentPath, string $baseName = 'New folder'): string
    {
        $parentPath = $this->normalisePath($parentPath);
        $baseName = $this->validateName($baseName);
        $this->authorize($user, 'create-directory', $parentPath);
        $this->assertDirectoryDepth($this->join($parentPath, $baseName));

        for ($suffix = 1; $suffix <= 1_000; $suffix++) {
            $name = $suffix === 1 ? $baseName : sprintf('%s (%d)', $baseName, $suffix);
            $path = $this->join($parentPath, $name);
            $absolutePath = $this->absolutePath($path);

            if ($this->disk()->exists($absolutePath)) {
                continue;
            }

            $this->disk()->makeDirectory($absolutePath);

            if ($this->disk()->directoryExists($absolutePath)) {
                return $path;
            }
        }

        throw new InvalidFilePath('A unique folder name could not be generated.');
    }

    public function upload(mixed $user, UploadedFile $file, string $directory = ''): string
    {
        $directory = $this->normalisePath($directory);
        $this->authorize($user, 'upload', $directory);
        $this->validateUpload($file);

        $name = $this->uniqueFileName($directory, $this->uploadFileName($file));
        $path = $this->absolutePath($this->join($directory, $name));
        $this->disk()->putFileAs(dirname($path), $file, basename($path), ['visibility' => $this->visibility()]);
        $this->thumbnailer->create($this->disk(), $path, $this->root(), $this->visibility());

        return $this->relativeFromAbsolute($path);
    }

    /** @param list<UploadedFile> $files */
    public function validateUploads(array $files): void
    {
        foreach ($files as $file) {
            $this->validateUpload($file);
        }
    }

    public function delete(mixed $user, string $path): void
    {
        $path = $this->normalisePath($path);
        if ($path === '') {
            throw new InvalidFilePath('The configured root cannot be deleted.');
        }

        $this->authorize($user, 'delete', $path);
        $absolutePath = $this->absolutePath($path);

        if ($this->disk()->directoryExists($absolutePath)) {
            if ($this->disk()->files($absolutePath) !== [] || $this->disk()->directories($absolutePath) !== []) {
                throw new FolderNotEmpty('This folder is not empty. Delete or move its contents first.');
            }

            $this->disk()->deleteDirectory($absolutePath);

            return;
        }

        $this->disk()->delete($this->thumbnailPath($absolutePath));
        $this->disk()->delete($absolutePath);
    }

    public function move(mixed $user, string $source, string $destinationDirectory): string
    {
        $source = $this->normalisePath($source);
        $destinationDirectory = $this->normalisePath($destinationDirectory);

        if ($source === '') {
            throw new InvalidFilePath('The configured root cannot be moved.');
        }

        $this->authorize($user, 'move', $source);
        $this->authorize($user, 'create', $destinationDirectory);

        $sourcePath = $this->absolutePath($source);
        $destinationPath = $this->absolutePath($destinationDirectory);
        if (! $this->disk()->exists($sourcePath)) {
            throw new InvalidFilePath('The source item does not exist.');
        }

        if (! $this->disk()->directoryExists($destinationPath)) {
            throw new InvalidFilePath('The destination must be an existing folder.');
        }

        if ($this->disk()->directoryExists($sourcePath) && $this->isSameOrDescendantDirectory($destinationDirectory, $source)) {
            throw new InvalidFilePath('A folder cannot be moved into itself or one of its subfolders.');
        }

        $target = $this->join($destinationDirectory, basename($source));
        if ($source === $target || $this->disk()->exists($this->absolutePath($target))) {
            throw new InvalidFilePath('The destination already contains an item with this name.');
        }

        $targetPath = $this->absolutePath($target);
        $sourceFiles = $this->disk()->directoryExists($sourcePath) ? $this->disk()->allFiles($sourcePath) : null;

        if ($sourceFiles !== null) {
            $this->assertDirectoryTreeDepth($sourcePath, $target);
        }

        $this->disk()->move($sourcePath, $targetPath);
        if ($sourceFiles !== null) {
            foreach ($sourceFiles as $file) {
                $this->disk()->delete($this->thumbnailPath($file));
            }
        } else {
            $this->disk()->delete($this->thumbnailPath($sourcePath));
            $this->thumbnailer->create($this->disk(), $targetPath, $this->root(), $this->visibility());
        }

        return $target;
    }

    public function rename(mixed $user, string $source, string $name): string
    {
        $source = $this->normalisePath($source);
        $name = $this->validateName($name);

        if ($source === '') {
            throw new InvalidFilePath('The configured root cannot be renamed.');
        }

        $this->authorize($user, 'rename', $source);
        $target = $this->join(dirname($source) === '.' ? '' : dirname($source), $name);

        if ($source === $target) {
            return $source;
        }

        if ($this->disk()->exists($this->absolutePath($target))) {
            throw new InvalidFilePath('An item with this name already exists.');
        }

        $sourcePath = $this->absolutePath($source);
        if (! $this->disk()->exists($sourcePath)) {
            throw new InvalidFilePath('The source item does not exist.');
        }

        $targetPath = $this->absolutePath($target);
        $sourceFiles = $this->disk()->directoryExists($sourcePath) ? $this->disk()->allFiles($sourcePath) : null;
        $this->disk()->move($sourcePath, $targetPath);
        if ($sourceFiles !== null) {
            foreach ($sourceFiles as $file) {
                $this->disk()->delete($this->thumbnailPath($file));
            }
        } else {
            $this->disk()->delete($this->thumbnailPath($sourcePath));
            $this->thumbnailer->create($this->disk(), $targetPath, $this->root(), $this->visibility());
        }

        return $target;
    }

    public function downloadPath(mixed $user, string $path): string
    {
        $path = $this->normalisePath($path);
        $this->authorize($user, 'download', $path);

        return $this->absolutePath($path);
    }

    public function publicUrl(mixed $user, string $path): ?string
    {
        $path = $this->normalisePath($path);
        $this->authorize($user, 'view', $path);

        return $this->visibility() === 'public'
            ? $this->disk()->url($this->absolutePath($path))
            : null;
    }

    public function download(mixed $user, string $path): StreamedResponse
    {
        $path = $this->normalisePath($path);
        $this->authorize($user, 'download', $path);

        return $this->disk()->download($this->absolutePath($path));
    }

    public function preview(mixed $user, string $path): StreamedResponse
    {
        $path = $this->normalisePath($path);
        $this->authorize($user, 'preview', $path);
        $mimeType = $this->disk()->mimeType($this->absolutePath($path));

        if (! in_array($mimeType, config('filament-file-manager.preview_mimes', []), true)) {
            throw new InvalidFilePath('This file type cannot be previewed.');
        }

        return $this->disk()->response($this->absolutePath($path), basename($path), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ], 'inline');
    }

    public function thumbnail(mixed $user, string $path): StreamedResponse
    {
        $path = $this->normalisePath($path);
        $this->authorize($user, 'preview', $path);
        $sourcePath = $this->absolutePath($path);
        $mimeType = $this->disk()->mimeType($sourcePath);

        if (! str_starts_with((string) $mimeType, 'image/')) {
            throw new InvalidFilePath('Only images have thumbnails.');
        }

        $thumbnailPath = $this->thumbnailPath($sourcePath);
        if (! $this->disk()->exists($thumbnailPath)) {
            $thumbnailPath = $this->thumbnailer->create($this->disk(), $sourcePath, $this->root(), $this->visibility());
        }

        if ($thumbnailPath === null) {
            return $this->genericImageThumbnail();
        }

        return $this->disk()->response($thumbnailPath, basename($path).'.jpg', [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ], 'inline');
    }

    private function disk(): FilesystemAdapter
    {
        $diskName = (string) $this->areaConfiguration()['disk'];
        $this->assertDiskIsSafe($diskName);

        return Storage::disk($diskName);
    }

    private function assertDiskIsSafe(string $diskName): void
    {
        if ($this->visibility() === 'public') {
            return;
        }

        if ($this->visibility() !== 'private') {
            throw new UnsafeDiskConfiguration('Private storage areas must use private visibility.');
        }

        $diskConfiguration = config('filesystems.disks.'.$diskName, []);
        if ($diskName === 'public' || ! is_array($diskConfiguration) || $this->isWebServedLocalDisk($diskConfiguration)) {
            throw new UnsafeDiskConfiguration('File Manager must use a private disk. The public disk and web-served local storage roots are not supported.');
        }
    }

    /** @param array<string, mixed> $diskConfiguration */
    private function isWebServedLocalDisk(array $diskConfiguration): bool
    {
        if (($diskConfiguration['driver'] ?? null) !== 'local' || ! isset($diskConfiguration['root'])) {
            return false;
        }

        $root = (string) $diskConfiguration['root'];

        return $this->pathIsWithin($root, public_path())
            || $this->pathIsWithin($root, storage_path('app/public'));
    }

    private function pathIsWithin(string $path, string $parent): bool
    {
        $path = rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR);
        $parent = rtrim(realpath($parent) ?: $parent, DIRECTORY_SEPARATOR);

        return $path === $parent || str_starts_with($path.DIRECTORY_SEPARATOR, $parent.DIRECTORY_SEPARATOR);
    }

    private function authorize(mixed $user, string $operation, string $path): void
    {
        if (! $this->authorizer->can($user, $operation, $path)) {
            throw new AccessDeniedHttpException('You are not allowed to manage these files.');
        }
    }

    private function validateUpload(UploadedFile $file): void
    {
        if (! $file->isValid() || $file->getSize() > ((int) config('filament-file-manager.max_upload_size') * 1024)) {
            throw new InvalidFilePath('The upload is invalid or exceeds the configured size limit.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, config('filament-file-manager.allowed_extensions', []), true)
            || ! in_array($file->getMimeType(), config('filament-file-manager.allowed_mimes', []), true)) {
            throw new InvalidFilePath('This file type is not allowed.');
        }
    }

    private function uploadFileName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F<>:"\\\\|?*]+/u', '_', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");

        return $this->validateName($name);
    }

    private function uniqueFileName(string $directory, string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $baseName = pathinfo($name, PATHINFO_FILENAME);

        for ($suffix = 1; $suffix <= 1_000; $suffix++) {
            $candidate = $suffix === 1 ? $name : sprintf('%s (%d)%s', $baseName, $suffix, $extension === '' ? '' : '.'.$extension);

            if (! $this->disk()->exists($this->absolutePath($this->join($directory, $candidate)))) {
                return $candidate;
            }
        }

        throw new InvalidFilePath('A unique file name could not be generated.');
    }

    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === ''
            || $name !== basename($name)
            || in_array($name, ['.', '..'], true)
            || preg_match('/[\x00-\x1F\x7F<>:"\\\\|?*]/u', $name)) {
            throw new InvalidFilePath('The name contains unsupported characters.');
        }

        return $name;
    }

    private function normalisePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            throw new InvalidFilePath('Paths must stay within the configured root.');
        }

        if ($segments[0] === config('filament-file-manager.thumbnails.directory')) {
            throw new InvalidFilePath('Internal thumbnail paths cannot be managed.');
        }

        return implode('/', $segments);
    }

    private function assertDirectoryTreeDepth(string $sourcePath, string $targetPath): void
    {
        $this->assertDirectoryDepth($targetPath);

        foreach ($this->disk()->allDirectories($sourcePath) as $directory) {
            $suffix = ltrim(Str::after($directory, rtrim($sourcePath, '/')), '/');
            $this->assertDirectoryDepth($this->join($targetPath, $suffix));
        }
    }

    private function isSameOrDescendantDirectory(string $candidate, string $directory): bool
    {
        return $candidate === $directory || str_starts_with($candidate.'/', $directory.'/');
    }

    private function assertDirectoryDepth(string $path): void
    {
        $maximum = max(1, (int) config('filament-file-manager.max_directory_depth', 7));
        $depth = count(array_filter(explode('/', trim($path, '/'))));

        if ($depth > $maximum) {
            throw new InvalidFilePath(sprintf('Folders can be nested up to %d levels deep.', $maximum));
        }
    }

    private function absolutePath(string $relativePath): string
    {
        $root = $this->root();

        return $root === '' ? $relativePath : ($relativePath === '' ? $root : $root.'/'.$relativePath);
    }

    private function relativeFromAbsolute(string $absolutePath): string
    {
        $root = $this->root();

        return $root === '' ? $absolutePath : ltrim(Str::after($absolutePath, $root), '/');
    }

    private function join(string $left, string $right): string
    {
        return $left === '' ? $right : $left.'/'.$right;
    }

    /** @return array{enabled: bool, disk: string, root: string, visibility: string} */
    private function areaConfiguration(): array
    {
        $areas = config('filament-file-manager.storage_areas', []);
        $configuration = is_array($areas) ? ($areas[$this->storageArea] ?? null) : null;

        if (! is_array($configuration) && $this->storageArea === 'private') {
            $configuration = [
                'enabled' => true,
                'disk' => config('filament-file-manager.disk'),
                'root' => config('filament-file-manager.root'),
                'visibility' => config('filament-file-manager.visibility', 'private'),
            ];
        }

        if (! is_array($configuration) || ! ($configuration['enabled'] ?? false)) {
            throw new InvalidFilePath('The requested storage area is not enabled.');
        }

        return [
            'enabled' => true,
            'disk' => (string) ($configuration['disk'] ?? ''),
            'root' => trim((string) ($configuration['root'] ?? ''), '/'),
            'visibility' => (string) ($configuration['visibility'] ?? 'private'),
        ];
    }

    private function normaliseStorageArea(string $area): string
    {
        $area = strtolower(trim($area));
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $area)) {
            throw new InvalidFilePath('The requested storage area is invalid.');
        }

        $this->storageArea = $area;
        $this->areaConfiguration();

        return $area;
    }

    private function root(): string
    {
        return $this->areaConfiguration()['root'];
    }

    private function visibility(): string
    {
        return $this->areaConfiguration()['visibility'];
    }

    private function thumbnailPath(string $sourcePath): string
    {
        return $this->thumbnailer->path($sourcePath, $this->root());
    }

    private function genericImageThumbnail(): StreamedResponse
    {
        return new StreamedResponse(static function (): void {
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120"><rect width="160" height="120" fill="#f3f4f6"/><path d="M31 87l29-29 19 19 17-17 33 27H31z" fill="#9ca3af"/><circle cx="57" cy="42" r="10" fill="#d1d5db"/></svg>';
        }, 200, [
            'Content-Type' => 'image/svg+xml',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
