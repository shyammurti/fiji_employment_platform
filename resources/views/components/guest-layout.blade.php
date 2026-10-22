@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f4f7f8">
    <title>{{ $title ? $title.' | Fiji Employment Hub' : config('app.name') }}</title>
    <x-favicons />
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-background min-h-screen px-4 py-10 font-sans text-charcoal antialiased">
    <div class="mx-auto flex max-w-7xl items-center justify-between">
        <a href="{{ route('home') }}" class="flex items-center gap-3 font-bold text-fiji-900"><x-logo :size="44" />Fiji Employment Hub</a>
        <div class="flex items-center gap-2"><x-theme-toggle /><a href="{{ route('help') }}" class="link text-sm">Need help?</a></div>
    </div>
    <main class="auth-shell mx-auto my-10 rounded-3xl border border-slate-200 bg-surface p-6 shadow-pop sm:p-10">
        @if (isset($errors) && $errors->any())
            <div role="alert" class="alert-error" data-error-summary data-error-fields="{{ implode(',', $errors->keys()) }}">
                <p class="font-semibold">{{ $errors->count() === 1 ? 'One thing needs fixing' : $errors->count().' things need fixing' }} before you can continue.</p>
                <ul class="mt-2 list-inside list-disc space-y-0.5">
                    @foreach ($errors->messages() as $field => $messages)
                        @foreach ($messages as $message)<li><button type="button" class="text-left underline decoration-red-300 underline-offset-2 hover:decoration-current" data-error-for="{{ $field }}">{{ $message }}</button></li>@endforeach
                    @endforeach
                </ul>
            </div>
        @endif
        {{ $slot }}
    </main>
    <p class="text-center text-xs text-slate-600">Connecting Fiji's jobseekers, employers and mentors.</p>
</body>
</html>
