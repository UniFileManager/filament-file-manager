@if ($pagination->lastPage() > 1)
    @php
        $currentPage = $pagination->currentPage();
        $lastPage = $pagination->lastPage();
        $startPage = max(1, min($currentPage - 1, $lastPage - 2));
        $endPage = min($lastPage, $startPage + 2);
    @endphp
    <nav wire:key="ufm-pagination-{{ $pageName }}" class="ufm__pagination" aria-label="File manager pagination">
        <button type="button" wire:click="goToManagerPage('{{ $pageName }}', 1)" @disabled($currentPage === 1) class="ufm__pagination-icon" aria-label="First page" title="First page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m11 6-6 6 6 6M19 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" wire:click="goToManagerPage('{{ $pageName }}', {{ $currentPage - 1 }})" @disabled($currentPage === 1) class="ufm__pagination-icon" aria-label="Previous page" title="Previous page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        @if ($startPage > 1)<span class="ufm__pagination-ellipsis">…</span>@endif
        @for ($page = $startPage; $page <= $endPage; $page++)
            <button type="button" wire:click="goToManagerPage('{{ $pageName }}', {{ $page }})" @class(['ufm__pagination-page', 'is-active' => $page === $currentPage]) aria-current="{{ $page === $currentPage ? 'page' : 'false' }}">{{ $page }}</button>
        @endfor
        @if ($endPage < $lastPage)<span class="ufm__pagination-ellipsis">…</span>@endif
        <button type="button" wire:click="goToManagerPage('{{ $pageName }}', {{ $currentPage + 1 }})" @disabled($currentPage === $lastPage) class="ufm__pagination-icon" aria-label="Next page" title="Next page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" wire:click="goToManagerPage('{{ $pageName }}', {{ $lastPage }})" @disabled($currentPage === $lastPage) class="ufm__pagination-icon" aria-label="Last page" title="Last page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 6 6 6-6 6M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </nav>
@endif
