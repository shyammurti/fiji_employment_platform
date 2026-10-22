<x-guest-layout title="Confirm password">
    <h1 class="text-2xl font-semibold tracking-tight text-fiji-900">Confirm your password</h1>
    <p class="mb-6 mt-2 text-sm text-slate-600">This is a secure area. Please confirm your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="current-password" class="input">
            <x-field-error name="password" />
        </div>
        <button type="submit" class="btn-primary w-full">Confirm</button>
    </form>
</x-guest-layout>
