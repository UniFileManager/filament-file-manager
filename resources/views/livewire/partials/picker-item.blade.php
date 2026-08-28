@if ($item['type'] === 'directory')
    <button wire:key="picker-{{ $item['path'] }}" type="button" class="ufm-picker-explorer__item" wire:click="open({{ \Illuminate\Support\Js::from($item['path']) }})">
        <span class="ufm-picker-explorer__thumbnail ufm-picker-explorer__thumbnail--folder"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.75A2.75 2.75 0 0 1 5.75 4h4.14c.73 0 1.42.34 1.86.92l.81 1.08h5.69A2.75 2.75 0 0 1 21 8.75v8.5A2.75 2.75 0 0 1 18.25 20h-12A3.25 3.25 0 0 1 3 16.75v-10Z"/></svg></span>
        <span class="ufm-picker-explorer__name">{{ $item['name'] }}</span>
        <span class="ufm-picker-explorer__meta">{{ __('filament-file-manager::file-manager.folder') }}</span>
    </button>
@else
    <button wire:key="picker-{{ $item['path'] }}" type="button" class="ufm-picker-explorer__item" wire:click="select({{ \Illuminate\Support\Js::from($item['path']) }})" @class(['is-selected' => $multiple && $this->isSelected($item['path'])]) @disabled($multiple && ! $this->isSelected($item['path']) && $this->selectionLimitReached()) aria-pressed="{{ $multiple && $this->isSelected($item['path']) ? 'true' : 'false' }}">
        @if ($multiple && $this->isSelected($item['path']))
            <span class="ufm-picker-explorer__selection-check" aria-label="{{ __('filament-file-manager::file-manager.selected') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4.2 4.2L19 6.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
        @endif
        <span class="ufm-picker-explorer__thumbnail">
            @if ($this->isImage($item))
                <img src="{{ $this->thumbnailUrl($item['path']) }}" alt="" loading="lazy" />
            @else
                @php($documentKind = $this->documentKind($item))
                <span class="ufm__document-icon ufm__document-icon--{{ $documentKind }}"><span>{{ strtoupper($documentKind === 'word' ? 'doc' : $documentKind) }}</span></span>
            @endif
        </span>
        <span class="ufm-picker-explorer__name">{{ $item['name'] }}</span>
        <span class="ufm-picker-explorer__meta">{{ number_format(($item['size'] ?? 0) / 1024, 1) }} KB</span>
    </button>
@endif
