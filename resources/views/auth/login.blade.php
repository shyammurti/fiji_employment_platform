<x-guest-layout title="Log in">
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-slate-900">{{ $loginTitle ?? 'Login' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $loginIntro ?? 'Access your Fiji Employment Hub account.' }}</p>
    </div>

    @if (session('status'))
        <div class="mb-4 text-sm font-medium text-ocean-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        @if (! empty($loginRole))
            <input type="hidden" name="expected_role" value="{{ $loginRole }}">
        @endif
        <div>
            <label for="identifier" class="label">Email or phone number</label>
            <input id="identifier" type="text" name="identifier" value="{{ old('identifier', old('email')) }}" required autocomplete="username" spellcheck="false" autocapitalize="none" maxlength="255" class="input" placeholder="name@example.com or +679 7XXXXXX">
            <x-field-error name="identifier" /><x-field-error name="email" />
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="input" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror>
            <x-field-error name="password" />
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-ocean-700 shadow-sm">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-ocean-700 hover:underline">Forgot password?</a>
        </div>
        <button type="submit" class="btn-primary w-full justify-center">Log in</button>
        <div class="space-y-2 text-center text-sm text-slate-500">
            <p>New here? <a href="{{ route('register') }}" class="text-ocean-700 hover:underline">Create an account</a></p>
            <div class="flex justify-center gap-3">
                <a href="{{ route('login') }}" class="text-ocean-700 hover:underline">Jobseeker</a>
                <a href="{{ route('employer.login') }}" class="text-ocean-700 hover:underline">Employer</a>
                <a href="{{ route('mentor.login') }}" class="text-ocean-700 hover:underline">Mentor</a>
            </div>
        </div>
    </form>
</x-guest-layout>
