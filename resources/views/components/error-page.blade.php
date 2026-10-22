@props(['code', 'title', 'message', 'icon' => 'info'])
<x-app-layout :title="$title" :breadcrumbs="[[$title]]" help="account">
    <section class="mx-auto max-w-xl py-12 text-center" aria-labelledby="error-title">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-ocean-50 text-ocean-700" aria-hidden="true">
            @if ($icon === 'removed')
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7 19.1 19.1A2 2 0 0 1 17.1 21H6.9a2 2 0 0 1-2-1.9L4 7m5 4v6m6-6v6M3 7h18M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg>
            @elseif ($icon === 'lock')
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path stroke-linecap="round" d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            @else
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5m0 3h.01"/></svg>
            @endif
        </span>
        <p class="mt-6 text-sm font-semibold tabular-nums text-ocean-700">Error {{ $code }}</p>
        <h1 id="error-title" class="page-title mt-2">{{ $title }}</h1>
        <p class="mt-4 text-slate-600">{{ $message }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="btn-secondary">Go back</a>
            <a href="{{ auth()->check() ? (auth()->user()->isAdmin() ? route('admin.dashboard') : route('dashboard')) : route('home') }}" class="btn-primary">{{ auth()->check() ? 'Open your dashboard' : 'Go to home page' }}</a>
        </div>
        {{ $slot }}
    </section>
</x-app-layout>
