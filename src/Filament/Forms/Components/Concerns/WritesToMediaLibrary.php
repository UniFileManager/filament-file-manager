<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Filament\Forms\Components\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use UniFileManager\Core\Contracts\StorageAreaResolver;

/**
 * Let the picker read and write a spatie media-library collection.
 *
 * By default a picked path is assigned to a model attribute, which needs a
 * column to land in. An application that keeps its images in media collections
 * has no such column — the images are rows in `media`, and its API reads them
 * from there — so pointing the picker at `avatar` fails with "column does not
 * exist". Adding a column per field would leave the conversions, the ordering
 * and the per-image properties stranded in the media table.
 *
 * With `->collection('images')` the field reads and writes that collection
 * instead. No schema change, and everything already reading the collection —
 * API resources, `getFirstMediaUrl()`, image columns — keeps working unchanged.
 *
 * Nothing is copied. A picked file is already an object on the disk, so the row
 * records where it is in an `external_dir` custom property rather than
 * duplicating the object under an id-based key; the file keeps the URL the apps
 * already hold. A path generator that honours that property is required — see
 * the package README.
 *
 * Unlinking never deletes the file. A library is shared by definition: the same
 * object can back many records, and taking it off one of them must not blank the
 * others. Deleting from the disk is the file manager's own job, behind its own
 * authorization.
 */
trait WritesToMediaLibrary
{
    protected string|Closure|null $mediaCollection = null;

    /**
     * Store this field's selection in a media-library collection.
     */
    public function collection(string|Closure|null $name): static
    {
        if ($name !== null) {
            $this->ensureMediaLibraryIsInstalled();
        }

        $this->mediaCollection = $name;

        if ($name === null) {
            return $this;
        }

        // The value is not the record's to hold; it lives in the media table.
        $this->dehydrated(false);

        $this->afterStateHydrated(static function (self $component, ?Model $record): void {
            $component->state($component->mediaLibraryPaths($record));
        });

        $this->saveRelationshipsUsing(static function (self $component, mixed $state, ?Model $record): void {
            $component->syncMediaLibrary($state, $record);
        });

        return $this;
    }

    public function getCollection(): ?string
    {
        return $this->evaluate($this->mediaCollection);
    }

    public function writesToMediaLibrary(): bool
    {
        return filled($this->getCollection());
    }

    /**
     * The collection's contents as picker paths, in display order.
     *
     * @return array<int, string>|string|null
     */
    public function mediaLibraryPaths(?Model $record): array|string|null
    {
        if (! $record instanceof HasMedia) {
            return $this->isMultiple() ? [] : null;
        }

        $paths = $record->getMedia((string) $this->getCollection())
            ->sortBy('order_column')
            // Whatever the row points at, this is its key on the disk — an
            // id-based one the library uploaded, or a library object it links.
            ->map(fn (Media $media): string => $this->toPickerPath($media->getPathRelativeToRoot()))
            ->values()
            ->all();

        return $this->isMultiple() ? $paths : ($paths[0] ?? null);
    }

    /**
     * Bring the collection in line with the selection, in the chosen order.
     */
    public function syncMediaLibrary(mixed $state, ?Model $record = null): void
    {
        // getRecord() reaches for the component's container, which a field
        // built outside a schema does not have.
        $record ??= rescue(fn (): ?Model => $this->getRecord(), null, false);

        if (! $record instanceof HasMedia || ! $record instanceof Model) {
            return;
        }

        $collection = (string) $this->getCollection();
        $state = $this->validateSelectionState($state);

        /** @var array<int, string> $selected */
        $selected = array_values(array_filter(
            is_array($state) ? $state : [$state],
            static fn (mixed $path): bool => is_string($path) && $path !== '',
        ));

        $existing = $record->getMedia($collection)
            ->keyBy(fn (Media $media): string => $this->toPickerPath($media->getPathRelativeToRoot()));

        $order = 1;

        foreach ($selected as $path) {
            $media = $existing->get($path);

            if ($media instanceof Media) {
                // Already linked — only its position can have changed.
                if ((int) $media->order_column !== $order) {
                    $media->order_column = $order;
                    $media->save();
                }
            } else {
                $this->linkMedia($record, $collection, $path, $order);
            }

            $order++;
        }

        foreach ($existing as $path => $media) {
            if (! in_array($path, $selected, true)) {
                $this->unlinkMedia($media);
            }
        }

        $record->unsetRelation('media');
    }

    /**
     * Record an object already on the disk as a member of the collection.
     */
    protected function linkMedia(Model&HasMedia $record, string $collection, string $path, int $order): void
    {
        $disk = $this->mediaLibraryDisk();
        $key = $this->toDiskKey($path);
        $fileName = basename($key);
        $storage = Storage::disk($disk);

        $record->media()->create([
            'collection_name' => $collection,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => rescue(fn (): ?string => $storage->mimeType($key) ?: null, null, false),
            'disk' => $disk,
            'conversions_disk' => $disk,
            'size' => rescue(fn (): int => (int) $storage->size($key), 0, false),
            'manipulations' => [],
            // Where the object actually is. Without this the library resolves the
            // row to an id-based key that holds nothing.
            'custom_properties' => ['external_dir' => trim((string) pathinfo($key, PATHINFO_DIRNAME), '.')],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => $order,
        ]);
    }

    /**
     * Drop the row and leave the object alone.
     *
     * Deleting through the model fires the media observer, which removes the
     * file — and that file is a library object other records may point at.
     */
    protected function unlinkMedia(Media $media): void
    {
        Media::withoutEvents(static fn () => Media::query()->whereKey($media->getKey())->delete());
    }

    /**
     * Fail clearly when the optional integration package is not available.
     */
    protected function ensureMediaLibraryIsInstalled(): void
    {
        if (class_exists(HasMedia::class) && class_exists(Media::class)) {
            return;
        }

        throw new LogicException('UniFilePicker media-library collections require spatie/laravel-medialibrary to be installed.');
    }

    /**
     * The disk behind this field's storage area.
     */
    protected function mediaLibraryDisk(): string
    {
        return (string) ($this->mediaLibraryArea()['disk'] ?? config('media-library.disk_name'));
    }

    /**
     * The area's root prefix, without surrounding slashes.
     */
    protected function mediaLibraryRoot(): string
    {
        return trim((string) ($this->mediaLibraryArea()['root'] ?? ''), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function mediaLibraryArea(): array
    {
        return app(StorageAreaResolver::class)->resolve($this->getStorageArea()) ?? [];
    }

    /**
     * Picker paths are relative to the area root; media rows record the key on
     * the disk. These two convert between them, and are identity when the area
     * is the whole bucket.
     */
    protected function toDiskKey(string $pickerPath): string
    {
        $root = $this->mediaLibraryRoot();

        return $root === '' ? ltrim($pickerPath, '/') : $root.'/'.ltrim($pickerPath, '/');
    }

    protected function toPickerPath(string $diskKey): string
    {
        $root = $this->mediaLibraryRoot();
        $diskKey = ltrim($diskKey, '/');

        if ($root === '') {
            return $diskKey;
        }

        if ($diskKey === $root) {
            return '';
        }

        if (str_starts_with($diskKey, $root.'/')) {
            return substr($diskKey, strlen($root) + 1);
        }

        return $diskKey;
    }
}
