<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UniFileManager\Core\Exceptions\InvalidFilePath;
use UniFileManager\Core\Services\FileManager as FileManagerService;
use UniFileManager\Core\Support\DirectoryScope;
use UniFileManager\Core\Support\MimeTypeMatcher;
use UniFileManager\FilamentFileManager\Support\PreviewUrlParameters;
use Throwable;

final class FilePickerExplorer extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use WithFileUploads;
    use WithPagination;

    #[Locked]
    public string $pickerId = '';

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

    /** @var list<string> */
    public array $selectedPaths = [];

    public ?string $selectionMessage = null;

    private const DISPLAY_MODES = ['separate', 'all'];

    private const SORT_FIELDS = ['name', 'modified_at', 'type'];

    public string $path = '';

    public string $search = '';

    public string $displayMode = 'all';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    /** @var list<UploadedFile> */
    public array $uploads = [];

    public ?string $uploadMessage = null;

    /** @var list<array{path: string, name: string, is_image: bool}> */
    #[Locked]
    public array $uploadedPreviewFiles = [];

    /** @var list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public array $items = [];

    /** @param list<string> $allowedMimeTypes */
    public function mount(string $pickerId = '', bool $multiple = false, int $maxFiles = 1, string $directory = '', string $storageArea = 'private', array $allowedMimeTypes = []): void
    {
        $this->pickerId = $pickerId;
        $this->multiple = $multiple;
        $this->maxFiles = max(1, $maxFiles);
        $this->directory = DirectoryScope::normalise($directory);
        $this->storageArea = $storageArea;
        $this->allowedMimeTypes = array_values(array_filter($allowedMimeTypes, 'is_string')) ?: MimeTypeMatcher::DEFAULT_FILE_PICKER_MIME_TYPES;
        $this->path = $this->directory;
        $this->restoreDisplayPreference();
        $this->refreshItems();
    }

    public function open(string $path): void
    {
        $path = DirectoryScope::normalise($path);
        if (! DirectoryScope::contains($this->directory, $path)) {
            return;
        }

        foreach ($this->items as $item) {
            if ($item['path'] === $path && $item['type'] === 'directory') {
                $this->path = $path;
                $this->search = '';
                $this->resetPickerPagination();
                $this->refreshItems();

                return;
            }
        }
    }

    public function up(): void
    {
        $segments = explode('/', $this->path);
        array_pop($segments);
        $parentPath = implode('/', array_filter($segments));
        $this->path = DirectoryScope::contains($this->directory, $parentPath) ? $parentPath : $this->directory;
        $this->search = '';
        $this->resetPickerPagination();
        $this->refreshItems();
    }

    public function updatedSearch(): void
    {
        $this->resetPickerPagination();
    }

    public function setDisplayMode(string $displayMode): void
    {
        if (! $this->canChooseItemLayout() || ! in_array($displayMode, self::DISPLAY_MODES, true)) {
            return;
        }

        $this->displayMode = $displayMode;
        $this->resetPickerPagination();
        session()->put($this->displayPreferenceKey(), $displayMode);
    }

    public function canChooseItemLayout(): bool
    {
        return (bool) config('filament-file-manager.show_item_layout', false);
    }

    public function updatedSortBy(string $sortBy): void
    {
        $this->sortBy = in_array($sortBy, self::SORT_FIELDS, true) ? $sortBy : 'name';
        $this->resetPickerPagination();
    }

    public function toggleSortDirection(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPickerPagination();
    }

    public function goToManagerPage(string $pageName, mixed $page): void
    {
        if (! in_array($pageName, ['pickerFoldersPage', 'pickerFilesPage', 'pickerItemsPage'], true)) {
            return;
        }

        $this->setPage(max(1, (int) $page), $pageName);
    }

    public function updatedUploads(): void
    {
        $maximum = $this->maximumUploadFiles();
        $this->uploadMessage = null;
        $this->resetValidation('uploads');

        if (count($this->uploads) > $maximum) {
            $this->reset('uploads');
            $this->addError('uploads', __('filament-file-manager::file-manager.too_many_files', ['count' => $maximum]));

            return;
        }

        if ($this->uploads !== []) {
            $this->storeUploads();
        }
    }

    public function maximumUploadFiles(): int
    {
        $configuredMaximum = max(1, (int) config('filament-file-manager.max_upload_files'));
        $phpMaximum = (int) ini_get('max_file_uploads');
        $remainingSlots = max(1, $this->availableSelectionSlots());

        return $phpMaximum > 0
            ? min($configuredMaximum, $phpMaximum, $remainingSlots)
            : min($configuredMaximum, $remainingSlots);
    }

    public function availableSelectionSlots(): int
    {
        return $this->multiple ? max(0, $this->maxFiles - count($this->selectedPaths)) : 1;
    }

    public function canUpload(): bool
    {
        return $this->availableSelectionSlots() > 0;
    }

    public function acceptedFileTypes(): string
    {
        return implode(',', $this->allowedMimeTypes);
    }

    public function rejectTooManyFiles(int $count): void
    {
        $this->reset('uploads');
        $this->addError('uploads', __('filament-file-manager::file-manager.selected_too_many_files', ['selected' => $count, 'count' => $this->maximumUploadFiles()]));
    }

    public function deleteUploadedPreviewAction(): Action
    {
        return Action::make('deleteUploadedPreview')
            ->requiresConfirmation()
            ->modalHeading(__('filament-file-manager::file-manager.delete_uploaded_file_heading'))
            ->modalDescription(__('filament-file-manager::file-manager.delete_uploaded_file_description'))
            ->modalSubmitActionLabel(__('filament-file-manager::file-manager.delete_file'))
            ->color('danger')
            ->action(function (array $arguments): void {
                $path = $arguments['path'] ?? null;

                if (is_string($path)) {
                    $this->deleteUploadedPreview($path);
                }
            });
    }

    public function deleteAllUploadedPreviewsAction(): Action
    {
        return Action::make('deleteAllUploadedPreviews')
            ->requiresConfirmation()
            ->modalHeading(__('filament-file-manager::file-manager.delete_all_uploads_heading'))
            ->modalDescription(__('filament-file-manager::file-manager.delete_all_uploads_description'))
            ->modalSubmitActionLabel(__('filament-file-manager::file-manager.delete_all'))
            ->color('danger')
            ->action(function (): void {
                $this->deleteAllUploadedPreviews();
            });
    }

    public function select(string $path): void
    {
        foreach ($this->items as $item) {
            if ($item['path'] === $path && $item['type'] === 'file' && $this->isAllowedFile($item)) {
                if ($this->multiple) {
                    $this->toggleSelection($path);

                    return;
                }

                $this->dispatch('unifile-manager:selected', pickerId: $this->pickerId, path: $path);

                return;
            }
        }
    }

    public function toggleSelection(string $path): void
    {
        $item = collect($this->items)->first(static fn (array $item): bool => $item['path'] === $path);

        if (! is_array($item) || $item['type'] !== 'file' || ! $this->isAllowedFile($item)) {
            return;
        }

        $this->selectionMessage = null;

        if (in_array($path, $this->selectedPaths, true)) {
            $this->selectedPaths = array_values(array_filter(
                $this->selectedPaths,
                static fn (string $selectedPath): bool => $selectedPath !== $path,
            ));

            return;
        }

        if ($this->selectionLimitReached()) {
            $this->selectionMessage = trans_choice('filament-file-manager::file-manager.select_up_to_files', $this->maxFiles, ['count' => $this->maxFiles]);

            return;
        }

        $this->selectedPaths[] = $path;
    }

    public function confirmSelection(): void
    {
        if (! $this->multiple || $this->selectedPaths === []) {
            return;
        }

        $this->dispatch('unifile-manager:selected', pickerId: $this->pickerId, paths: $this->selectedPaths);
        $this->selectedPaths = [];
    }

    public function isSelected(string $path): bool
    {
        return in_array($path, $this->selectedPaths, true);
    }

    public function selectionLimitReached(): bool
    {
        return count($this->selectedPaths) >= $this->maxFiles;
    }

    public function thumbnailUrl(string $path): string
    {
        return route('filament-file-manager.preview', PreviewUrlParameters::make($path, $this->storageArea, true));
    }

    /** @param array{mime_type?: string} $file */
    public function isImage(array $file): bool
    {
        return str_starts_with((string) ($file['mime_type'] ?? ''), 'image/');
    }

    /** @param array{name: string} $file */
    public function documentKind(array $file): string
    {
        return match (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'xls', 'xlsx', 'csv' => 'sheet',
            'txt' => 'text',
            default => 'file',
        };
    }

    /** @return list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public function getFilteredItemsProperty(): array
    {
        $search = mb_strtolower(trim($this->search));
        $items = array_values(array_filter(
            $this->items,
            fn (array $item): bool => $item['type'] === 'directory' || $this->isAllowedFile($item),
        ));

        if ($search === '') {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => str_contains(mb_strtolower($item['name']), $search),
        ));
    }

    /** @return list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public function getSortedItemsProperty(): array
    {
        $items = $this->filteredItems;
        $sortBy = in_array($this->sortBy, self::SORT_FIELDS, true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? -1 : 1;

        usort($items, static function (array $left, array $right) use ($sortBy, $direction): int {
            $leftValue = $left[$sortBy] ?? null;
            $rightValue = $right[$sortBy] ?? null;

            if ($sortBy === 'name') {
                return strnatcasecmp((string) $leftValue, (string) $rightValue) * $direction;
            }

            if ($leftValue === null) {
                return $rightValue === null ? strnatcasecmp($left['name'], $right['name']) : 1;
            }

            if ($rightValue === null) {
                return -1;
            }

            return (($leftValue <=> $rightValue) ?: strnatcasecmp($left['name'], $right['name'])) * $direction;
        });

        return $items;
    }

    /** @return list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public function getFoldersProperty(): array
    {
        return array_values(array_filter($this->sortedItems, static fn (array $item): bool => $item['type'] === 'directory'));
    }

    /** @return list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public function getFilesProperty(): array
    {
        return array_values(array_filter($this->sortedItems, static fn (array $item): bool => $item['type'] === 'file'));
    }

    public function getPaginatedFoldersProperty(): LengthAwarePaginator
    {
        return $this->paginate($this->folders, 'pickerFoldersPage', (int) config('filament-file-manager.folders_per_page'));
    }

    public function getPaginatedFilesProperty(): LengthAwarePaginator
    {
        return $this->paginate($this->files, 'pickerFilesPage', (int) config('filament-file-manager.files_per_page'));
    }

    public function getPaginatedItemsProperty(): LengthAwarePaginator
    {
        return $this->paginate($this->sortedItems, 'pickerItemsPage', (int) config('filament-file-manager.items_per_page'));
    }

    public function render(): mixed
    {
        return view('filament-file-manager::livewire.file-picker-explorer');
    }

    private function refreshItems(): void
    {
        $this->path = $this->currentPath();
        $this->items = app(FileManagerService::class)->forArea($this->storageArea)->list(auth()->user(), $this->path);
    }

    private function currentPath(): string
    {
        $path = DirectoryScope::normalise($this->path);

        if (! DirectoryScope::contains($this->directory, $path)) {
            throw new InvalidFilePath('This File Picker is restricted to its configured directory.');
        }

        return $path;
    }

    private function storeUploads(): void
    {
        try {
            if (! $this->canUpload()) {
                throw ValidationException::withMessages([
                    'uploads' => __('filament-file-manager::file-manager.use_or_remove_selected'),
                ]);
            }

            $this->validate([
                'uploads' => ['required', 'array', 'min:1', 'max:'.$this->maximumUploadFiles()],
                'uploads.*' => ['file'],
            ]);

            /** @var list<UploadedFile> $uploads */
            $uploads = $this->uploads;

            if (! $this->uploadsUseAllowedMimeTypes($uploads)) {
                throw ValidationException::withMessages([
                    'uploads' => __('filament-file-manager::file-manager.unsupported_field_files'),
                ]);
            }

            $fileManager = app(FileManagerService::class)->forArea($this->storageArea);
            $fileManager->validateUploads($uploads);

            $uploadedPaths = [];
            $uploadedPreviewFiles = [];
            foreach ($uploads as $upload) {
                $path = $fileManager->upload(auth()->user(), $upload, $this->currentPath());
                $uploadedPaths[] = $path;
                $uploadedPreviewFiles[] = [
                    'path' => $path,
                    'name' => basename($path),
                    'is_image' => str_starts_with((string) $upload->getMimeType(), 'image/'),
                ];
            }

            $this->reset('uploads');
            $this->uploadedPreviewFiles = array_values(array_merge($uploadedPreviewFiles, $this->uploadedPreviewFiles));
            $this->selectUploadedPaths($uploadedPaths);
            $this->uploadMessage = count($uploadedPaths) === 1
                ? __('filament-file-manager::file-manager.file_uploaded_body', ['name' => basename($uploadedPaths[0])])
                : __('filament-file-manager::file-manager.files_uploaded_body', ['count' => count($uploadedPaths)]);
            $this->refreshItems();
            $this->resetPickerPagination();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->reset('uploads');
            $this->addError('uploads', $exception instanceof InvalidFilePath ? $exception->getMessage() : __('filament-file-manager::file-manager.upload_failed_body'));
        }
    }

    private function deleteUploadedPreview(string $path): void
    {
        if (! in_array($path, array_column($this->uploadedPreviewFiles, 'path'), true)) {
            return;
        }

        try {
            app(FileManagerService::class)->forArea($this->storageArea)->delete(auth()->user(), $path);
            $this->uploadedPreviewFiles = array_values(array_filter(
                $this->uploadedPreviewFiles,
                static fn (array $file): bool => $file['path'] !== $path,
            ));
            $this->selectedPaths = array_values(array_filter(
                $this->selectedPaths,
                static fn (string $selectedPath): bool => $selectedPath !== $path,
            ));
            $this->refreshItems();

            Notification::make()
                ->success()
                ->title(__('filament-file-manager::file-manager.uploaded_file_deleted'))
                ->body(__('filament-file-manager::file-manager.uploaded_file_deleted_body'))
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('filament-file-manager::file-manager.delete_failed'))
                ->body(__('filament-file-manager::file-manager.delete_failed_body'))
                ->send();
        }
    }

    private function deleteAllUploadedPreviews(): void
    {
        $deletedPaths = [];
        $failed = 0;
        $fileManager = app(FileManagerService::class)->forArea($this->storageArea);

        foreach ($this->uploadedPreviewFiles as $file) {
            try {
                $fileManager->delete(auth()->user(), $file['path']);
                $deletedPaths[] = $file['path'];
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        if ($deletedPaths !== []) {
            $this->uploadedPreviewFiles = array_values(array_filter(
                $this->uploadedPreviewFiles,
                static fn (array $file): bool => ! in_array($file['path'], $deletedPaths, true),
            ));
            $this->selectedPaths = array_values(array_filter(
                $this->selectedPaths,
                static fn (string $path): bool => ! in_array($path, $deletedPaths, true),
            ));
            $this->refreshItems();
        }

        Notification::make()
            ->{$failed === 0 ? 'success' : 'warning'}()
            ->title($failed === 0 ? __('filament-file-manager::file-manager.uploaded_files_deleted') : __('filament-file-manager::file-manager.some_uploads_not_deleted'))
            ->body($failed === 0
                ? __('filament-file-manager::file-manager.uploaded_files_deleted_body')
                : __('filament-file-manager::file-manager.some_uploads_not_deleted_body'))
            ->send();
    }

    /** @param list<string> $paths */
    private function selectUploadedPaths(array $paths): void
    {
        if (! $this->multiple) {
            $this->dispatch('unifile-manager:selected', pickerId: $this->pickerId, path: $paths[0] ?? null);

            return;
        }

        foreach ($paths as $path) {
            if (! in_array($path, $this->selectedPaths, true) && ! $this->selectionLimitReached()) {
                $this->selectedPaths[] = $path;
            }
        }
    }

    /** @param array{type: string, mime_type?: string} $item */
    private function isAllowedFile(array $item): bool
    {
        return MimeTypeMatcher::allows((string) ($item['mime_type'] ?? ''), $this->allowedMimeTypes);
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

    /** @param list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> $items */
    private function paginate(array $items, string $pageName, int $perPage): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $this->getPage($pageName)), $lastPage);

        return new LengthAwarePaginator(
            array_values(array_slice($items, ($currentPage - 1) * $perPage, $perPage)),
            $total,
            $perPage,
            $currentPage,
            ['pageName' => $pageName, 'path' => request()->url()],
        );
    }

    private function resetPickerPagination(): void
    {
        $this->resetPage('pickerFoldersPage');
        $this->resetPage('pickerFilesPage');
        $this->resetPage('pickerItemsPage');
    }

    private function restoreDisplayPreference(): void
    {
        if (! $this->canChooseItemLayout()) {
            $this->displayMode = 'all';

            return;
        }

        $displayMode = session()->get($this->displayPreferenceKey());

        if (in_array($displayMode, self::DISPLAY_MODES, true)) {
            $this->displayMode = $displayMode;
        }
    }

    private function displayPreferenceKey(): string
    {
        return 'filament-file-manager.file-picker.display-mode.'.(string) (auth()->id() ?? 'guest');
    }
}
