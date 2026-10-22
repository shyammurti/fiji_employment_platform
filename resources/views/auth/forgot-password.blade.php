<x-guest-layout title="Reset password">
    <h1 class="text-2xl font-semibold tracking-tight text-fiji-900">Forgot your password?</h1>
    <p class="mb-6 mt-2 text-sm text-slate-600">Enter your account email and we will send you a password reset link. Phone-only accounts should contact the administrator.</p>

    @if (session('status'))
        <div class="alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" spellcheck="false" class="input" placeholder="name@example.com">
            <x-field-error name="email" />
        </div>
        <button type="submit" class="btn-primary w-full">Email reset link</button>
        <p class="text-center text-sm text-slate-600"><a href="{{ route('login') }}" class="link">Back to log in</a></p>
    </form>
</x-guest-layout>
