<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use UniFileManager\FilamentFileManager\Services\FileManager as FileManagerService;
use UniFileManager\FilamentFileManager\Support\DirectoryScope;
use UniFileManager\FilamentFileManager\Support\MimeTypeMatcher;
use UniFileManager\FilamentFileManager\Exceptions\InvalidFilePath;

class UniFilePicker extends Field
{
    public const DEFAULT_ALLOWED_MIME_TYPES = MimeTypeMatcher::DEFAULT_FILE_PICKER_MIME_TYPES;

    protected string $view = 'filament-file-manager::forms.components.uni-file-picker';

    protected bool|Closure $isClearable = true;

    protected bool|Closure $isMultiple = false;

    protected bool|Closure $isDuplicateSelectionAllowed = false;

    protected bool|Closure|null $isLibraryEnabled = null;

    protected string|Closure|null $managerUrl = null;

    protected int|Closure|null $maxFiles = null;

    protected string|Closure|null $directory = null;

    protected string|Closure|null $storageArea = null;

    protected bool|Closure $isImageCardView = false;

    protected string|Htmlable|Closure|null $pickerHelperText = null;

    protected string|Closure|null $uploadHeading = null;

    protected string|Closure|null $uploadDescription = null;

    /** @var array<string>|Closure|null */
    protected array|Closure|null $allowedMimeTypes = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Browser state is never a permission boundary. Validate selections both
        // during form validation and when Filament dehydrates state for saving.
        $validateSelection = static fn (UniFilePicker $component, mixed $state): mixed => $component->validateSelectionState($state);

        $this->mutateStateForValidationUsing($validateSelection);
        $this->mutateDehydratedStateUsing($validateSelection);
    }

    /**
     * @deprecated Use the `file_picker_manager_url` config value, or `library()` to control library access.
     */
    public function managerUrl(string $url): static
    {
        $this->managerUrl = $url;

        // Keep legacy integrations working when the global library option is
        // disabled, while allowing an explicit library(false) call to win.
        if ($this->isLibraryEnabled === null) {
            $this->isLibraryEnabled = true;
        }

        return $this;
    }

    /** Enable or disable the embedded File Picker library for this field. */
    public function library(bool|Closure $condition = true): static
    {
        $this->isLibraryEnabled = $condition;

        return $this;
    }

    public function clearable(bool|Closure $condition = true): static
    {
        $this->isClearable = $condition;

        return $this;
    }

    /**
     * Set upload guidance that is rendered directly below the placement box.
     *
     * This replaces Filament's default below-content helper so it can disappear
     * with the placement box after a selection is made.
     */
    public function helperText(string|Htmlable|Closure|null $text): static
    {
        $this->pickerHelperText = $text;

        return $this;
    }

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        // A thumbnail list needs the full width of the form row. Applications can
        // still override this afterwards with ->columnSpan(...).
        if ($condition === true) {
            $this->columnSpanFull();
        }

        return $this;
    }

    /** Allow a multiple picker to select the same library file more than once. */
    public function allowDuplicateSelection(bool|Closure $condition = true): static
    {
        $this->isDuplicateSelectionAllowed = $condition;

        return $this;
    }

    public function maxFiles(int|Closure|null $maxFiles): static
    {
        $this->maxFiles = $maxFiles;

        return $this;
    }

    /** Restrict this picker to a directory below the configured File Manager root. */
    public function directory(string|Closure|null $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /** Use the configured public-media storage area. */
    public function publicMedia(bool|Closure $condition = true): static
    {
        if ((bool) $this->evaluate($condition)) {
            $this->storageArea = 'public';
        }

        return $this;
    }

    /** Use the configured private storage area. */
    public function privateMedia(bool|Closure $condition = true): static
    {
        if ((bool) $this->evaluate($condition)) {
            $this->storageArea = 'private';
        }

        return $this;
    }

    /** Set the primary text shown in the upload drop zone. */
    public function uploadHeading(string|Closure|null $heading): static
    {
        $this->uploadHeading = $heading;

        return $this;
    }

    /** Set the supporting text shown in the upload drop zone. */
    public function uploadDescription(string|Closure|null $description): static
    {
        $this->uploadDescription = $description;

        return $this;
    }

    /**
     * Restrict library selection and uploads for this picker instance.
     *
     * @param array<string>|Closure|null $mimeTypes
     */
    public function allowedMimeTypes(array|Closure|null $mimeTypes): static
    {
        $this->allowedMimeTypes = $mimeTypes;

        return $this;
    }

    /** Display selected files as image cards instead of the default compact list. */
    public function imageCardView(bool|Closure $condition = true): static
    {
        $this->isImageCardView = $condition;

        return $this;
    }

    public function isClearable(): bool
    {
        return (bool) $this->evaluate($this->isClearable);
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function allowsDuplicateSelection(): bool
    {
        return $this->isMultiple() && (bool) $this->evaluate($this->isDuplicateSelectionAllowed);
    }

    public function hasLibrary(): bool
    {
        return $this->isLibraryEnabled === null
            ? (bool) config('filament-file-manager.file_picker_library', true)
            : (bool) $this->evaluate($this->isLibraryEnabled);
    }

    public function isImageCardView(): bool
    {
        return (bool) $this->evaluate($this->isImageCardView);
    }

    public function getPickerHelperText(): string|Htmlable|null
    {
        return $this->evaluate($this->pickerHelperText);
    }

    public function getUploadHeading(): ?string
    {
        return $this->evaluate($this->uploadHeading);
    }

    public function getUploadDescription(): ?string
    {
        return $this->evaluate($this->uploadDescription);
    }

    public function getManagerUrl(): ?string
    {
        $url = $this->evaluate($this->managerUrl) ?? config('filament-file-manager.file_picker_manager_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** @return list<string> */
    public function getAllowedMimeTypes(): array
    {
        $mimeTypes = $this->evaluate($this->allowedMimeTypes) ?? self::DEFAULT_ALLOWED_MIME_TYPES;

        return array_values(array_filter($mimeTypes, 'is_string'));
    }

    public function getMaxFiles(): int
    {
        $configuredMaximum = max(1, (int) config('filament-file-manager.max_upload_files'));
        $maximum = $this->evaluate($this->maxFiles) ?? $configuredMaximum;

        return $this->isMultiple() ? min($configuredMaximum, max(1, (int) $maximum)) : 1;
    }

    public function getDirectory(): string
    {
        return DirectoryScope::normalise($this->evaluate($this->directory));
    }

    public function getStorageArea(): string
    {
        $configuredArea = $this->evaluate($this->storageArea);
        if (is_string($configuredArea) && $configuredArea !== '') {
            return $configuredArea;
        }

        $areas = array_filter(
            config('filament-file-manager.storage_areas', []),
            static fn (mixed $area): bool => is_array($area) && ($area['enabled'] ?? false),
        );

        if (count($areas) === 1) {
            return (string) array_key_first($areas);
        }

        $defaultArea = config('filament-file-manager.file_picker_default_area');
        if (is_string($defaultArea) && isset($areas[$defaultArea])) {
            return $defaultArea;
        }

        if ($areas === []) {
            throw new InvalidFilePath('UniFilePicker cannot be used because no storage areas are enabled. Enable storage_areas.private or storage_areas.public in filament-file-manager.php.');
        }

        throw new InvalidFilePath(sprintf(
            'UniFilePicker cannot resolve its storage area. Set file_picker_default_area, or add ->privateMedia() or ->publicMedia() to this field. Enabled areas: %s.',
            implode(', ', array_keys($areas)),
        ));
    }

    /**
     * Revalidate browser-provided state before a form validates or persists it.
     *
     * @return string|list<string>|null
     */
    public function validateSelectionState(mixed $state): string|array|null
    {
        if ($this->isMultiple()) {
            if ($state === null || $state === '') {
                return [];
            }

            if (! is_array($state)) {
                $this->throwInvalidSelection();
            }

            if (count($state) > $this->getMaxFiles()) {
                $this->throwInvalidSelection();
            }

            $paths = [];
            foreach ($state as $path) {
                if (! is_string($path)) {
                    $this->throwInvalidSelection();
                }

                $paths[] = $this->validateSelectedPath($path);
            }

            if (! $this->allowsDuplicateSelection() && count($paths) !== count(array_unique($paths))) {
                $this->throwInvalidSelection();
            }

            return $paths;
        }

        if ($state === null || $state === '') {
            return null;
        }

        if (! is_string($state)) {
            $this->throwInvalidSelection();
        }

        return $this->validateSelectedPath($state);
    }

    public function isImagePath(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function thumbnailUrl(string $path): string
    {
        return route('filament-file-manager.preview', ['path' => $path, 'area' => $this->getStorageArea(), 'thumbnail' => 1]);
    }

    public function previewUrl(string $path): string
    {
        return route('filament-file-manager.preview', ['path' => $path, 'area' => $this->getStorageArea()]);
    }

    public function documentKind(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'xls', 'xlsx', 'csv' => 'sheet',
            'txt' => 'text',
            default => 'file',
        };
    }

    private function validateSelectedPath(string $path): string
    {
        try {
            $path = DirectoryScope::normalise($path);
            if (! DirectoryScope::contains($this->getDirectory(), $path)) {
                $this->throwInvalidSelection();
            }

            $file = app(FileManagerService::class)
                ->forArea($this->getStorageArea())
                ->selectableFile(auth()->user(), $path);

            if (! MimeTypeMatcher::allows($file['mime_type'], $this->getAllowedMimeTypes())) {
                $this->throwInvalidSelection();
            }

            return $file['path'];
        } catch (InvalidFilePath|AccessDeniedHttpException) {
            $this->throwInvalidSelection();
        }
    }

    private function throwInvalidSelection(): never
    {
        throw ValidationException::withMessages([
            $this->getStatePath() => 'Select a permitted file from this library.',
        ]);
    }
}
