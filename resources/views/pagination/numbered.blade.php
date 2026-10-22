{{--
    Pagination used by every list. Jump straight to a record range
    ("21 to 40"), change how many records show per page, or step through
    numbered pages. Works without JavaScript via the Go buttons.
--}}
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $perPage = $paginator->perPage();
    $total = $paginator->total();

    // Grid pages use multiples of their column count; lists use 10/20/50/100.
    $gridBase = collect([9, 12])->first(fn ($base) => in_array($perPage, \App\Support\PerPage::grid($base), true));
    $sizes = $gridBase ? \App\Support\PerPage::grid($gridBase) : \App\Support\PerPage::LIST;
    if (! in_array($perPage, $sizes, true)) { $sizes[] = $perPage; sort($sizes); }

    // Keep search filters when jumping pages or changing the page size.
    $keep = collect(request()->query())->except(['page', 'per_page'])->filter(fn ($value) => is_scalar($value));

    // Page numbers to show: first, last, and two either side of the current page.
    $window = collect([1, $last])->merge(range(max(1, $current - 2), min($last, $current + 2)))->unique()->sort()->values();
    $range = fn (int $page) => number_format(($page - 1) * $perPage + 1).'–'.number_format(min($page * $perPage, $total));
@endphp

@if ($total > 0)
<nav role="navigation" aria-label="Pagination" class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-surface px-4 py-3 shadow-card lg:flex-row lg:items-center lg:justify-between">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 text-sm text-slate-700">
        {{-- Jump to a range of records --}}
        <form method="GET" class="flex items-center gap-2" onchange="this.requestSubmit()">
            @foreach ($keep as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <label for="page-range-{{ $paginator->getPageName() }}">Showing</label>
            <select id="page-range-{{ $paginator->getPageName() }}" name="{{ $paginator->getPageName() }}" class="input !mt-0 !min-h-9 !w-auto !py-1.5 !pr-8 tabular-nums" @disabled($last === 1)>
                @for ($page = 1; $page <= $last; $page++)
                    <option value="{{ $page }}" @selected($page === $current)>{{ $range($page) }}</option>
                @endfor
            </select>
            <span class="tabular-nums">of {{ number_format($total) }}</span>
            <noscript><button type="submit" class="btn-secondary btn-sm">Go</button></noscript>
        </form>

        {{-- Records per page --}}
        <form method="GET" class="flex items-center gap-2" onchange="this.requestSubmit()">
            @foreach ($keep as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
            <label for="per-page-{{ $paginator->getPageName() }}">Per page</label>
            <select id="per-page-{{ $paginator->getPageName() }}" name="per_page" class="input !mt-0 !min-h-9 !w-auto !py-1.5 !pr-8 tabular-nums">
                @foreach ($sizes as $size)
                    <option value="{{ $size }}" @selected($size === $perPage)>{{ $size }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn-secondary btn-sm">Apply</button></noscript>
        </form>
    </div>

    @if ($last > 1)
        <ul class="flex flex-wrap items-center gap-1 text-sm" role="list">
            @php($link = 'flex h-9 min-w-9 items-center justify-center rounded-lg px-2.5 font-medium tabular-nums transition-colors')
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $link }} cursor-not-allowed text-slate-400" aria-disabled="true"><span aria-hidden="true">‹</span><span class="ml-1 hidden sm:inline">Previous</span></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} text-slate-700 hover:bg-slate-100"><span aria-hidden="true">‹</span><span class="ml-1 hidden sm:inline">Previous</span><span class="sr-only sm:hidden">Previous page</span></a>
                @endif
            </li>
            @foreach ($window as $i => $page)
                @if ($i > 0 && $page - $window[$i - 1] > 1)
                    <li class="px-1 text-slate-500" aria-hidden="true">…</li>
                @endif
                <li>
                    @if ($page === $current)
                        <span class="{{ $link }} bg-brand text-white" aria-current="page"><span class="sr-only">Page </span>{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="{{ $link }} text-slate-700 hover:bg-slate-100" title="Records {{ $range($page) }}"><span class="sr-only">Page </span>{{ $page }}</a>
                    @endif
                </li>
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} text-slate-700 hover:bg-slate-100"><span class="mr-1 hidden sm:inline">Next</span><span class="sr-only sm:hidden">Next page</span><span aria-hidden="true">›</span></a>
                @else
                    <span class="{{ $link }} cursor-not-allowed text-slate-400" aria-disabled="true"><span class="mr-1 hidden sm:inline">Next</span><span aria-hidden="true">›</span></span>
                @endif
            </li>
        </ul>
    @endif
</nav>
@endif
