<x-guest-layout title="Verify your email">
    <h1 class="text-2xl font-semibold tracking-tight text-fiji-900">Check your inbox</h1>
    <p class="mt-3 text-sm leading-6 text-slate-600">
        We sent a verification link to <strong class="break-all text-fiji-900">{{ auth()->user()->email }}</strong>.
        Open it to activate your account. The link expires in 60 minutes.
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="alert-success mt-5 !mb-0" role="status">A new verification link has been sent to your email address.</div>
    @elseif (session('status'))
        <div class="alert-success mt-5 !mb-0" role="status">{{ session('status') }}</div>
    @endif

    <ul class="mt-5 list-disc space-y-1 pl-5 text-sm text-slate-600">
        <li>Check your spam or promotions folder.</li>
        <li>Wrong address? <a href="{{ route('profile.edit') }}" class="link">Correct it on your profile</a> and we will send a new link.</li>
    </ul>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn-primary">Resend verification email</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-semibold text-slate-600 hover:text-fiji-900 hover:underline">Log out</button>
        </form>
    </div>
</x-guest-layout>
