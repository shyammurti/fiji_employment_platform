@php
    // [label, route, active pattern]
    $nav = [['Browse jobs', 'jobs.index', 'jobs.index']];
    $user = auth()->user();
    if ($user) {
        $nav[] = ['Dashboard', $user->isAdmin() ? 'admin.dashboard' : 'dashboard', $user->isAdmin() ? 'admin.dashboard' : 'dashboard'];
        if ($user->isJobseeker()) $nav[] = ['My applications', 'applications.mine', 'applications.mine'];
        if ($user->isApprovedEmployer()) {
            $nav[] = ['My listings', 'employer.jobs.index', 'employer.jobs.*'];
            $nav[] = ['Applications', 'employer.applications.index', 'employer.applications.*'];
            $nav[] = ['Candidates', 'employer.candidates.index', 'employer.candidates.*'];
        }
        if ($user->isJobseeker() || $user->isMentor()) $nav[] = ['Career Hub', 'career.hub', 'career.hub'];
        if ($user->isAdmin()) {
            $nav[] = ['Employers', 'admin.employers.pending', 'admin.employers.*'];
            $nav[] = ['Listings', 'admin.jobs.pending', 'admin.jobs.*'];
            $nav[] = ['Credentials', 'admin.verifications.index', 'admin.verifications.*'];
            $nav[] = ['User access', 'admin.users.index', 'admin.users.*'];
        }
    }
    $nav[] = ['Help', 'help', 'help'];
@endphp
<nav class="site-nav" x-data="{ open: false, accountOpen: false }" @keydown.escape.window="open = false; accountOpen = false" aria-label="Main navigation">
    <div class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="Fiji Employment Hub home">
            <x-logo :size="48" />
            <span class="text-sm font-bold leading-tight text-fiji-900 sm:text-base">Fiji Employment<span class="hidden text-[10px] font-semibold uppercase tracking-[.2em] text-ocean-700 sm:block">Talent, opportunity, growth</span></span>
        </a>
        <div class="hidden min-w-0 items-center gap-0.5 xl:flex">
            @foreach ($nav as [$label, $route, $pattern])
                <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($pattern) ? 'nav-link-active' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            <x-theme-toggle />
            @auth
                <x-notifications-dropdown />
                <div class="relative hidden sm:block">
                    <button type="button" @click="accountOpen = !accountOpen" :aria-expanded="accountOpen" aria-haspopup="true" aria-controls="account-menu" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-surface py-1.5 pl-1.5 pr-3 text-sm transition-colors hover:border-ocean-500">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ocean-100 font-bold text-ocean-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="max-w-32 truncate font-semibold">{{ $user->name }}</span>
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.2 7.2a.75.75 0 0 1 1.06.02L10 11.17l3.74-3.95a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div id="account-menu" x-show="accountOpen" x-cloak @click.outside="accountOpen = false" class="absolute right-0 z-40 mt-3 w-60 rounded-2xl border border-slate-200 bg-surface p-2 shadow-pop">
                        <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-600">{{ ucfirst($user->role) }} account</p>
                        <a class="menu-link" href="{{ route('profile.edit') }}">Your profile</a>
                        @if ($user->isJobseeker())<a class="menu-link" href="{{ route('employer.register.form') }}">Register as employer</a>@endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="menu-link w-full text-left">Log out</button></form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="hidden text-sm font-semibold text-fiji-900 hover:text-ocean-700 sm:inline">Log in</a>
                <a href="{{ route('register') }}" class="btn-primary hidden sm:inline-flex">Create account</a>
            @endauth
            <button type="button" class="rounded-xl border border-slate-200 p-2.5 text-fiji-900 xl:hidden" @click="open = !open" :aria-expanded="open" aria-controls="mobile-nav" :aria-label="open ? 'Close navigation' : 'Open navigation'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path x-show="!open" d="M4 6h16M4 12h16M4 18h16"/><path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>
    <div id="mobile-nav" x-show="open" x-cloak class="border-t border-slate-100 bg-surface px-4 py-4 xl:hidden">
        <div class="grid gap-1 sm:grid-cols-2">
            @foreach ($nav as [$label, $route, $pattern])
                <a href="{{ route($route) }}" class="menu-link {{ request()->routeIs($pattern) ? 'bg-ocean-50 text-ocean-700' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            @auth
                <a href="{{ route('profile.edit') }}" class="menu-link">Your profile</a>
                @if ($user->isJobseeker())<a href="{{ route('employer.register.form') }}" class="menu-link">Register as employer</a>@endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="menu-link w-full text-left">Log out</button></form>
            @else
                <a class="menu-link" href="{{ route('login') }}">Log in</a>
                <a class="menu-link" href="{{ route('register') }}">Create account</a>
            @endauth
        </div>
    </div>
</nav>
