<div
    class="ufm-uni-picker-upload"
    x-data="{ dragging: false, uploading: false, progress: 0, maxFiles: {{ $this->maximumUploadFiles() }} }"
>
    <div
        class="ufm-uni-picker-upload__dropzone"
        x-bind:class="{ 'is-dragging': dragging }"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false; if ($event.dataTransfer.files.length > maxFiles) { $wire.rejectTooManyFiles($event.dataTransfer.files.length); return; } $refs.uniPickerUploadInput.files = $event.dataTransfer.files; $refs.uniPickerUploadInput.dispatchEvent(new Event('change', { bubbles: true }))"
    >
        <input x-ref="uniPickerUploadInput" type="file" wire:model="uploads" accept="{{ $this->acceptedFileTypes() }}" @if ($multiple) multiple @endif x-on:livewire-upload-start="uploading = true; progress = 0" x-on:livewire-upload-progress="progress = $event.detail.progress" x-on:livewire-upload-finish="uploading = false; progress = 100" x-on:livewire-upload-error="uploading = false" x-on:change.capture="if ($event.target.files.length > maxFiles) { $event.stopImmediatePropagation(); $wire.rejectTooManyFiles($event.target.files.length); $event.target.value = ''; }" class="ufm__visually-hidden" />
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 18.5h10a4 4 0 0 0 .77-7.93A5.8 5.8 0 0 0 6.7 9.2 4.7 4.7 0 0 0 7 18.5Z" stroke-linecap="round"/><path d="m12 15.5 0-7m0 0-2.5 2.5M12 8.5l2.5 2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <strong>{{ $heading ?? ('Drop '.($multiple ? 'files' : 'a file').' here'.($hasLibrary ? ' or Choose from Library' : '')) }}</strong>
        <span>{{ $description ?? ($hasLibrary ? 'Drag files here, or open the library to upload and select files.' : 'Drag files here to upload automatically.') }}</span>
        @if ($hasLibrary)<button type="button" wire:click="openLibrary">Choose from Library</button>@endif
    </div>
    <div x-show="uploading" x-cloak class="ufm-uni-picker-upload__progress"><span>Uploading…</span><i><b x-bind:style="'width: ' + progress + '%' "></b></i></div>
    @error('uploads') <p class="ufm-uni-picker-upload__error">{{ $message }}</p> @enderror
</div>
