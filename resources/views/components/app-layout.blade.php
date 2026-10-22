@props(['title' => null])
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

    @isset($header)
        <header class="border-b border-slate-200 bg-platform-bg">
            <div class="mx-auto max-w-7xl px-4 pb-7 pt-9 sm:px-6 lg:px-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-fiji-900 sm:[&_h2]:text-3xl">
                {{ $header }}
            </div>
        </header>
    @endisset

    <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-4 py-8 focus:outline-none sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert-warning" role="status">{{ session('warning') }}</div>
        @endif
        @if (isset($errors) && $errors->any())
            <div class="alert-error" role="alert">
                <p class="mb-1 font-semibold">Please check the following:</p>
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
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
