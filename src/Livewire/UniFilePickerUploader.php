<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Livewire;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use UniFileManager\FilamentFileManager\Exceptions\InvalidFilePath;
use UniFileManager\FilamentFileManager\Services\FileManager as FileManagerService;
use UniFileManager\FilamentFileManager\Support\DirectoryScope;
use UniFileManager\FilamentFileManager\Support\MimeTypeMatcher;
use Throwable;

final class UniFilePickerUploader extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $pickerId;

    #[Locked]
    public bool $multiple = false;

    #[Locked]
    public int $maxFiles = 1;

    #[Locked]
    public string $directory = '';

    #[Locked]
    public string $storageArea = 'private';

    /** @var list<string> */
    #[Locked]
    public array $allowedMimeTypes = [];

    #[Locked]
    public bool $hasLibrary = false;

    public ?string $heading = null;

    public ?string $description = null;

    /** @var list<UploadedFile> */
    public array $uploads = [];

    /** @param list<string> $allowedMimeTypes */
    public function mount(string $pickerId, bool $multiple, int $maxFiles, string $directory = '', string $storageArea = 'private', array $allowedMimeTypes = [], bool $hasLibrary = false, ?string $heading = null, ?string $description = null): void
    {
        $this->pickerId = $pickerId;
        $this->multiple = $multiple;
        $this->maxFiles = max(1, $maxFiles);
        $this->directory = DirectoryScope::normalise($directory);
        $this->storageArea = $storageArea;
        $this->allowedMimeTypes = array_values(array_filter($allowedMimeTypes, 'is_string')) ?: MimeTypeMatcher::DEFAULT_FILE_PICKER_MIME_TYPES;
        $this->hasLibrary = $hasLibrary;
        $this->heading = $heading;
        $this->description = $description;
    }

    public function updatedUploads(): void
    {
        if (count($this->uploads) > $this->maximumUploadFiles()) {
            $this->reset('uploads');
            $this->addError('uploads', sprintf('You may upload a maximum of %d files at once.', $this->maximumUploadFiles()));

            return;
        }

        if ($this->uploads !== []) {
            $this->storeUploads();
        }
    }

    public function rejectTooManyFiles(int $count): void
    {
        $this->reset('uploads');
        $this->addError('uploads', sprintf('You selected %d files. The maximum is %d files.', $count, $this->maximumUploadFiles()));
    }

    public function openLibrary(): void
    {
        $this->dispatch('unifile-manager:open-library', pickerId: $this->pickerId);
    }

    public function maximumUploadFiles(): int
    {
        $phpMaximum = (int) ini_get('max_file_uploads');
        $maximum = $this->multiple ? $this->maxFiles : 1;

        return $phpMaximum > 0 ? min($maximum, $phpMaximum) : $maximum;
    }

    public function acceptedFileTypes(): string
    {
        return implode(',', $this->allowedMimeTypes);
    }

    public function render(): mixed
    {
        return view('filament-file-manager::livewire.uni-file-picker-uploader');
    }

    private function storeUploads(): void
    {
        try {
            $this->validate([
                'uploads' => ['required', 'array', 'min:1', 'max:'.$this->maximumUploadFiles()],
                'uploads.*' => ['file'],
            ]);

            /** @var list<UploadedFile> $uploads */
            $uploads = $this->uploads;

            if (! $this->uploadsUseAllowedMimeTypes($uploads)) {
                throw ValidationException::withMessages([
                    'uploads' => 'One or more selected files are not supported by this field.',
                ]);
            }

            $fileManager = app(FileManagerService::class)->forArea($this->storageArea);
            $fileManager->validateUploads($uploads);

            $paths = [];
            foreach ($uploads as $upload) {
                $paths[] = $fileManager->upload(auth()->user(), $upload, $this->directory);
            }

            $this->reset('uploads');
            $this->dispatch('unifile-manager:uploaded', pickerId: $this->pickerId, paths: $paths);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->reset('uploads');
            $this->addError('uploads', $exception instanceof InvalidFilePath ? $exception->getMessage() : 'The files could not be uploaded. Please try again.');
        }
    }

    /** @param list<UploadedFile> $uploads */
    private function uploadsUseAllowedMimeTypes(array $uploads): bool
    {
        foreach ($uploads as $upload) {
            if (! MimeTypeMatcher::allows((string) $upload->getMimeType(), $this->allowedMimeTypes)) {
                return false;
            }
        }

        return true;
    }
}
