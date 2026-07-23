<div
    class="ufm-picker-explorer"
    x-data="{ optionsOpen: false, uploadModal: false, uploadDragging: false, uploading: false, uploadProgress: 0, maxUploadFiles: {{ $this->maximumUploadFiles() }} }"
>
    <div class="ufm-picker-explorer__toolbar">
        <div class="ufm-picker-explorer__location">
            @if ($path !== $directory)
                <button type="button" wire:click="up" aria-label="Go to parent folder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            @endif
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z" stroke-linejoin="round"/></svg>
            <span>{{ $path === '' ? 'Main Library' : $path }}</span>
        </div>

        <div class="ufm-picker-explorer__toolbar-actions">
            <label class="ufm-picker-explorer__search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4" stroke-linecap="round"/></svg>
                <input wire:model.live.debounce.200ms="search" type="search" placeholder="Search this folder" />
            </label>
            <div class="ufm-picker-explorer__options">
                <button type="button" class="ufm-picker-explorer__icon-button" x-on:click="optionsOpen = ! optionsOpen" x-bind:aria-expanded="optionsOpen" aria-label="{{ $this->canChooseItemLayout() ? 'Item layout and sort options' : 'Sort options' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h10M4 12h16M4 17h7" stroke-linecap="round"/><path d="m16 5 2 2-2 2M12 15l2 2-2 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div x-show="optionsOpen" x-cloak x-on:click.outside="optionsOpen = false" class="ufm-picker-explorer__options-menu">
                    @if ($this->canChooseItemLayout())
                        <p>Item layout</p>
                        <button type="button" wire:click="setDisplayMode('separate')" aria-pressed="{{ $displayMode === 'separate' ? 'true' : 'false' }}" @class(['is-active' => $displayMode === 'separate'])>Folders first</button>
                        <button type="button" wire:click="setDisplayMode('all')" aria-pressed="{{ $displayMode === 'all' ? 'true' : 'false' }}" @class(['is-active' => $displayMode === 'all'])>All items together</button>
                        <hr />
                    @endif
                    <p>Sort by</p>
                    <select wire:model.live="sortBy" aria-label="Sort picker items"><option value="name">Name</option><option value="modified_at">Last modified</option><option value="type">Type</option></select>
                    <button type="button" wire:click="toggleSortDirection">{{ $sortDirection === 'asc' ? 'Ascending' : 'Descending' }}</button>
                </div>
            </div>
            <button type="button" class="ufm-picker-explorer__upload-button" x-on:click="uploadModal = true" @disabled(! $this->canUpload()) title="{{ $this->canUpload() ? 'Upload files' : 'Use or remove selected files before uploading more' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 16V4m0 0L8 8m4-4 4 4M5 20h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Upload
            </button>
        </div>
    </div>

    @if ($displayMode === 'separate')
        <div class="ufm-picker-explorer__content">
            <section class="ufm-picker-explorer__section">
                <h3>Folders</h3>
                @if ($this->paginatedFolders->total() > 0)
                    <div class="ufm-picker-explorer__grid">
                        @foreach ($this->paginatedFolders->items() as $item)
                            @include('filament-file-manager::livewire.partials.picker-item', ['item' => $item])
                        @endforeach
                    </div>
                    @include('filament-file-manager::components.pagination', ['pagination' => $this->paginatedFolders, 'pageName' => 'pickerFoldersPage'])
                @else
                    <p class="ufm-picker-explorer__empty-row">This folder has no subfolders.</p>
                @endif
            </section>

            <section class="ufm-picker-explorer__section">
                <h3>Files</h3>
                @if ($this->paginatedFiles->total() > 0)
                    <div class="ufm-picker-explorer__grid">
                        @foreach ($this->paginatedFiles->items() as $item)
                            @include('filament-file-manager::livewire.partials.picker-item', ['item' => $item])
                        @endforeach
                    </div>
                    @include('filament-file-manager::components.pagination', ['pagination' => $this->paginatedFiles, 'pageName' => 'pickerFilesPage'])
                @else
                    <p class="ufm-picker-explorer__empty-row">{{ $search === '' ? 'This folder has no files.' : 'No files match your search.' }}</p>
                @endif
            </section>
        </div>
    @elseif ($this->paginatedItems->total() > 0)
        <div class="ufm-picker-explorer__content">
            <div class="ufm-picker-explorer__grid">
                @foreach ($this->paginatedItems->items() as $item)
                    @include('filament-file-manager::livewire.partials.picker-item', ['item' => $item])
                @endforeach
            </div>
            @include('filament-file-manager::components.pagination', ['pagination' => $this->paginatedItems, 'pageName' => 'pickerItemsPage'])
        </div>
    @else
        <div class="ufm-picker-explorer__empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg>
            <strong>{{ $search === '' ? 'This folder is empty' : 'No files found' }}</strong>
            <span>{{ $search === '' ? 'Upload a file or open another folder.' : 'Try a different search term.' }}</span>
        </div>
    @endif

    @if ($multiple)
        <footer class="ufm-picker-explorer__selection-footer">
            <div><strong>{{ count($selectedPaths) }} of {{ $maxFiles }} selected</strong>@if ($selectionMessage)<span>{{ $selectionMessage }}</span>@endif</div>
            <button type="button" wire:click="confirmSelection" @disabled($selectedPaths === [])>Use selected {{ count($selectedPaths) === 1 ? 'file' : 'files' }}</button>
        </footer>
    @endif

    <div x-show="uploadModal" x-cloak x-on:keydown.escape.window="uploadModal = false" class="ufm-picker-upload" role="dialog" aria-modal="true" aria-label="Upload files">
        <div class="ufm-picker-upload__backdrop" x-on:click="uploadModal = false"></div>
        <div class="ufm-picker-upload__dialog">
            <div class="ufm-picker-upload__header"><div><strong>Upload files</strong><span>Choose or drop up to {{ $this->maximumUploadFiles() }} files. Uploads start automatically.</span></div><button type="button" x-on:click="uploadModal = false" aria-label="Close upload dialog">×</button></div>
            <label class="ufm-picker-upload__dropzone" x-bind:class="{ 'is-dragging': uploadDragging }" x-on:dragover.prevent="uploadDragging = true" x-on:dragleave.prevent="uploadDragging = false" x-on:drop.prevent="uploadDragging = false; if ($event.dataTransfer.files.length > maxUploadFiles) { $wire.rejectTooManyFiles($event.dataTransfer.files.length); return; } $refs.pickerUploadInput.files = $event.dataTransfer.files; $refs.pickerUploadInput.dispatchEvent(new Event('change', { bubbles: true }))">
                <input x-ref="pickerUploadInput" type="file" wire:model="uploads" accept="{{ $this->acceptedFileTypes() }}" multiple @disabled(! $this->canUpload()) x-on:livewire-upload-start="uploading = true; uploadProgress = 0" x-on:livewire-upload-progress="uploadProgress = $event.detail.progress" x-on:livewire-upload-finish="uploading = false; uploadProgress = 100" x-on:livewire-upload-error="uploading = false" x-on:change.capture="if ($event.target.files.length > maxUploadFiles) { $event.stopImmediatePropagation(); $wire.rejectTooManyFiles($event.target.files.length); $event.target.value = ''; }" class="ufm__visually-hidden" />
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 18.5h10a4 4 0 0 0 .77-7.93A5.8 5.8 0 0 0 6.7 9.2 4.7 4.7 0 0 0 7 18.5Z" stroke-linecap="round"/><path d="m12 15.5 0-7m0 0-2.5 2.5M12 8.5l2.5 2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <strong>Choose files or drag &amp; drop them here</strong>
                <span>Uploads start automatically · up to {{ round(config('filament-file-manager.max_upload_size') / 1024) }} MB each</span>
                <em>Browse files</em>
            </label>
            @error('uploads') <p class="ufm-picker-upload__error">{{ $message }}</p> @enderror
            <div x-show="uploading" x-cloak class="ufm-picker-upload__progress"><div><span>Uploading files…</span><span x-text="uploadProgress + '%'">0%</span></div><i><b x-bind:style="'width: ' + uploadProgress + '%' "></b></i></div>
            @if ($uploadMessage)<p class="ufm-picker-upload__success">{{ $uploadMessage }}</p>@endif
            @if ($uploadedPreviewFiles !== [])
                <section class="ufm-picker-upload__review" aria-label="Uploaded files">
                    <div class="ufm-picker-upload__review-heading">
                        <strong>{{ count($uploadedPreviewFiles) === 1 ? 'Uploaded file' : 'Uploaded files' }}</strong>
                        <button type="button" wire:click="mountAction('deleteAllUploadedPreviews')" class="ufm-picker-upload__delete-all">Delete all</button>
                    </div>
                    <div class="ufm-picker-upload__preview-grid">
                        @foreach ($uploadedPreviewFiles as $uploadedFile)
                            <div class="ufm-picker-upload__preview-card">
                                @if ($uploadedFile['is_image'])
                                    <img src="{{ $this->thumbnailUrl($uploadedFile['path']) }}" alt="" />
                                @else
                                    @php($documentKind = $this->documentKind($uploadedFile))
                                    <span class="ufm__document-icon ufm__document-icon--{{ $documentKind }}"><span>{{ strtoupper($documentKind === 'word' ? 'doc' : $documentKind) }}</span></span>
                                @endif
                                <span title="{{ $uploadedFile['name'] }}">{{ $uploadedFile['name'] }}</span>
                                <button type="button" wire:click="mountAction('deleteUploadedPreview', { path: {{ \Illuminate\Support\Js::from($uploadedFile['path']) }} })" class="ufm-picker-upload__remove" aria-label="Delete {{ $uploadedFile['name'] }}" title="Delete uploaded file">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v5m4-5v5M9 7l1-3h4l1 3m-9 0 1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
    <x-filament-actions::modals />
</div>
