@props([
    'title' => null,
    // Breadcrumb trail after "Home": [['Label', url], ..., ['Current page']]. Use 'dashboard' as an item to insert the user's dashboard.
    'breadcrumbs' => null,
    // FAQ anchor on the Help page for "Help with this page", e.g. 'jobs' or 'faq-how-do-i-apply'.
    'help' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="Fiji's employment platform connecting graduates and skilled jobseekers with approved employers and industry mentors.">
    <title>{{ $title ? $title.' | Fiji Employment Hub' : config('app.name') }}</title>
    <x-favicons />
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-platform-bg font-sans text-charcoal antialiased" x-data>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-surface focus:p-4 focus:shadow-pop">Skip to content</a>
    @include('layouts.navigation')

    @if (isset($header) || $breadcrumbs)
        <header class="border-b border-slate-200 bg-platform-bg">
            <div class="mx-auto max-w-7xl px-4 pb-7 pt-6 sm:px-6 lg:px-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-fiji-900 sm:[&_h2]:text-3xl">
                @if ($breadcrumbs || $help)
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
                        @if ($breadcrumbs)<x-breadcrumbs :items="$breadcrumbs" />@else<span></span>@endif
                        @if ($help)
                            <a href="{{ route('help') }}#{{ $help }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ocean-700 hover:text-fiji-900 hover:underline">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path stroke-linecap="round" d="M7.8 7.6a2.3 2.3 0 0 1 4.4.9c0 1.5-2.2 1.9-2.2 3.2M10 14.4h.01"/></svg>
                                Help with this page
                            </a>
                        @endif
                    </div>
                @endif
                {{ $header ?? '' }}
            </div>
        </header>
    @endif

    <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-4 py-8 focus:outline-none sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert-warning" role="status">{{ session('warning') }}</div>
        @endif
        @if (isset($errors) && $errors->any())
            {{-- Each message links to its field; app.js also moves focus to the first one. --}}
            <div class="alert-error" role="alert" data-error-summary data-error-fields="{{ implode(',', $errors->keys()) }}">
                <p class="font-semibold">{{ $errors->count() === 1 ? 'One thing needs fixing' : $errors->count().' things need fixing' }} before you can continue.</p>
                <p class="mt-0.5">Select a message to go to that field, correct it, then submit again.</p>
                <ul class="mt-2 list-inside list-disc space-y-0.5">
                    @foreach ($errors->messages() as $field => $messages)
                        @foreach ($messages as $message)
                            <li><button type="button" class="text-left underline decoration-red-300 underline-offset-2 hover:decoration-current" data-error-for="{{ $field }}">{{ $message }}</button></li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mt-12 border-t border-slate-200 bg-surface">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p class="flex items-center gap-3"><x-logo :size="32" />{{ config('app.name') }}. Connecting Fiji's jobseekers, employers and mentors.</p>
            <nav aria-label="Footer" class="flex gap-5">
                <a href="{{ route('jobs.index') }}" class="link">Browse jobs</a>
                <a href="{{ route('help') }}" class="link">Help &amp; FAQ</a>
            </nav>
        </div>
    </footer>
</body>
</html>
