<x-guest-layout title="Choose a new password">
    <h1 class="mb-6 text-2xl font-semibold tracking-tight text-fiji-900">Choose a new password</h1>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="email" spellcheck="false" class="input">
            <x-field-error name="email" />
        </div>
        <div>
            <label for="password" class="label">New password</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="input">
            <x-field-error name="password" />
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="input">
        </div>
        <button type="submit" class="btn-primary w-full">Reset password</button>
    </form>
</x-guest-layout>
