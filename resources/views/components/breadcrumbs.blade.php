{{--
    Breadcrumb trail: Home › ... › Current page.
    $items: ['dashboard'] inserts the signed-in user's dashboard; ['Label', url] is a link;
    the last ['Label'] is the current page (not a link).
--}}
@props(['items' => []])
@php
    $user = auth()->user();
    $trail = [['Home', route('home')]];
    foreach ($items as $item) {
        if ($item === 'dashboard') {
            if ($user) {
                $trail[] = $user->isAdmin() ? ['Admin dashboard', route('admin.dashboard')] : ['Dashboard', route('dashboard')];
            }
            continue;
        }
        $trail[] = (array) $item;
    }
    $last = count($trail) - 1;
@endphp
<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-slate-600">
        @foreach ($trail as $i => $crumb)
            <li class="flex min-w-0 items-center gap-1.5">
                @if ($i > 0)
                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 14.8a.75.75 0 0 1 0-1.06L10.94 10 7.2 6.27a.75.75 0 1 1 1.06-1.06l4.27 4.26a.75.75 0 0 1 0 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
                @endif
                @if ($i === $last || empty($crumb[1]))
                    <span class="max-w-[16rem] truncate font-semibold text-fiji-900" @if ($i === $last) aria-current="page" @endif title="{{ $crumb[0] }}">{{ $crumb[0] }}</span>
                @else
                    <a href="{{ $crumb[1] }}" class="flex items-center gap-1 rounded hover:text-ocean-700 hover:underline">
                        @if ($i === 0)<svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M3 9.5 10 3l7 6.5V17a1 1 0 0 1-1 1h-3.5v-5h-5v5H4a1 1 0 0 1-1-1V9.5Z"/></svg>@endif
                        {{ $crumb[0] }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
