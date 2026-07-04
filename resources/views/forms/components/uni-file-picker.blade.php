@php
    $pickerId = 'unifile-manager-picker-'.md5($getStatePath());
    $state = $getState();
    $paths = $field->isMultiple()
        ? array_values(array_filter(is_array($state) ? $state : [], 'is_string'))
        : (is_string($state) && $state !== '' ? [$state] : []);
    $maxFiles = $field->getMaxFiles();
    $remainingUploadSlots = $field->isMultiple() ? max(0, $maxFiles - count($paths)) : ($paths === [] ? 1 : 0);
    $pickerHelperText = $field->getPickerHelperText();
    $hasLibrary = $field->hasLibrary();
    $managerUrl = $field->getManagerUrl();
    $directory = $field->getDirectory();
    $storageArea = $field->getStorageArea();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="ufm-picker ufm-uni-picker"
        @if ($managerUrl !== null) data-file-manager-url="{{ $managerUrl }}" @endif
        x-data="{
            value: $wire.entangle('{{ $getStatePath() }}'),
            pickerOpen: false,
            preview: null,
            multiple: {{ $field->isMultiple() ? 'true' : 'false' }},
            allowDuplicateSelection: {{ $field->allowsDuplicateSelection() ? 'true' : 'false' }},
            maxFiles: {{ $maxFiles }},
            sync() {
                $wire.set('{{ $getStatePath() }}', this.value);
            },
            choose(paths) {
                const cleanPaths = paths.filter((path) => typeof path === 'string' && path.length > 0);
                if (this.multiple) {
                    const current = Array.isArray(this.value) ? this.value : [];
                    const paths = this.allowDuplicateSelection
                        ? [...current, ...cleanPaths]
                        : [...new Set([...current, ...cleanPaths])];
                    this.value = paths.slice(0, this.maxFiles);
                } else {
                    this.value = cleanPaths[0] ?? null;
                }
                this.sync();
                this.pickerOpen = false;
            },
            remove(path, index = null) {
                this.value = this.multiple
                    ? (Array.isArray(this.value) ? this.value.filter((item, itemIndex) => itemIndex !== index) : [])
                    : null;
                this.sync();
            },
            clear() {
                this.value = this.multiple ? [] : null;
                this.sync();
            }
        }"
        x-on:unifile-manager:selected.window="if ($event.detail.pickerId === '{{ $pickerId }}') choose($event.detail.paths ?? [$event.detail.path])"
        x-on:unifile-manager:uploaded.window="if ($event.detail.pickerId === '{{ $pickerId }}') choose($event.detail.paths ?? [])"
        x-on:unifile-manager:open-library.window="if ($event.detail.pickerId === '{{ $pickerId }}') pickerOpen = true"
    >
        @if ($remainingUploadSlots > 0)
            <livewire:unifile-manager.uni-file-picker-uploader :picker-id="$pickerId" :multiple="$field->isMultiple()" :max-files="$remainingUploadSlots" :directory="$directory" :storage-area="$storageArea" :allowed-mime-types="$field->getAllowedMimeTypes()" :has-library="$hasLibrary" :heading="$field->getUploadHeading()" :description="$field->getUploadDescription()" :key="$pickerId.'-uploader-'.count($paths)" />

            @if (filled($pickerHelperText))
                <p class="ufm-uni-picker__helper-text">{{ $pickerHelperText }}</p>
            @endif
        @endif

        @if ($paths !== [])
            <div class="ufm-uni-picker__selected" @class([
                'is-single' => ! $field->isMultiple(),
                'is-list' => $field->isMultiple() && ! $field->isImageCardView(),
            ])>
                @foreach ($paths as $path)
                    <article class="ufm-uni-picker__card">
                        @if ($field->isImagePath($path))
                            <button type="button" class="ufm-uni-picker__preview" x-on:click="preview = { url: {{ \Illuminate\Support\Js::from($field->previewUrl($path)) }}, name: {{ \Illuminate\Support\Js::from(basename($path)) }} }" aria-label="Preview {{ basename($path) }}">
                                <img src="{{ $field->thumbnailUrl($path) }}" alt="" loading="lazy" />
                            </button>
                        @else
                            <span class="ufm-uni-picker__preview"><span class="ufm__document-icon ufm__document-icon--{{ $field->documentKind($path) }}"><span>{{ strtoupper($field->documentKind($path) === 'word' ? 'doc' : $field->documentKind($path)) }}</span></span></span>
                        @endif
                        <span class="ufm-uni-picker__name">{{ basename($path) }}</span>
                        <button type="button" class="ufm-uni-picker__remove" x-on:click="remove({{ \Illuminate\Support\Js::from($path) }}, {{ $loop->index }})" aria-label="Remove {{ basename($path) }}" title="Remove file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>
                        </button>
                    </article>
                @endforeach
            </div>
        @endif

        @if (! $isDisabled() && $isClearable() && $field->isMultiple() && $paths !== [])
            <button type="button" class="ufm-uni-picker__clear" x-on:click="clear()">Clear selection</button>
        @endif

        @if ($hasLibrary)
            <div x-show="pickerOpen" x-cloak x-on:keydown.escape.window="pickerOpen = false" class="ufm-picker__modal" role="dialog" aria-modal="true" aria-label="Choose a file">
                <div class="ufm-picker__backdrop" x-on:click="pickerOpen = false"></div>
                <div class="ufm-picker__dialog">
                    <div class="ufm-picker__header"><div><strong>Choose {{ $field->isMultiple() ? 'files' : 'a file' }}</strong><span>Select {{ $field->isMultiple() ? 'up to '.$remainingUploadSlots.' files' : 'a file' }} from your library.</span></div><button type="button" x-on:click="pickerOpen = false" aria-label="Close file picker">×</button></div>
                    <livewire:unifile-manager.file-picker-explorer :picker-id="$pickerId" :multiple="$field->isMultiple()" :max-files="$remainingUploadSlots" :directory="$directory" :storage-area="$storageArea" :allowed-mime-types="$field->getAllowedMimeTypes()" :key="$pickerId.'-explorer-'.count($paths)" />
                </div>
            </div>
        @endif

        <div x-show="preview" x-cloak x-on:keydown.escape.window="preview = null" class="ufm-uni-picker__preview-modal" role="dialog" aria-modal="true" x-bind:aria-label="preview?.name">
            <div class="ufm-picker__backdrop" x-on:click="preview = null"></div>
            <div class="ufm-uni-picker__preview-dialog"><button type="button" x-on:click="preview = null" aria-label="Close preview">×</button><img x-bind:src="preview?.url" x-bind:alt="preview?.name" /></div>
        </div>
    </div>
</x-dynamic-component>
