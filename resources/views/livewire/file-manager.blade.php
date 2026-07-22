<x-filament-panels::page
    class="ufm"
    x-data="{ optionsOpen: false, storageOpen: false, imagePreview: null, uploadModal: false, recentlyUploaded: [], uploadHighlightTimer: null, uploadDragging: false, uploading: false, uploadProgress: 0, maxUploadFiles: {{ $this->maximumUploadFiles() }}, copyText(value) { if (! value) return; if (navigator.clipboard?.writeText) { navigator.clipboard.writeText(value); return; } const textarea = document.createElement('textarea'); textarea.value = value; textarea.style.position = 'fixed'; textarea.style.opacity = '0'; document.body.appendChild(textarea); textarea.select(); document.execCommand('copy'); textarea.remove(); } }"
    x-on:unifile-manager:selected.window="localStorage.setItem('unifile-manager:selected', JSON.stringify({ path: $event.detail.path, nonce: Date.now() }))"
    x-on:file-manager:focus-rename.window="$nextTick(() => { $refs.renameItem?.focus(); $refs.renameItem?.select(); })"
    x-on:file-manager:uploaded.window="recentlyUploaded = $event.detail.paths ?? []; clearTimeout(uploadHighlightTimer); uploadHighlightTimer = setTimeout(() => recentlyUploaded = [], 4000)"
>
    <div class="ufm ufm__layout">
        <section class="ufm__hero">
            <div class="ufm__hero-main">
                <div>
                    <p class="ufm__eyebrow">File library</p>
                    <div class="ufm__title-row">
                        <h1 class="ufm__title">{{ $path === '' ? 'Main Library' : basename($path) }}</h1>
                    </div>
                    <nav class="ufm__breadcrumb" aria-label="Current folder">
                        @if ($path !== '')
                            <button type="button" wire:click="up" class="ufm__breadcrumb-back" aria-label="Go to parent folder" title="Back">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14.5 5-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <span class="ufm__breadcrumb-separator" aria-hidden="true">/</span>
                        @endif
                        <button type="button" wire:click="open('')" aria-label="Open main library">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 10 8-6 8 6v9a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9Z" stroke-linejoin="round"/></svg>
                            <span>Main Library</span>
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
                            <span class="ufm__storage-indicator-label">Storage</span>
                            <span>{{ $this->availableStorageAreas()[$storageArea] }}</span>
                        </span>
                    @endif
                    <button type="button" x-on:click="uploadModal = true" class="ufm__button ufm__button--primary">Upload files</button>
                </div>
            </div>
        </section>

        <div x-show="uploadModal" x-cloak x-on:keydown.escape.window="$wire.closeUploadModal(); uploadModal = false" class="ufm__upload-modal" role="dialog" aria-modal="true" aria-label="Upload files">
            <div class="ufm__preview-backdrop" x-on:click="$wire.closeUploadModal(); uploadModal = false"></div>
                <div class="ufm__upload-dialog">
                <div class="ufm__preview-header">
                    <div><p>Upload files</p><span>Choose or drop up to {{ $this->maximumUploadFiles() }} files. Uploads start automatically.</span></div>
                    <button type="button" x-on:click="$wire.closeUploadModal(); uploadModal = false" aria-label="Close upload dialog">×</button>
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
                    <span class="ufm__upload-dropzone-title">Choose files or drag &amp; drop them here</span>
                    <span class="ufm__upload-dropzone-help">Uploads start automatically. {{ strtoupper(implode(', ', array_slice(config('filament-file-manager.allowed_extensions'), 0, 5))) }} and more · up to {{ round(config('filament-file-manager.max_upload_size') / 1024) }} MB each</span>
                    <span class="ufm__upload-browse">Browse files</span>
                </label>

                @error('uploads') <p class="ufm__error">{{ $message }}</p> @enderror
                <div x-show="uploading" x-cloak class="ufm__upload-progress" aria-live="polite">
                    <div class="ufm__upload-progress-label"><span>Uploading files…</span><span x-text="uploadProgress + '%'">0%</span></div>
                    <div class="ufm__upload-progress-track"><span x-bind:style="'width: ' + uploadProgress + '%' "></span></div>
                </div>

                @if (count($uploadedPreviewFiles) > 0)
                    <div class="ufm__upload-review">
                        <div class="ufm__upload-review-title">
                            <strong>{{ count($uploadedPreviewFiles) === 1 ? 'Uploaded file' : 'Uploaded files' }}</strong>
                            <x-filament::button color="danger" size="sm" wire:click="mountAction('deleteAllUploadedPreviews')">Delete all</x-filament::button>
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
                                    <button type="button" wire:click="mountAction('deleteUploadedPreview', { path: {{ \Illuminate\Support\Js::from($uploadedFile['path']) }} })" class="ufm__upload-remove ufm__upload-remove--danger" aria-label="Delete {{ $uploadedFile['name'] }}" title="Delete file">
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
                <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search this folder" />
            </label>
            <p class="ufm__item-count">{{ count($this->filteredItems) }} item{{ count($this->filteredItems) === 1 ? '' : 's' }}</p>
        </section>

        @if (count($selectedPaths) > 0)
            <div class="ufm__selection-toolbar">
                <span>{{ count($selectedPaths) }} selected</span>
                <button type="button" class="ufm__button ufm__button--danger" wire:click="mountAction('deleteSelectedItems')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3m-9 0 1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Delete selected
                </button>
                <button type="button" class="ufm__button ufm__button--ghost" wire:click="clearSelection">Clear</button>
            </div>
        @endif

        @if ($displayMode === 'separate')
        <section class="ufm__section">
            <div class="ufm__section-heading">
                <div>
                    <h2>Folders</h2>
                    <p>Keep related files organised in one place.</p>
                </div>
                <div class="ufm__library-actions">
                    <div class="ufm__options">
                        <button type="button" x-on:click="optionsOpen = ! optionsOpen" class="ufm__icon-button" aria-label="{{ $this->canChooseItemLayout() ? 'Item layout and sort options' : 'Sort options' }}" x-bind:aria-expanded="optionsOpen">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h10M4 12h16M4 17h7" stroke-linecap="round"/><path d="m16 5 2 2-2 2M12 15l2 2-2 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div x-show="optionsOpen" x-cloak x-on:click.outside="optionsOpen = false" class="ufm__options-menu">
                            @if ($this->canChooseItemLayout())
                                <p class="ufm__options-label">Item layout</p>
                                <button type="button" wire:click="setDisplayMode('separate')" aria-pressed="{{ $displayMode === 'separate' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'separate'])>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                    Folders first
                                </button>
                                <button type="button" wire:click="setDisplayMode('all')" aria-pressed="{{ $displayMode === 'all' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'all'])>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 6h14M5 12h14M5 18h14" stroke-linecap="round"/></svg>
                                    All items together
                                </button>
                                <div class="ufm__options-divider"></div>
                            @endif
                            <p class="ufm__options-label">Sort by</p>
                            <select wire:model.live="sortBy" class="ufm__sort-select" aria-label="Sort files and folders">
                                <option value="name">Name</option>
                                <option value="modified_at">Last modified</option>
                                <option value="type">Type</option>
                            </select>
                            <button type="button" wire:click="toggleSortDirection" class="ufm__option">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 4v16m0-16-3 3m3-3 3 3M16 20V4m0 16-3-3m3 3 3-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ $sortDirection === 'asc' ? 'Ascending' : 'Descending' }}
                            </button>
                        </div>
                    </div>
                    <button type="button" wire:click="createNewDirectory" class="ufm__text-button">+ New folder</button>
                </div>
            </div>

            <div class="ufm__folder-grid">
                @foreach ($this->paginatedFolders->items() as $folder)
                    @if ($renamingPath === $folder['path'])
                        <form wire:submit="saveRename" class="ufm__folder-card ufm__folder-card--renaming">
                            <span class="ufm__folder-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg></span>
                            <label class="ufm__rename-field">
                                <span class="ufm__visually-hidden">Folder name</span>
                                <input x-ref="renameItem" wire:model="renamingName" wire:keydown.escape="cancelRename" type="text" maxlength="100" />
                                @error('renamingName') <span class="ufm__error">{{ $message }}</span> @enderror
                            </label>
                            <div class="ufm__rename-actions">
                                <button type="submit" class="ufm__rename-action ufm__rename-action--save" aria-label="Save folder name" title="Save name">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button type="button" wire:click="cancelRename" class="ufm__rename-action ufm__rename-action--cancel" aria-label="Cancel folder rename" title="Cancel rename">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                        </form>
                    @else
                        <article class="ufm__folder-card-wrap" @class(['ufm__folder-card-wrap--selected' => in_array($folder['path'], $selectedPaths, true)])>
                            @if (in_array($folder['path'], $selectedPaths, true))
                                <span class="ufm-picker-explorer__selection-check ufm__selection-check" aria-label="Selected"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            @endif
                            <button type="button" wire:click="open({{ \Illuminate\Support\Js::from($folder['path']) }})" class="ufm__folder-card">
                                <span class="ufm__folder-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg></span>
                                <span class="ufm__folder-copy">
                                    <span class="ufm__folder-name">{{ $folder['name'] }}</span>
                                    <span class="ufm__folder-meta">Folder</span>
                                </span>
                                <span class="ufm__folder-arrow">→</span>
                            </button>
                            <div class="ufm__folder-actions">
                                <button type="button" class="ufm__file-action" wire:click="beginRename({{ \Illuminate\Support\Js::from($folder['path']) }})" aria-label="Rename folder" title="Rename folder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button type="button" @class(['ufm__file-action', 'is-selected' => in_array($folder['path'], $selectedPaths, true)]) wire:click="toggleSelection({{ \Illuminate\Support\Js::from($folder['path']) }})" aria-pressed="{{ in_array($folder['path'], $selectedPaths, true) ? 'true' : 'false' }}" aria-label="{{ in_array($folder['path'], $selectedPaths, true) ? 'Deselect' : 'Select' }} folder" title="{{ in_array($folder['path'], $selectedPaths, true) ? 'Deselect folder' : 'Select folder' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <button type="button" class="ufm__file-action ufm__file-action--danger" wire:click="mountAction('deleteItem', { path: {{ \Illuminate\Support\Js::from($folder['path']) }} })" aria-label="Delete folder" title="Delete folder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3m-9 0 1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </article>
                    @endif
                @endforeach

                <button type="button" wire:click="createNewDirectory" class="ufm__new-folder-card"><span>+</span> Add new folder</button>
            </div>
            @include('filament-file-manager::components.pagination', ['pagination' => $this->paginatedFolders, 'pageName' => 'foldersPage'])
        </section>
        @endif

        <section class="ufm__section">
            <div class="ufm__section-heading">
                <div>
                    <h2>{{ $displayMode === 'separate' ? 'Files' : 'Items' }}</h2>
                    <p>{{ $displayMode === 'separate' ? 'Select a file to use it with a File Picker field.' : 'Folders and files in this directory.' }}</p>
                </div>
                @if ($displayMode === 'all')
                    <div class="ufm__library-actions">
                        <div class="ufm__options">
                            <button type="button" x-on:click="optionsOpen = ! optionsOpen" class="ufm__icon-button" aria-label="{{ $this->canChooseItemLayout() ? 'Item layout and sort options' : 'Sort options' }}" x-bind:aria-expanded="optionsOpen">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h10M4 12h16M4 17h7" stroke-linecap="round"/><path d="m16 5 2 2-2 2M12 15l2 2-2 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div x-show="optionsOpen" x-cloak x-on:click.outside="optionsOpen = false" class="ufm__options-menu">
                                @if ($this->canChooseItemLayout())
                                    <p class="ufm__options-label">Item layout</p>
                                    <button type="button" wire:click="setDisplayMode('separate')" aria-pressed="{{ $displayMode === 'separate' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'separate'])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Folders first</button>
                                    <button type="button" wire:click="setDisplayMode('all')" aria-pressed="{{ $displayMode === 'all' ? 'true' : 'false' }}" @class(['ufm__option', 'is-active' => $displayMode === 'all'])><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 6h14M5 12h14M5 18h14" stroke-linecap="round"/></svg>All items together</button>
                                    <div class="ufm__options-divider"></div>
                                @endif
                                <p class="ufm__options-label">Sort by</p>
                                <select wire:model.live="sortBy" class="ufm__sort-select" aria-label="Sort files and folders"><option value="name">Name</option><option value="modified_at">Last modified</option><option value="type">Type</option></select>
                                <button type="button" wire:click="toggleSortDirection" class="ufm__option"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 4v16m0-16-3 3m3-3 3 3M16 20V4m0 16-3-3m3 3 3-3" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $sortDirection === 'asc' ? 'Ascending' : 'Descending' }}</button>
                            </div>
                        </div>
                        <button type="button" wire:click="createNewDirectory" class="ufm__text-button">+ New folder</button>
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
                                <div class="ufm__file-actions" aria-label="Rename actions">
                                    <button type="submit" class="ufm__rename-action ufm__rename-action--save" aria-label="Save file name" title="Save name">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" wire:click="cancelRename" class="ufm__rename-action ufm__rename-action--cancel" aria-label="Cancel file rename" title="Cancel rename">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>
                                    </button>
                                </div>
                            </form>
                        @else
                        <article class="ufm__file-card" x-bind:class="{ 'ufm__file-card--new': recentlyUploaded.includes({{ \Illuminate\Support\Js::from($file['path']) }}), 'ufm__file-card--selected': {{ in_array($file['path'], $selectedPaths, true) ? 'true' : 'false' }} }">
                            @if (in_array($file['path'], $selectedPaths, true))
                                <span class="ufm-picker-explorer__selection-check ufm__selection-check" aria-label="Selected"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            @endif
                            <button
                                type="button"
                                @if ($file['type'] === 'directory') wire:click="open({{ \Illuminate\Support\Js::from($file['path']) }})" @elseif ($this->isImage($file)) x-on:click="imagePreview = {{ \Illuminate\Support\Js::from(['url' => $this->previewUrl($file['path']), 'name' => $file['name'], 'path' => $file['path'], 'mime' => $file['mime_type'] ?? null, 'size' => $file['size'] ?? null, 'modified' => $file['modified_at'] ?? null, 'publicUrl' => $this->publicUrl($file['path'])]) }}" @else wire:click="select({{ \Illuminate\Support\Js::from($file['path']) }})" @endif
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
                                <span class="ufm__file-size">{{ $file['type'] === 'directory' ? 'Folder' : number_format(($file['size'] ?? 0) / 1024, 1).' KB' }}</span>
                            </button>
                            <div class="ufm__file-actions">
                                @if ($file['type'] === 'file')
                                    <button type="button" class="ufm__file-action" wire:click="beginRename({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="Rename file" title="Rename file">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    @if ($this->isImage($file))
                                        <button type="button" class="ufm__file-action" x-on:click="imagePreview = {{ \Illuminate\Support\Js::from(['url' => $this->previewUrl($file['path']), 'name' => $file['name'], 'path' => $file['path'], 'mime' => $file['mime_type'] ?? null, 'size' => $file['size'] ?? null, 'modified' => $file['modified_at'] ?? null, 'publicUrl' => $this->publicUrl($file['path'])]) }}" aria-label="Preview image" title="Preview image">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                        </button>
                                    @endif
                                    <button type="button" @class(['ufm__file-action', 'is-selected' => in_array($file['path'], $selectedPaths, true)]) wire:click="toggleSelection({{ \Illuminate\Support\Js::from($file['path']) }})" aria-pressed="{{ in_array($file['path'], $selectedPaths, true) ? 'true' : 'false' }}" aria-label="{{ in_array($file['path'], $selectedPaths, true) ? 'Deselect' : 'Select' }} file" title="{{ in_array($file['path'], $selectedPaths, true) ? 'Deselect file' : 'Select file' }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" class="ufm__file-action" wire:click="download({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="Download file" title="Download file">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 20h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                @else
                                    <button type="button" class="ufm__file-action" wire:click="beginRename({{ \Illuminate\Support\Js::from($file['path']) }})" aria-label="Rename folder" title="Rename folder">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" @class(['ufm__file-action', 'is-selected' => in_array($file['path'], $selectedPaths, true)]) wire:click="toggleSelection({{ \Illuminate\Support\Js::from($file['path']) }})" aria-pressed="{{ in_array($file['path'], $selectedPaths, true) ? 'true' : 'false' }}" aria-label="{{ in_array($file['path'], $selectedPaths, true) ? 'Deselect' : 'Select' }} folder" title="{{ in_array($file['path'], $selectedPaths, true) ? 'Deselect folder' : 'Select folder' }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                @endif
                                <button type="button" class="ufm__file-action ufm__file-action--danger" wire:click="mountAction('deleteItem', { path: {{ \Illuminate\Support\Js::from($file['path']) }} })" aria-label="Delete {{ $file['type'] }}" title="Delete {{ $file['type'] }}">
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
                    <h3>This folder is empty</h3>
                    <p>Upload a permitted file or create a folder to get started.</p>
                </div>
            @endif
        </section>

        <div x-show="imagePreview" x-cloak x-on:keydown.escape.window="imagePreview = null" class="ufm__preview-modal" role="dialog" aria-modal="true" x-bind:aria-label="imagePreview?.name">
            <div class="ufm__preview-backdrop" x-on:click="imagePreview = null"></div>
            <div class="ufm__preview-dialog ufm__preview-dialog--details">
                <div class="ufm__preview-header"><p x-text="imagePreview?.name"></p><button type="button" x-on:click="imagePreview = null" aria-label="Close preview">×</button></div>
                <div class="ufm__preview-body">
                    <div class="ufm__preview-image"><img x-bind:src="imagePreview?.url" x-bind:alt="imagePreview?.name" /></div>
                    <aside class="ufm__preview-details">
                        <h3>File details</h3>
                        <dl><dt>Type</dt><dd x-text="imagePreview?.mime || 'Image'"></dd><dt>Size</dt><dd x-text="imagePreview?.size ? (imagePreview.size / 1024).toFixed(1) + ' KB' : '—'"></dd><dt>Modified</dt><dd x-text="imagePreview?.modified ? new Date(imagePreview.modified * 1000).toLocaleDateString() : '—'"></dd></dl>
                        <p>Relative path</p><div class="ufm__preview-copy"><code x-text="imagePreview?.path"></code><button type="button" x-on:click="copyText(imagePreview?.path)">Copy</button></div>
                        <template x-if="imagePreview?.publicUrl"><div><p>Public URL</p><div class="ufm__preview-copy"><code x-text="imagePreview?.publicUrl"></code><button type="button" x-on:click="copyText(imagePreview?.publicUrl)">Copy</button></div></div></template>
                        <button type="button" class="ufm__preview-download" x-on:click="$wire.download(imagePreview.path)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 4v10m0 0 4-4m-4 4-4-4M5 18.5h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Download file
                        </button>
                        <button type="button" class="ufm__preview-rename" x-on:click="$wire.beginRename(imagePreview.path); imagePreview = null">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 6.5 17.5 10.5M4 20l3.5-.8L19 7.7a1.8 1.8 0 0 0 0-2.5l-.2-.2a1.8 1.8 0 0 0-2.5 0L4.8 16.5 4 20Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Rename file
                        </button>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
