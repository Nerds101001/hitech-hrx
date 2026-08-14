{{--
    Expects $paginated (a LengthAwarePaginator built from the current
    brand/category slice, not the whole result set — see
    DigitalLibraryController::index()). Page links preserve category/brand/
    subcat automatically since the paginator was built with
    'query' => $request->except('page'); brand/subcat links elsewhere reset
    to page 1 by simply never including a page param.
--}}
@if($paginated && $paginated->lastPage() > 1)
    @php
        $current = $paginated->currentPage();
        $last = $paginated->lastPage();
        $windowStart = max(1, $current - 2);
        $windowEnd = min($last, $current + 2);
    @endphp
    <div class="vault-pagination d-flex justify-content-center align-items-center flex-wrap gap-2 mt-4 pt-3">
        <a href="{{ $paginated->previousPageUrl() ?? 'javascript:void(0)' }}" class="page-btn {{ !$paginated->previousPageUrl() ? 'disabled' : '' }}">‹ Prev</a>

        @if($windowStart > 1)
            <a href="{{ $paginated->url(1) }}" class="page-btn">1</a>
            @if($windowStart > 2)<span class="text-muted small px-1">…</span>@endif
        @endif

        @for($p = $windowStart; $p <= $windowEnd; $p++)
            <a href="{{ $paginated->url($p) }}" class="page-btn {{ $p === $current ? 'active' : '' }}">{{ $p }}</a>
        @endfor

        @if($windowEnd < $last)
            @if($windowEnd < $last - 1)<span class="text-muted small px-1">…</span>@endif
            <a href="{{ $paginated->url($last) }}" class="page-btn">{{ $last }}</a>
        @endif

        <a href="{{ $paginated->nextPageUrl() ?? 'javascript:void(0)' }}" class="page-btn {{ !$paginated->nextPageUrl() ? 'disabled' : '' }}">Next ›</a>
    </div>
@endif
