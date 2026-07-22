<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UniFileManager\FilamentFileManager\Services\FileManager as FileManagerService;
use UniFileManager\FilamentFileManager\Exceptions\FolderNotEmpty;
use UniFileManager\FilamentFileManager\Exceptions\InvalidFilePath;
use Throwable;

final class FileManager extends Page
{
    use WithFileUploads;
    use WithPagination;

    private const DISPLAY_MODES = ['separate', 'all'];

    private const SORT_FIELDS = ['name', 'modified_at', 'type'];

    protected string $view = 'filament-file-manager::livewire.file-manager';

    protected static bool $shouldRegisterNavigation = true;

    protected static string | BackedEnum | null $navigationIcon = 'ufm-file-manager';

    public string $path = '';

    public string $storageArea = 'private';

    public string $search = '';

    public string $displayMode = 'all';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public ?string $renamingPath = null;

    public string $renamingName = '';

    public bool $isCreatingDirectory = false;

    /** @var list<string> */
    public array $selectedPaths = [];

    /** @var list<TemporaryUploadedFile|UploadedFile> */
    public array $uploads = [];

    /** @var list<array{path: string, name: string, is_image: bool}> */
    public array $uploadedPreviewFiles = [];

    /** @var list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public array $items = [];

    public function mount(): void
    {
        $this->storageArea = $this->defaultStorageArea();
        $savedArea = session()->get('unifile-manager:storage-area');
        if (is_string($savedArea) && isset($this->availableStorageAreas()[$savedArea])) {
            $this->storageArea = $savedArea;
        }
        $this->restorePreferences();
        $this->refreshItems();
    }

    public function setStorageArea(string $area): void
    {
        if (! isset($this->availableStorageAreas()[$area])) {
            return;
        }

        $this->storageArea = $area;
        session()->put('unifile-manager:storage-area', $area);
        $this->path = '';
        $this->search = '';
        $this->clearSelection();
        $this->cancelRename();
        $this->uploadedPreviewFiles = [];
        $this->resetPagination();
        $this->refreshItems();
    }

    /** @return array<string, string> */
    public function availableStorageAreas(): array
    {
        $areas = config('filament-file-manager.storage_areas', []);
        $available = [];
        foreach (is_array($areas) ? $areas : [] as $key => $area) {
            if (is_string($key) && is_array($area) && ($area['enabled'] ?? false)) {
                $available[$key] = $key === 'public' ? 'Public media' : 'Private files';
            }
        }

        return $available === [] ? ['private' => 'Private files'] : $available;
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function open(string $path): void
    {
        $this->path = $path;
        $this->clearSelection();
        $this->resetPagination();
        $this->refreshItems();
    }

    public function up(): void
    {
        $segments = explode('/', $this->path);
        array_pop($segments);
        $this->path = implode('/', array_filter($segments));
        $this->clearSelection();
        $this->resetPagination();
        $this->refreshItems();
    }

    public function createNewDirectory(FileManagerService $fileManager): void
    {
        $fileManager = $fileManager->forArea($this->storageArea);
        try {
            $this->renamingPath = $fileManager->createNewDirectory(auth()->user(), $this->path);
            $this->renamingName = basename($this->renamingPath);
            $this->isCreatingDirectory = true;
            $this->refreshItems();
            $this->showNewFolderPage($this->renamingPath);
            $this->dispatch('file-manager:focus-rename');
        } catch (InvalidFilePath $exception) {
            Notification::make()
                ->danger()
                ->title('Folder cannot be created')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function saveRename(FileManagerService $fileManager): void
    {
        $fileManager = $fileManager->forArea($this->storageArea);
        $this->validate(['renamingName' => ['required', 'string', 'max:100']]);

        if ($this->renamingPath === null) {
            return;
        }

        $renamedPath = $this->renamingPath;
        $originalName = basename($renamedPath);
        $itemType = $this->isFilePath($renamedPath) ? 'File' : 'Folder';
        $isCreatingDirectory = $this->isCreatingDirectory;
        $renamedName = $this->renamingName;

        if ($this->isFilePath($renamedPath)) {
            $extension = $this->fileExtension($renamedPath);
            $renamedName .= $extension === '' ? '' : '.'.$extension;
        }

        try {
            $updatedPath = $fileManager->rename(auth()->user(), $renamedPath, $renamedName);
            $updatedName = basename($updatedPath);
            $this->selectedPaths = array_values(array_filter(
                $this->selectedPaths,
                static fn (string $selectedPath): bool => $selectedPath !== $renamedPath,
            ));
            $this->cancelRename();
            $this->refreshItems();

            Notification::make()
                ->success()
                ->title($isCreatingDirectory
                    ? 'Folder created'
                    : ($originalName === $updatedName ? $itemType.' name saved' : $itemType.' renamed'))
                ->body($isCreatingDirectory
                    ? sprintf('"%s" was created.', $updatedName)
                    : ($originalName === $updatedName
                        ? sprintf('The %s name is unchanged.', strtolower($itemType))
                        : sprintf('"%s" is now "%s".', $originalName, $updatedName)))
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('renamingName', $exception instanceof InvalidFilePath ? $exception->getMessage() : 'The item could not be renamed. Please try again.');

            Notification::make()
                ->danger()
                ->title('Rename failed')
                ->body('The item could not be renamed. Please try again.')
                ->send();
        }
    }

    public function beginRename(string $path): void
    {
        foreach ($this->items as $item) {
            if ($item['path'] !== $path) {
                continue;
            }

            $this->renamingPath = $path;
            $this->isCreatingDirectory = false;
            $this->renamingName = $item['type'] === 'file'
                ? $this->fileNameWithoutExtension($item['name'])
                : $item['name'];
            $this->resetValidation('renamingName');
            $this->dispatch('file-manager:focus-rename');

            return;
        }
    }

    public function cancelRename(): void
    {
        $this->renamingPath = null;
        $this->renamingName = '';
        $this->isCreatingDirectory = false;
        $this->resetValidation('renamingName');
    }

    public function renamingExtension(): string
    {
        return $this->renamingPath !== null && $this->isFilePath($this->renamingPath)
            ? $this->fileExtension($this->renamingPath)
            : '';
    }

    public function setDisplayMode(string $displayMode): void
    {
        if ($this->canChooseItemLayout() && in_array($displayMode, self::DISPLAY_MODES, true)) {
            $this->displayMode = $displayMode;
            $this->resetPagination();
            $this->persistPreferences();
        }
    }

    public function canChooseItemLayout(): bool
    {
        return (bool) config('filament-file-manager.show_item_layout', false);
    }

    public function updatedSortBy(string $sortBy): void
    {
        if (! in_array($sortBy, self::SORT_FIELDS, true)) {
            $this->sortBy = 'name';
        }

        $this->resetPagination();
        $this->persistPreferences();
    }

    public function toggleSortDirection(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPagination();
        $this->persistPreferences();
    }

    public function updatedSearch(): void
    {
        $this->resetPagination();
    }

    public function goToManagerPage(string $pageName, mixed $page): void
    {
        $page = (int) $page;

        if (! in_array($pageName, ['foldersPage', 'filesPage', 'itemsPage'], true)) {
            return;
        }

        $this->setPage(max(1, $page), $pageName);
    }

    public function updatedUploads(): void
    {
        $maximum = $this->maximumUploadFiles();
        $count = count($this->uploads);

        if ($count > $maximum) {
            $this->reset('uploads');
            $this->addError('uploads', sprintf('You may upload a maximum of %d files at once.', $maximum));
            Notification::make()
                ->danger()
                ->title('Too many files selected')
                ->body(sprintf('Select a maximum of %d files at once.', $maximum))
                ->send();

            return;
        }

        if ($count > 0) {
            $this->storeUploads($this->manager());
        }
    }

    public function maximumUploadFiles(): int
    {
        $configuredMaximum = max(1, (int) config('filament-file-manager.max_upload_files'));
        $phpMaximum = (int) ini_get('max_file_uploads');

        return $phpMaximum > 0 ? min($configuredMaximum, $phpMaximum) : $configuredMaximum;
    }

    public function rejectTooManyFiles(int $count): void
    {
        $maximum = $this->maximumUploadFiles();
        $this->reset('uploads');
        $this->addError('uploads', sprintf('You selected %d files. The maximum is %d files.', $count, $maximum));

        Notification::make()
            ->danger()
            ->title('Too many files selected')
            ->body(sprintf('Select a maximum of %d files at once.', $maximum))
            ->send();
    }

    public function closeUploadModal(): void
    {
        $this->reset('uploads');
        $this->uploadedPreviewFiles = [];
        $this->resetValidation('uploads');
    }

    public function storeUploads(FileManagerService $fileManager): void
    {
        $fileManager = $fileManager->forArea($this->storageArea);
        try {
            $maximum = $this->maximumUploadFiles();
            $this->validate([
                'uploads' => ['required', 'array', 'min:1', 'max:'.$maximum],
                'uploads.*' => ['file'],
            ]);

            /** @var list<UploadedFile> $uploads */
            $uploads = $this->uploads;
            $fileManager->validateUploads($uploads);

            $uploadedPaths = [];
            $uploadedPreviewFiles = [];
            foreach ($uploads as $upload) {
                $path = $fileManager->upload(auth()->user(), $upload, $this->path);
                $uploadedPaths[] = $path;
                $uploadedPreviewFiles[] = [
                    'path' => $path,
                    'name' => basename($path),
                    'is_image' => str_starts_with((string) $upload->getMimeType(), 'image/'),
                ];
            }

            $this->reset('uploads');
            $this->uploadedPreviewFiles = array_values(array_merge($uploadedPreviewFiles, $this->uploadedPreviewFiles));
            $this->refreshItems();
            $this->dispatch('file-manager:uploaded', paths: $uploadedPaths);

            Notification::make()
                ->success()
                ->title(count($uploadedPaths) === 1 ? 'File uploaded' : 'Files uploaded')
                ->body(count($uploadedPaths) === 1 ? basename($uploadedPaths[0]) : sprintf('%d files were uploaded successfully.', count($uploadedPaths)))
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Upload validation failed')
                ->body('Review the selected files and try again.')
                ->send();

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->reset('uploads');

            if ($exception instanceof InvalidFilePath) {
                $this->addError('uploads', $exception->getMessage());
            }

            Notification::make()
                ->danger()
                ->title('Upload failed')
                ->body('The files could not be uploaded. Please try again.')
                ->send();
        }
    }

    public function delete(string $path, FileManagerService $fileManager): void
    {
        $fileManager = $fileManager->forArea($this->storageArea);
        $type = 'item';
        foreach ($this->items as $item) {
            if ($item['path'] === $path) {
                $type = $item['type'];
                break;
            }
        }

        $label = $type === 'directory' ? 'folder' : ($type === 'file' ? 'file' : 'item');

        try {
            $fileManager->delete(auth()->user(), $path);
            $this->selectedPaths = array_values(array_filter(
                $this->selectedPaths,
                static fn (string $selectedPath): bool => $selectedPath !== $path,
            ));
            $this->refreshItems();

            Notification::make()
                ->success()
                ->title($type === 'directory' ? 'Folder deleted' : 'File deleted')
                ->body('The '.$label.' was removed successfully.')
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('Delete failed')
                ->body($exception instanceof FolderNotEmpty ? $exception->getMessage() : 'The '.$label.' could not be deleted. Please try again.')
                ->send();
        }
    }

    public function deleteItemAction(): Action
    {
        return Action::make('deleteItem')
            ->requiresConfirmation()
            ->modalHeading('Delete item?')
            ->modalDescription('This permanently deletes the selected file or folder from storage.')
            ->modalSubmitActionLabel('Delete item')
            ->color('danger')
            ->action(function (array $arguments): void {
                $path = $arguments['path'] ?? null;

                if (is_string($path)) {
                    $this->delete($path, app(FileManagerService::class));
                }
            });
    }

    public function toggleSelection(string $path): void
    {
        if (! in_array($path, array_column($this->items, 'path'), true)) {
            return;
        }

        if (in_array($path, $this->selectedPaths, true)) {
            $this->selectedPaths = array_values(array_filter(
                $this->selectedPaths,
                static fn (string $selectedPath): bool => $selectedPath !== $path,
            ));

            return;
        }

        $this->selectedPaths[] = $path;
    }

    public function clearSelection(): void
    {
        $this->selectedPaths = [];
    }

    public function deleteSelectedItemsAction(): Action
    {
        return Action::make('deleteSelectedItems')
            ->requiresConfirmation()
            ->modalHeading('Delete selected items?')
            ->modalDescription(fn (): string => sprintf('This permanently deletes the %d selected item%s. This action cannot be undone.', count($this->selectedPaths), count($this->selectedPaths) === 1 ? '' : 's'))
            ->modalSubmitActionLabel('Delete selected items')
            ->color('danger')
            ->action(function (): void {
                $this->deleteSelectedItems(app(FileManagerService::class));
            });
    }

    public function deleteSelectedItems(FileManagerService $fileManager): void
    {
        $fileManager = $fileManager->forArea($this->storageArea);
        $currentPaths = array_column($this->items, 'path');
        $paths = array_values(array_unique(array_filter(
            $this->selectedPaths,
            static fn (mixed $path): bool => is_string($path) && in_array($path, $currentPaths, true),
        )));

        if ($paths === []) {
            $this->clearSelection();

            return;
        }

        $deletedPaths = [];
        $failed = 0;
        $nonEmptyFolders = 0;

        foreach ($paths as $path) {
            try {
                $fileManager->delete(auth()->user(), $path);
                $deletedPaths[] = $path;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $nonEmptyFolders += $exception instanceof FolderNotEmpty ? 1 : 0;
            }
        }

        $this->selectedPaths = array_values(array_diff($paths, $deletedPaths));
        $this->refreshItems();

        $nonEmptyFolderMessage = sprintf(
            '%d folder%s %s not deleted because %s still contain%s files or folders. Move or delete %s contents, then try again.',
            $nonEmptyFolders,
            $nonEmptyFolders === 1 ? '' : 's',
            $nonEmptyFolders === 1 ? 'was' : 'were',
            $nonEmptyFolders === 1 ? 'it' : 'they',
            $nonEmptyFolders === 1 ? 's' : '',
            $nonEmptyFolders === 1 ? 'its' : 'their',
        );

        $notification = Notification::make()
            ->title($failed === 0 ? 'Selected items deleted' : 'Some items were not deleted')
            ->body($failed === 0
                ? sprintf('%d item%s deleted successfully.', count($deletedPaths), count($deletedPaths) === 1 ? ' was' : 's were')
                : ($nonEmptyFolders > 0
                    ? (count($deletedPaths) > 0
                        ? sprintf('%d item%s deleted. %s', count($deletedPaths), count($deletedPaths) === 1 ? ' was' : 's were', $nonEmptyFolderMessage)
                        : $nonEmptyFolderMessage)
                    : sprintf('%d item%s could not be deleted and remain selected.', $failed, $failed === 1 ? '' : 's')));

        if ($failed === 0) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
    }

    public function deleteUploadedPreview(string $path, FileManagerService $fileManager): void
    {
        $fileManager = $fileManager->forArea($this->storageArea);
        if (! in_array($path, array_column($this->uploadedPreviewFiles, 'path'), true)) {
            return;
        }

        try {
            $fileManager->delete(auth()->user(), $path);
            $this->uploadedPreviewFiles = array_values(array_filter(
                $this->uploadedPreviewFiles,
                static fn (array $file): bool => $file['path'] !== $path,
            ));
            $this->refreshItems();

            Notification::make()
                ->success()
                ->title('File deleted')
                ->body('The uploaded file was removed successfully.')
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('Delete failed')
                ->body('The uploaded file could not be deleted. Please try again.')
                ->send();
        }
    }

    public function deleteUploadedPreviewAction(): Action
    {
        return Action::make('deleteUploadedPreview')
            ->requiresConfirmation()
            ->modalHeading('Delete uploaded file?')
            ->modalDescription('This permanently deletes the file from storage.')
            ->modalSubmitActionLabel('Delete file')
            ->color('danger')
            ->action(function (array $arguments): void {
                $path = $arguments['path'] ?? null;

                if (is_string($path)) {
                    $this->deleteUploadedPreview($path, app(FileManagerService::class));
                }
            });
    }

    public function deleteAllUploadedPreviewsAction(): Action
    {
        return Action::make('deleteAllUploadedPreviews')
            ->requiresConfirmation()
            ->modalHeading('Delete all these uploads?')
            ->modalDescription('This permanently deletes every file currently listed in this upload window.')
            ->modalSubmitActionLabel('Delete all files')
            ->color('danger')
            ->action(function (): void {
                $this->deleteAllUploadedPreviews(app(FileManagerService::class));
            });
    }

    public function deleteAllUploadedPreviews(FileManagerService $fileManager): void
    {
        $fileManager = $fileManager->forArea($this->storageArea);
        $deletedPaths = [];
        $failed = 0;

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
            $this->refreshItems();
        }

        if ($failed === 0) {
            Notification::make()
                ->success()
                ->title('Recent uploads deleted')
                ->body(sprintf('%d %s deleted successfully.', count($deletedPaths), count($deletedPaths) === 1 ? 'file was' : 'files were'))
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title('Some files could not be deleted')
            ->body(sprintf('%d file%s remain in the upload list.', $failed, $failed === 1 ? '' : 's'))
            ->send();
    }

    public function select(string $path): void
    {
        $this->dispatch('unifile-manager:selected', path: $path);
    }

    public function download(string $path, FileManagerService $fileManager): StreamedResponse
    {
        return $fileManager->forArea($this->storageArea)->download(auth()->user(), $path);
    }

    public function previewUrl(string $path): string
    {
        return route('filament-file-manager.preview', ['path' => $path, 'area' => $this->storageArea]);
    }

    public function publicUrl(string $path): ?string
    {
        return $this->manager()->publicUrl(auth()->user(), $path);
    }

    public function thumbnailUrl(string $path): string
    {
        return route('filament-file-manager.preview', ['path' => $path, 'area' => $this->storageArea, 'thumbnail' => 1]);
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

        if ($search === '') {
            return $this->items;
        }

        return array_values(array_filter(
            $this->items,
            static fn (array $item): bool => str_contains(mb_strtolower($item['name']), $search),
        ));
    }

    /** @return list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public function getFoldersProperty(): array
    {
        return array_values(array_filter(
            $this->sortedItems,
            static fn (array $item): bool => $item['type'] === 'directory',
        ));
    }

    /** @return list<array{name: string, path: string, type: 'directory'|'file', size?: int, modified_at?: int, mime_type?: string}> */
    public function getFilesProperty(): array
    {
        return array_values(array_filter(
            $this->sortedItems,
            static fn (array $item): bool => $item['type'] === 'file',
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

    public function getPaginatedFoldersProperty(): LengthAwarePaginator
    {
        return $this->paginate($this->folders, 'foldersPage', (int) config('filament-file-manager.folders_per_page'));
    }

    public function getPaginatedFilesProperty(): LengthAwarePaginator
    {
        return $this->paginate($this->files, 'filesPage', (int) config('filament-file-manager.files_per_page'));
    }

    public function getPaginatedItemsProperty(): LengthAwarePaginator
    {
        return $this->paginate($this->sortedItems, 'itemsPage', (int) config('filament-file-manager.items_per_page'));
    }

    private function refreshItems(): void
    {
        $this->items = $this->manager()->list(auth()->user(), $this->path);
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

    private function resetPagination(): void
    {
        $this->resetPage('foldersPage');
        $this->resetPage('filesPage');
        $this->resetPage('itemsPage');
    }

    private function showNewFolderPage(string $path): void
    {
        $items = $this->displayMode === 'separate' ? $this->folders : $this->sortedItems;
        $index = array_search($path, array_column($items, 'path'), true);

        if ($index === false) {
            return;
        }

        $perPage = $this->displayMode === 'separate'
            ? (int) config('filament-file-manager.folders_per_page')
            : (int) config('filament-file-manager.items_per_page');
        $page = intdiv($index, max(1, $perPage)) + 1;
        if ($this->displayMode === 'separate') {
            $this->setPage($page, 'foldersPage');

            return;
        }

        $this->setPage($page, 'itemsPage');
    }

    private function isFilePath(string $path): bool
    {
        foreach ($this->items as $item) {
            if ($item['path'] === $path) {
                return $item['type'] === 'file';
            }
        }

        return false;
    }

    private function manager(): FileManagerService
    {
        return app(FileManagerService::class)->forArea($this->storageArea);
    }

    private function defaultStorageArea(): string
    {
        $default = config('filament-file-manager.file_picker_default_area', 'private');

        return is_string($default) && isset($this->availableStorageAreas()[$default])
            ? $default
            : (string) array_key_first($this->availableStorageAreas());
    }

    private function fileNameWithoutExtension(string $name): string
    {
        $extension = $this->fileExtension($name);

        return $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));
    }

    private function fileExtension(string $path): string
    {
        return pathinfo(basename($path), PATHINFO_EXTENSION);
    }

    private function restorePreferences(): void
    {
        if (! $this->canChooseItemLayout()) {
            $this->displayMode = 'all';

            return;
        }

        $preferences = session()->get($this->preferencesKey());

        if (! is_array($preferences)) {
            return;
        }

        if (in_array($preferences['display_mode'] ?? null, self::DISPLAY_MODES, true)) {
            $this->displayMode = $preferences['display_mode'];
        }

        if (in_array($preferences['sort_by'] ?? null, self::SORT_FIELDS, true)) {
            $this->sortBy = $preferences['sort_by'];
        }

        if (in_array($preferences['sort_direction'] ?? null, ['asc', 'desc'], true)) {
            $this->sortDirection = $preferences['sort_direction'];
        }
    }

    private function persistPreferences(): void
    {
        session()->put($this->preferencesKey(), [
            'display_mode' => $this->displayMode,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ]);
    }

    private function preferencesKey(): string
    {
        return 'filament-file-manager.preferences.'.(string) (auth()->id() ?? 'guest');
    }
}
