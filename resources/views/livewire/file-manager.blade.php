<x-filament-panels::page
    class="ufm"
    x-data="{ optionsOpen: false, storageOpen: false, filePreview: null, uploadModal: false, recentlyUploaded: [], uploadHighlightTimer: null, uploadDragging: false, uploading: false, uploadProgress: 0, maxUploadFiles: {{ $this->maximumUploadFiles() }}, copyText(value) { if (! value) return; if (navigator.clipboard?.writeText) { navigator.clipboard.writeText(value); return; } const textarea = document.createElement('textarea'); textarea.value = value; textarea.style.position = 'fixed'; textarea.style.opacity = '0'; document.body.appendChild(textarea); textarea.select(); document.execCommand('copy'); textarea.remove(); } }"
    x-on:unifile-manager:selected.window="localStorage.setItem('unifile-manager:selected', JSON.stringify({ path: $event.detail.path, nonce: Date.now() }))"
    x-on:file-manager:focus-rename.window="$nextTick(() => { $refs.renameItem?.focus(); $refs.renameItem?.select(); })"
    x-on:file-manager:uploaded.window="recentlyUploaded = $event.detail.paths ?? []; clearTimeout(uploadHighlightTimer); uploadHighlightTimer = setTimeout(() => recentlyUploaded = [], 4000)"
>
    <div class="ufm ufm__layout">
        <section class="ufm__hero">
            <div class="ufm__hero-main">
                <div>
                    <p class="ufm__eyebrow">{{ __('filament-file-manager::file-manager.file_library') }}</p>
                    <div class="ufm__title-row">
                        <h1 class="ufm__title">{{ $path === '' ? __('filament-file-manager::file-manager.main_library') : basename($path) }}</h1>
                    </div>
                    <nav class="ufm__breadcrumb" aria-label="{{ __('filament-file-manager::file-manager.current_folder') }}">
                        @if ($path !== '')
                            <button type="button" wire:click="up" class="ufm__breadcrumb-back" aria-label="{{ __('filament-file-manager::file-manager.go_to_parent_folder') }}" title="{{ __('filament-file-manager::file-manager.go_to_parent_folder') }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14.5 5-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <span class="ufm__breadcrumb-separator" aria-hidden="true">/</span>
                        @endif
                        <button type="button" wire:click="open('')" aria-label="{{ __('filament-file-manager::file-manager.open_main_library') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 10 8-6 8 6v9a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9Z" stroke-linejoin="round"/></svg>
                            <span>{{ __('filament-file-manager::file-manager.main_library') }}</span>
                        </button>
                        @if ($path !== '')
                            @php
                                $breadcrumbPath = '';
                                $breadcrumbSegments = array_values(array_filter(explode('/', $path)));
                            @endphp
                            @foreach ($breadcrumbSegments as $segment)
                                <span class="ufm__breadcrumb-separator">/</span>
                                @php
                                    $breadcrumbPath = $breadcrumbPath === '' ? $segment : $breadcrumbPath.'/'.$segment;
                                @endphp
                                @if ($loop->last)
                                    <span class="ufm__breadcrumb-current" aria-current="page">{{ $segment }}</span>
                                @else
                                    <button type="button" wire:click="open({{ \Illuminate\Support\Js::from($breadcrumbPath) }})">{{ $segment }}</button>
                                @endif
                            @endforeach
                        @endif
                    </nav>
                </div>

                <div class="ufm__hero-actions">
                    @if (count($this->availableStorageAreas()) > 1)
                        <div class="ufm__storage-switcher" x-on:click.outside="storageOpen = false">
                            <button type="button" class="ufm__storage-trigger" x-on:click="storageOpen = ! storageOpen" x-bind:aria-expanded="storageOpen">
                                @if ($storageArea === 'public')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7.5h16v11H4zM8 7.5V5h8v2.5M8.5 12h7M12 9.5v5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"/></svg>
                                @endif
                                <span>{{ $this->availableStorageAreas()[$storageArea] }}</span>
                                <svg class="ufm__storage-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 10 5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div x-show="storageOpen" x-cloak class="ufm__storage-menu">
                                @foreach ($this->availableStorageAreas() as $area => $label)
                                    <button type="button" wire:click="setStorageArea('{{ $area }}')" x-on:click="storageOpen = false" @class(['is-active' => $storageArea === $area])>
                                        @if ($area === 'public')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7.5h16v11H4zM8 7.5V5h8v2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"/></svg>
                                        @endif
                                        <span>{{ $label }}</span>
                                        @if ($storageArea === $area)<span class="ufm__storage-check">✓</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <span class="ufm__storage-indicator" title="This File Manager uses {{ $this->availableStorageAreas()[$storageArea] }}">
                            @if ($storageArea === 'public')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7.5h16v11H4zM8 7.5V5h8v2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"/></svg>
                            @endif
                            <span class="ufm__storage-indicator-label">{{ __('filament-file-manager::file-manager.storage') }}</span>
                            <span>{{ $this->availableStorageAreas()[$storageArea] }}</span>
                        </span>
                    @endif
                    <button type="button" x-on:click="uploadModal = true" class="ufm__button ufm__button--primary">{{ __('filament-file-manager::file-manager.upload_files') }}</button>
                </div>
            </div>
        </section>

        <div x-show="uploadModal" x-cloak x-on:keydown.escape.window="$wire.closeUploadModal(); uploadModal = false" class="ufm__upload-modal" role="dialog" aria-modal="true" aria-label="{{ __('filament-file-manager::file-manager.upload_files') }}">
            <div class="ufm__preview-backdrop" x-on:click="$wire.closeUploadModal(); uploadModal = false"></div>
                <div class="ufm__upload-dialog">
                <div class="ufm__preview-header">
                    <div><p>{{ __('filament-file-manager::file-manager.upload_files') }}</p><span>{{ sprintf(__('filament-file-manager::file-manager.choose_files_to_upload'), $this->maximumUploadFiles()) }}</span></div>
                    <button type="button" x-on:click="$wire.closeUploadModal(); uploadModal = false" aria-label="{{ __('filament-file-manager::file-manager.close_upload_dialog') }}">×</button>
                </div>

                <label
                    class="ufm__upload-dropzone"
                    x-bind:class="{ 'is-dragging': uploadDragging }"
                    x-on:dragover.prevent="uploadDragging = true"
                    x-on:dragleave.prevent="uploadDragging = false"
                    x-on:drop.prevent="uploadDragging = false; if ($event.dataTransfer.files.length > maxUploadFiles) { $wire.rejectTooManyFiles($event.dataTransfer.files.length); return; } $refs.uploadInput.files = $event.dataTransfer.files; $refs.uploadInput.dispatchEvent(new Event('change', { bubbles: true }))"
                >
                    <input x-ref="uploadInput" type="file" wire:model="uploads" multiple x-on:livewire-upload-start="uploading = true; uploadProgress = 0" x-on:livewire-upload-progress="uploadProgress = $event.detail.progress" x-on:livewire-upload-finish="uploading = false; uploadProgress = 100" x-on:livewire-upload-error="uploading = false" x-on:change.capture="if ($event.target.files.length > maxUploadFiles) { $event.stopImmediatePropagation(); $wire.rejectTooManyFiles($event.target.files.length); $event.target.value = ''; }" class="ufm__visually-hidden" />
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 18.5h10a4 4 0 0 0 .77-7.93A5.8 5.8 0 0 0 6.7 9.2 4.7 4.7 0 0 0 7 18.5Z" stroke-linecap="round"/><path d="m12 15.5 0-7m0 0-2.5 2.5M12 8.5l2.5 2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="ufm__upload-dropzone-title">{{ __('filament-file-manager::file-manager.choose_files_and_drop') }}</span>
                    <span class="ufm__upload-dropzone-help">{{ sprintf(__('filament-file-manager::file-manager.uploads_start_automatically'), strtoupper(implode(', ', array_slice(config('filament-file-manager.allowed_extensions'), 0, 5))), round(config('filament-file-manager.max_upload_size') / 1024) ) }}</span>
                    <span class="ufm__upload-browse">{{ __('filament-file-manager::file-manager.browse_files') }}</span>
                </label>

                @error('uploads') <p class="ufm__error">{{ $message }}</p> @enderror
                <div x-show="uploading" x-cloak class="ufm__upload-progress" aria-live="polite">
                    <div class="ufm__upload-progress-label"><span>{{ __('filament-file-manager::file-manager.uploading_files') }}</span><span x-text="uploadProgress + '%'">0%</span></div>
                    <div class="ufm__upload-progress-track"><span x-bind:style="'width: ' + uploadProgress + '%' "></span></div>
                </div>

                @if (count($uploadedPreviewFiles) > 0)
                    <div class="ufm__upload-review">
                        <div class="ufm__upload-review-title">
                            <strong>{{ count($uploadedPreviewFiles) === 1 ? __('filament-file-manager::file-manager.uploaded_file') : __('filament-file-manager::file-manager.uploaded_files') }}</strong>
                            <x-filament::button color="danger" size="sm" wire:click="mountAction('deleteAllUploadedPreviews')">{{ __('filament-file-manager::file-manager.delete_all') }}</x-filament::button>
                        </div>
                        <div class="ufm__upload-preview-grid">
                            @foreach ($uploadedPreviewFiles as $uploadedFile)
                                <div class="ufm__upload-preview-card ufm__upload-preview-card--complete" x-bind:class="{ 'ufm__upload-preview-card--new': recentlyUploaded.includes({{ \Illuminate\Support\Js::from($uploadedFile['path']) }}) }">
                                    @if ($uploadedFile['is_image'])
                                        <img src="{{ $this->thumbnailUrl($uploadedFile['path']) }}" alt="" />
                                    @else
                                        @php
                                            $documentKind = $this->documentKind($uploadedFile);
                                        @endphp
                                        <span class="ufm__document-icon ufm__document-icon--{{ $documentKind }}"><span>{{ strtoupper($documentKind === 'word' ? 'doc' : $documentKind) }}</span></span>
                                    @endif
                                    <p>{{ $uploadedFile['name'] }}</p>
                                    <em>Uploaded</em>
                                    <button type="button" wire:click="mountAction('deleteUploadedPreview', { path: {{ \Illuminate\Support\Js::from($uploadedFile['path']) }} })" class="ufm__upload-remove ufm__upload-remove--danger" aria-label="{{ __('filament-file-manager::file-manager.delete') .' '. $uploadedFile['name'] }}" title="{{ __('filament-file-manager::file-manager.delete_file') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3m-9 0 1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <section class="ufm__search-row">
            <label class="ufm__search">
                <span class="ufm__search-icon">⌕</span>
                <input wire:model.live.debounce.250ms="search" type="search" placeholder="{{ __('filament-file-manager::file-manager.search_this_folder') }}" />
            </label>
            <p class="ufm__item-count">{{ count($this->filteredItems) .' '. (count($this->filteredItems) === 1 ? __('filament-file-manager::file-manager.item') : __('filament-file-manager::file-manager.items')) }}</p>
        </section>

        @if (count($selectedPaths) > 0)
            <div class="ufm__selection-toolbar">
                <span>{{ count($selectedPaths) .' '. __('filament-file-manager::file-manager.selected') }}</span>
                <button type="button" class="ufm__button ufm__button--danger" wire:click="mountAction('deleteSelectedItems')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3m-9 0 1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    __('filament-file-manager::file-manager.delete_selected')
                </button>
                <button type="button" class="ufm__button ufm__button--ghost" wire:click="clearSelection">{{ __('filament-file-manager::file-manager.clear') }}</button>
            </div>
        @endif

        @if ($displayMode === 'separate')
        <section class="ufm__section">
            <div class="ufm__section-heading">
                <div>
                    <h2>{{ __('filament-file-manager::file-manager.folders') }}</h2>
                    <p>{{ __('filament-file-manager::file-manager.folders_description') }}</p>
                </div>
                <div class="ufm__library-actions">
                    <div class="ufm__options">
                        <button type="button" x-on:click="optionsOpen = ! optionsOpen" class="ufm__icon-button" aria-label="{{ $this->canChooseItemLayout() ? __('filament-file-manager::file-manager.item_layout_and_sort_options') : __('filament-file-manager::file-manager.sort_options') }}" x-bind:aria-expanded="optionsOpen">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h10M4 12h16M4 17h7" stroke-linecap="round"/><path d="m16 5 2 2-2 2M12 15l2 2-2 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div x-show="optionsOpen" x-cloak x-on:click.outside="optionsOpen = false" class="ufm__options-menu">
                            @if ($this->canChooseItemLayout())
                                <p class="ufm__options-label">{{ __('filament-file-manager::file-manager.item_layout') }}</p>
                                <button type="button" wire:click="setDisplayMode('separate')" aria-pressed="{{ $displayMode === 'separate' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'separate'])>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                    {{ __('filament-file-manager::file-manager.folders_first') }}
                                </button>
                                <button type="button" wire:click="setDisplayMode('all')" aria-pressed="{{ $displayMode === 'all' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'all'])>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 6h14M5 12h14M5 18h14" stroke-linecap="round"/></svg>
                                    {{ __('filament-file-manager::file-manager.items_together') }}
                                </button>
                                <div class="ufm__options-divider"></div>
                            @endif
                            <p class="ufm__options-label">{{ __('filament-file-manager::file-manager.sort_by') }}</p>
                            <select wire:model.live="sortBy" class="ufm__sort-select" aria-label="{{ __('filament-file-manager::file-manager.sort_files_and_folders') }}">
                                <option value="name">{{ __('filament-file-manager::file-manager.name') }}</option>
                                <option value="modified_at">{{ __('filament-file-manager::file-manager.last_modified') }}</option>
                                <option value="type">{{ __('filament-file-manager::file-manager.type') }}</option>
                            </select>
                            <button type="button" wire:click="toggleSortDirection" class="ufm__option">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 4v16m0-16-3 3m3-3 3 3M16 20V4m0 16-3-3m3 3 3-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ $sortDirection === 'asc' ? __('filament-file-manager::file-manager.ascending') : __('filament-file-manager::file-manager.descending') }}
                            </button>
                        </div>
                    </div>
                    <button type="button" wire:click="createNewDirectory" class="ufm__text-button">+ {{ __('filament-file-manager::file-manager.new_folder') }}</button>
                </div>
            </div>

            <div class="ufm__folder-grid">
                @foreach ($this->paginatedFolders->items() as $folder)
                    @if ($renamingPath === $folder['path'])
                        <form wire:submit="saveRename" class="ufm__folder-card ufm__folder-card--renaming">
                            <span class="ufm__folder-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg></span>
                            <label class="ufm__rename-field">
                                <span class="ufm__visually-hidden">{{ __('filament-file-manager::file-manager.folder_name') }}</span>
                                <input x-ref="renameItem" wire:model="renamingName" wire:keydown.escape="cancelRename" type="text" maxlength="100" />
                                @error('renamingName') <span class="ufm__error">{{ $message }}</span> @enderror
                            </label>
                            <div class="ufm__rename-actions">
                                <button type="submit" class="ufm__rename-action ufm__rename-action--save" aria-label="{{ __('filament-file-manager::file-manager.save_folder_name') }}" title="Save name">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button type="button" wire:click="cancelRename" class="ufm__rename-action ufm__rename-action--cancel" aria-label="{{ __('filament-file-manager::file-manager.cancel_folder_rename') }}" title="{{ __('filament-file-manager::file-manager.cancel_rename') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                        </form>
                    @else
                        <article class="ufm__folder-card-wrap" @class(['ufm__folder-card-wrap--selected' => in_array($folder['path'], $selectedPaths, true)])>
                            @if (in_array($folder['path'], $selectedPaths, true))
                                <span class="ufm-picker-explorer__selection-check ufm__selection-check" aria-label="{{ __('filament-file-manager::file-manager.selected') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            @endif
                            <button type="button" wire:click="open({{ \Illuminate\Support\Js::from($folder['path']) }})" class="ufm__folder-card">
                                <span class="ufm__folder-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg></span>
                                <span class="ufm__folder-copy">
                                    <span class="ufm__folder-name">{{ $folder['name'] }}</span>
                                    <span class="ufm__folder-meta">{{ __('filament-file-manager::file-manager.folder') }}</span>
                                </span>
                                <span class="ufm__folder-arrow">→</span>
                            </button>
                            <div class="ufm__folder-actions">
                                <button type="button" class="ufm__file-action" wire:click="beginRename({{ \Illuminate\Support\Js::from($folder['path']) }})" aria-label="{{ __('filament-file-manager::file-manager.rename_folder') }}" title="{{ __('filament-file-manager::file-manager.rename_folder') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button type="button" class="ufm__file-action" wire:click="beginMove({{ \Illuminate\Support\Js::from($folder['path']) }})" aria-label="{{ __('filament-file-manager::file-manager.move_folder') }}" title="{{ __('filament-file-manager::file-manager.move_folder') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3-3m-3 3 3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button type="button" @class(['ufm__file-action', 'is-selected' => in_array($folder['path'], $selectedPaths, true)]) wire:click="toggleSelection({{ \Illuminate\Support\Js::from($folder['path']) }})" aria-pressed="{{ in_array($folder['path'], $selectedPaths, true) ? 'true' : 'false' }}" aria-label="{{ in_array($folder['path'], $selectedPaths, true) ? 'Deselect' : 'Select' }} folder" title="{{ in_array($folder['path'], $selectedPaths, true) ? 'Deselect folder' : 'Select folder' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button type="button" class="ufm__file-action ufm__file-action--danger" wire:click="mountAction('deleteItem', { path: {{ \Illuminate\Support\Js::from($folder['path']) }} })" aria-label="{{ __('filament-file-manager::file-manager.delete_folder') }}" title="{{ __('filament-file-manager::file-manager.delete_folder') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3m-9 0 1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </article>
                    @endif
                @endforeach

                <button type="button" wire:click="createNewDirectory" class="ufm__new-folder-card"><span>+</span> {{ __('filament-file-manager::file-manager.new_folder') }}</button>
            </div>
            @include('filament-file-manager::components.pagination', ['pagination' => $this->paginatedFolders, 'pageName' => 'foldersPage'])
        </section>
        @endif

        <section class="ufm__section">
            <div class="ufm__section-heading">
                <div>
                    <h2>{{ $displayMode === 'separate' ? __('filament-file-manager::file-manager.files') : __('filament-file-manager::file-manager.items') }}</h2>
                    <p>{{ $displayMode === 'separate' ? __('filament-file-manager::file-manager.select_file_to_pick') : __('filament-file-manager::file-manager.folders_and_files_in_this_directory') }}</p>
                </div>
                @if ($displayMode === 'all')
                    <div class="ufm__library-actions">
                        <div class="ufm__options">
                            <button type="button" x-on:click="optionsOpen = ! optionsOpen" class="ufm__icon-button" aria-label="{{ $this->canChooseItemLayout() ? __('filament-file-manager::file-manager.item_layout_and_sort_options') : __('filament-file-manager::file-manager.sort_options') }}" x-bind:aria-expanded="optionsOpen">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h10M4 12h16M4 17h7" stroke-linecap="round"/><path d="m16 5 2 2-2 2M12 15l2 2-2 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div x-show="optionsOpen" x-cloak x-on:click.outside="optionsOpen = false" class="ufm__options-menu">
                                @if ($this->canChooseItemLayout())
                                    <p class="ufm__options-label">{{ __('filament-file-manager::file-manager.item_layout') }}</p>
                                    <button type="button" wire:click="setDisplayMode('separate')" aria-pressed="{{ $displayMode === 'separate' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'separate'])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>{{ __('filament-file-manager::file-manager.folders_first') }}</button>
                                    <button type="button" wire:click="setDisplayMode('all')" aria-pressed="{{ $displayMode === 'all' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'all'])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 6h14M5 12h14M5 18h14" stroke-linecap="round"/></svg>{{ __('filament-file-manager::file-manager.all_items_together') }}</button>
                                    <div class="ufm__options-divider"></div>
                                @endif
                                <p class="ufm__options-label">{{ __('filament-file-manager::file-manager.sort_by') }}</p>
                                <select wire:model.live="sortBy" class="ufm__sort-select" aria-label="{{ __('filament-file-manager::file-manager.sort_files_and_folders') }}">
                                    <option value="name">{{ __('filament-file-manager::file-manager.name') }}</option>
                                    <option value="modified_at">{{ __('filament-file-manager::file-manager.last_modified') }}</option>
                                    <option value="type">{{ __('filament-file-manager::file-manager.type') }}</option>
                                </select>
                                <button type="button" wire:click="toggleSortDirection" class="ufm__option"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 4v16m0-16-3 3m3-3 3 3M16 20V4m0 16-3-3m3 3 3-3" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $sortDirection === 'asc' ? __('filament-file-manager::file-manager.ascending') : __('filament-file-manager::file-manager.descending') }}</button>
                            </div>
                        </div>
                        <button type="button" wire:click="createNewDirectory" class="ufm__text-button">+ {{ __('filament-file-manager::file-manager.new_folder') }}</button>
                    </div>
                @endif
            </div>

            @php
                $filePagination = $displayMode === 'separate' ? $this->paginatedFiles : $this->paginatedItems;
            @endphp
            @if ($filePagination->total() > 0)
                <div class="ufm__file-grid">
                    @foreach ($filePagination->items() as $file)
                        @if ($renamingPath === $file['path'])
                            <form wire:submit="saveRename" class="ufm__file-card ufm__file-card--renaming">
                                <div class="ufm__file-rename-content">
                                    <span class="ufm__file-preview {{ $file['type'] === 'directory' ? 'ufm__file-preview--folder' : '' }}">
                                        @if ($file['type'] === 'directory')
                                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg>
                                        @elseif ($this->isImage($file))
                                            <img src="{{ $this->thumbnailUrl($file['path']) }}" alt="" loading="lazy" />
                                        @else
                                            @php
                                                $documentKind = $this->documentKind($file);
                                            @endphp
                                            <span class="ufm__document-icon ufm__document-icon--{{ $documentKind }}"><span>{{ strtoupper($documentKind === 'word' ? 'doc' : $documentKind) }}</span></span>
                                        @endif
                                    </span>
                                    <label class="ufm__file-rename-field">
                                        <span class="ufm__visually-hidden">File name</span>
                                        <input x-ref="renameItem" wire:model="renamingName" wire:keydown.escape="cancelRename" type="text" maxlength="100" />
                                        @if ($this->renamingExtension() !== '')
                                            <span class="ufm__file-rename-extension">.{{ $this->renamingExtension() }}</span>
                                        @endif
                                    </label>
                                </div>
                                <div class="ufm__file-actions" aria-label="{{ __('filament-file-manager::file-manager.rename_actions') }}">
                                    <button type="submit" class="ufm__rename-action ufm__rename-action--save" aria-label="{{ __('filament-file-manager::file-manager.save_file_name') }}" title="{{ __('filament-file-manager::file-manager.save_name') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" wire:click="cancelRename" class="ufm__rename-action ufm__rename-action--cancel" aria-label="{{ __('filament-file-manager::file-manager.cancel_file_rename') }}" title="{{ __('filament-file-manager::file-manager.cancel_rename') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>
                                    </button>
                                </div>
                            </form>
                        @else
                        <article class="ufm__file-card" x-bind:class="{ 'ufm__file-card--new': recentlyUploaded.includes({{ \Illuminate\Support\Js::from($file['path']) }}), 'ufm__file-card--selected': {{ in_array($file['path'], $selectedPaths, true) ? 'true' : 'false' }} }">
                            @if (in_array($file['path'], $selectedPaths, true))
                                <span class="ufm-picker-explorer__selection-check ufm__selection-check" aria-label="{{ __('filament-file-manager::file-manager.selected') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            @endif
                            <button
                                type="button"
                                @if ($file['type'] === 'directory') wire:click="open({{ \Illuminate\Support\Js::from($file['path']) }})" @elseif ($this->isPreviewable($file)) x-on:click="filePreview = {{ \Illuminate\Support\Js::from(['url' => $this->previewUrl($file['path']), 'name' => $file['name'], 'path' => $file['path'], 'mime' => $file['mime_type'] ?? null, 'size' => $file['size'] ?? null, 'modified' => $file['modified_at'] ?? null, 'publicUrl' => $this->publicUrl($file['path']), 'isImage' => $this->isImage($file), 'width' => null, 'height' => null]) }}" @else wire:click="select({{ \Illuminate\Support\Js::from($file['path']) }})" @endif
                                class="ufm__file-select"
                            >
                                <span class="ufm__file-preview {{ $file['type'] === 'directory' ? 'ufm__file-preview--folder' : '' }}">
                                    @if ($file['type'] === 'directory')
                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg>
                                    @elseif ($this->isImage($file))
                                        <img src="{{ $this->thumbnailUrl($file['path']) }}" alt="" loading="lazy" />
                                    @else
                                        @php
                                            $documentKind = $this->documentKind($file);
                                        @endphp
                                        <span class="ufm__document-icon ufm__document-icon--{{ $documentKind }}">
                                            <span>{{ strtoupper($documentKind === 'word' ? 'doc' : $documentKind) }}</span>
                                        </span>
                                    @endif
                                </span>
                                <span class="ufm__file-name">{{ $file['name'] }}</span>
                                <span class="ufm__file-size">{{ $file['type'] === 'directory' ? __('filament-file-manager::file-manager.folder') : number_format(($file['size'] ?? 0) / 1024, 1).' KB' }}</span>
                            </button>
                            <div class="ufm__file-actions">
                                @if ($file['type'] === 'file')
                                    <button type="button" class="ufm__file-action" wire:click="beginRename({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="{{ __('filament-file-manager::file-manager.rename_file') }}" title="{{ __('filament-file-manager::file-manager.rename_file') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" class="ufm__file-action" wire:click="beginMove({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="{{ __('filament-file-manager::file-manager.move_file') }}" title="{{ __('filament-file-manager::file-manager.move_file') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3-3m-3 3 3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    @if ($this->isPreviewable($file))
                                        <button type="button" class="ufm__file-action" x-on:click="filePreview = {{ \Illuminate\Support\Js::from(['url' => $this->previewUrl($file['path']), 'name' => $file['name'], 'path' => $file['path'], 'mime' => $file['mime_type'] ?? null, 'size' => $file['size'] ?? null, 'modified' => $file['modified_at'] ?? null, 'publicUrl' => $this->publicUrl($file['path']), 'isImage' => $this->isImage($file), 'width' => null, 'height' => null]) }}" aria-label="{{ __('filament-file-manager::file-manager.preview_file') }}" title="{{ __('filament-file-manager::file-manager.preview_file') }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                        </button>
                                    @endif
                                    <button type="button" @class(['ufm__file-action', 'is-selected' => in_array($file['path'], $selectedPaths, true)]) wire:click="toggleSelection({{ \Illuminate\Support\Js::from($file['path']) }})" aria-pressed="{{ in_array($file['path'], $selectedPaths, true) ? 'true' : 'false' }}" aria-label="{{ in_array($file['path'], $selectedPaths, true) ? __('filament-file-manager::file-manager.deselect_file') : __('filament-file-manager::file-manager.select_file') }}" title="{{ in_array($file['path'], $selectedPaths, true) ? __('filament-file-manager::file-manager.deselect_file') : __('filament-file-manager::file-manager.select_file') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" class="ufm__file-action" wire:click="download({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="{{ __('filament-file-manager::file-manager.download_file') }}" title="{{ __('filament-file-manager::file-manager.download_file') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 20h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                @else
                                    <button type="button" class="ufm__file-action" wire:click="beginRename({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="{{ __('filament-file-manager::file-manager.rename_folder') }}" title="{{ __('filament-file-manager::file-manager.rename_folder') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" class="ufm__file-action" wire:click="beginMove({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="{{ __('filament-file-manager::file-manager.move_folder') }}" title="{{ __('filament-file-manager::file-manager.move_folder') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3-3m-3 3 3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" @class(['ufm__file-action', 'is-selected' => in_array($file['path'], $selectedPaths, true)]) wire:click="toggleSelection({{ \Illuminate\Support\Js::from($file['path']) }})" aria-pressed="{{ in_array($file['path'], $selectedPaths, true) ? 'true' : 'false' }}" aria-label="{{ in_array($file['path'], $selectedPaths, true) ? __('filament-file-manager::file-manager.deselect_folder') : __('filament-file-manager::file-manager.select_folder') }}" title="{{ in_array($file['path'], $selectedPaths, true) ? __('filament-file-manager::file-manager.deselect_folder') : __('filament-file-manager::file-manager.select_folder') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                @endif
                                <button type="button" class="ufm__file-action ufm__file-action--danger" wire:click="mountAction('deleteItem', { path: {{ \Illuminate\Support\Js::from($file['path']) }} })" aria-label="{{ __('filament-file-manager::file-manager.delete') .' '. $file['type'] }}" title="{{ __('filament-file-manager::file-manager.delete') .' '. $file['type'] }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3m-9 0 1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </article>
                        @endif
                    @endforeach
                </div>
                @include('filament-file-manager::components.pagination', ['pagination' => $filePagination, 'pageName' => $displayMode === 'separate' ? 'filesPage' : 'itemsPage'])
            @elseif (count($this->sortedItems) === 0)
                <div class="ufm__empty-state">
                    <div>📂</div>
                    <h3>{{ __('filament-file-manager::file-manager.folder_is_empty') }}</h3>
                    <p>{{ __('filament-file-manager::file-manager.upload_to_get_started') }}</p>
                </div>
            @endif
        </section>

        @if ($movingPath !== null)
            @php
                $moveBreadcrumbPath = '';
                $moveBreadcrumbSegments = array_values(array_filter(explode('/', $moveDestinationPath)));
                $canMoveToCurrentFolder = $this->canMoveToCurrentFolder();
            @endphp
            <div class="ufm__move-modal" role="dialog" aria-modal="true" aria-labelledby="ufm-move-title" wire:keydown.escape.window="cancelMove">
                <button type="button" class="ufm__move-backdrop" wire:click="cancelMove" aria-label="{{ __('filament-file-manager::file-manager.close_move_dialog') }}"></button>
                <section class="ufm__move-dialog">
                    <header class="ufm__move-header">
                        <div class="ufm__move-title-group">
                            <span class="ufm__move-title-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3-3m-3 3 3 3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <div>
                                <span>{{ __('filament-file-manager::file-manager.moving_item') }}</span>
                                <h2 id="ufm-move-title">{{ basename($movingPath) }}</h2>
                                <p>{{ __('filament-file-manager::file-manager.moving_item_body') }}</p>
                            </div>
                        </div>
                        <button type="button" wire:click="cancelMove" class="ufm__move-close" aria-label="{{ __('filament-file-manager::file-manager.close_move_dialog') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
                        </button>
                    </header>

                    <div class="ufm__move-toolbar">
                        <nav class="ufm__move-breadcrumb" aria-label="{{ __('filament-file-manager::file-manager.destination_folder') }}">
                            @if ($moveDestinationPath !== '')
                                <button type="button" wire:click="upMoveDestination" class="ufm__move-back" aria-label="{{ __('filament-file-manager::file-manager.go_to_parent_folder') }}" title="{{ __('filament-file-manager::file-manager.back') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m14.5 5-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            @endif
                            <button type="button" wire:click="goToMoveDestination('')">{{ __('filament-file-manager::file-manager.main_library') }}</button>
                            @foreach ($moveBreadcrumbSegments as $segment)
                                @php
                                    $moveBreadcrumbPath = $moveBreadcrumbPath === '' ? $segment : $moveBreadcrumbPath.'/'.$segment;
                                @endphp
                                <span aria-hidden="true">/</span>
                                @if ($loop->last)
                                    <span aria-current="page">{{ $segment }}</span>
                                @else
                                    <button type="button" wire:click="goToMoveDestination({{ \Illuminate\Support\Js::from($moveBreadcrumbPath) }})">{{ $segment }}</button>
                                @endif
                            @endforeach
                        </nav>
                        <label class="ufm__move-search">
                            <span class="ufm__visually-hidden">{{ __('filament-file-manager::file-manager.search_this_folder') }}</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.8" cy="10.8" r="5.8"/><path d="m16 16 4 4" stroke-linecap="round"/></svg>
                            <input type="search" wire:model.live.debounce.250ms="moveSearch" placeholder=" {{ __('filament-file-manager::file-manager.search_folder') }}" autocomplete="off" />
                        </label>
                    </div>

                    <div class="ufm__move-content">
                        @if (count($this->moveDestinationFolders) > 0)
                            <div class="ufm__move-folder-grid">
                                @foreach ($this->moveDestinationFolders as $folder)
                                    <button type="button" wire:click="openMoveDestination({{ \Illuminate\Support\Js::from($folder['path']) }})" class="ufm__move-folder">
                                        <span class="ufm__move-folder-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg></span>
                                        <span>{{ $folder['name'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="ufm__move-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg>
                                <p>{{ $moveSearch === '' ? __('filament-file-manager::file-manager.no_folders_in_location') : __('filament-file-manager::file-manager.no_folders_match_search') }}</p>
                            </div>
                        @endif
                    </div>

                    <footer class="ufm__move-footer">
                        <div class="ufm__move-destination">
                            <span class="ufm__move-destination-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z" stroke-linejoin="round"/></svg></span>
                            <div><span>{{ __('filament-file-manager::file-manager.destination') }}</span><strong>{{ $moveDestinationPath === '' ? __('filament-file-manager::file-manager.main_library') : str_replace('/', ' / ', $moveDestinationPath) }}</strong></div>
                            @unless ($canMoveToCurrentFolder)
                                <small>{{ __('filament-file-manager::file-manager.choose_different_destination') }}</small>
                            @endunless
                        </div>
                        <div class="ufm__move-footer-actions">
                            <button type="button" class="ufm__move-cancel" wire:click="cancelMove">{{ __('filament-file-manager::file-manager.cancel') }}</button>
                            <button type="button" class="ufm__move-confirm" wire:click="moveToCurrentFolder" @disabled(! $canMoveToCurrentFolder)>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h13m0 0-4-4m4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ __('filament-file-manager::file-manager.move_here') }}
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        @endif

        <div x-show="filePreview" x-cloak x-on:keydown.escape.window="filePreview = null" class="ufm__preview-modal" role="dialog" aria-modal="true" x-bind:aria-label="filePreview?.name">
            <div class="ufm__preview-backdrop" x-on:click="filePreview = null"></div>
            <div class="ufm__preview-dialog ufm__preview-dialog--details">
                <div class="ufm__preview-header ufm__preview-header--details"><div><span x-text="filePreview?.isImage ? '{{ __('filament-file-manager::file-manager.image_preview') }}' : '{{ __('filament-file-manager::file-manager.file_preview') }}'"></span><p x-text="filePreview?.name"></p></div><button type="button" x-on:click="filePreview = null" aria-label="{{ __('filament-file-manager::file-manager.close_preview') }}">×</button></div>
                <div class="ufm__preview-body">
                    <div class="ufm__preview-image">
                        <template x-if="filePreview?.isImage"><img x-bind:src="filePreview?.url" x-bind:alt="filePreview?.name" x-on:load="filePreview.width = $event.target.naturalWidth; filePreview.height = $event.target.naturalHeight" /></template>
                        <template x-if="! filePreview?.isImage"><iframe class="ufm__preview-document" x-bind:src="filePreview?.url" x-bind:title="filePreview?.name"></iframe></template>
                    </div>
                    <aside class="ufm__preview-details" style="font-family: var(--font-sans, inherit);">
                        <div class="ufm__preview-details-content">
                            <div class="ufm__preview-details-heading"><span class="ufm__preview-details-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 10v5m0-8v.01" stroke-linecap="round"/></svg></span><h3>{{ __('filament-file-manager::file-manager.file_details') }}</h3></div>
                            <dl x-bind:style="document.documentElement.classList.contains('dark') ? { backgroundColor: 'rgb(255 255 255 / .05)', borderColor: 'rgb(255 255 255 / .12)' } : {}">
                                <div><dt>{{ __('filament-file-manager::file-manager.type') }}</dt><dd x-text="filePreview?.mime || 'File'"></dd></div>
                                <div><dt>{{ __('filament-file-manager::file-manager.size') }}</dt><dd x-text="filePreview?.size ? (filePreview.size / 1024).toFixed(1) + ' KB' : '—'"></dd></div>
                                <template x-if="filePreview?.isImage"><div><dt>{{ __('filament-file-manager::file-manager.resolution') }}</dt><dd x-text="filePreview?.width && filePreview?.height ? filePreview.width + ' x ' + filePreview.height + ' px' : 'Loading...'"></dd></div></template>
                                <div><dt>{{ __('filament-file-manager::file-manager.modified') }}</dt><dd x-text="filePreview?.modified ? new Date(filePreview.modified * 1000).toLocaleDateString() : '—'"></dd></div>
                            </dl>
                            <div class="ufm__preview-copy-field">
                                <p>{{ __('filament-file-manager::file-manager.relative_path') }}</p>
                                <div class="ufm__preview-copy">
                                    <code style="font-family: var(--font-sans, inherit);" x-text="filePreview?.path"></code>
                                    <button type="button" x-on:click="copyText(filePreview?.path)">{{ __('filament-file-manager::file-manager.copy') }}</button>
                                </div>
                            </div>
                            <template x-if="filePreview?.publicUrl">
                                <div class="ufm__preview-copy-field">
                                    <p>{{ __('filament-file-manager::file-manager.public_url') }}</p>
                                    <div class="ufm__preview-copy">
                                        <code x-text="filePreview?.publicUrl"></code>
                                        <button type="button" x-on:click="copyText(filePreview?.publicUrl)">{{ __('filament-file-manager::file-manager.copy') }}</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="ufm__preview-actions" x-bind:class="{ 'has-document-actions': ! filePreview?.isImage }">
                            <button type="button" class="ufm__preview-download" x-bind:style="{ gridColumn: filePreview?.isImage ? 'auto' : '1 / -1' }" x-on:click="$wire.download(filePreview.path)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 4v10m0 0 4-4m-4 4-4-4M5 18.5h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ __('filament-file-manager::file-manager.download') }}
                            </button>
                            <template x-if="! filePreview?.isImage">
                                <a class="ufm__preview-open ufm__preview-rename" x-bind:href="filePreview?.url" target="_blank" rel="noopener noreferrer">
                                    {{ __('filament-file-manager::file-manager.open_in_new_tab') }}
                                </a>
                            </template>
                            <button type="button" class="ufm__preview-rename" x-on:click="$wire.beginRename(filePreview.path); filePreview = null">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ __('filament-file-manager::file-manager.rename') }}
                            </button>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
