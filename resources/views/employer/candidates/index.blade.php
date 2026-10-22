<x-app-layout title="Find candidates" :breadcrumbs="['dashboard', ['Candidates']]" help="faq-can-i-find-candidates-without-an-application">
    <x-slot name="header">
        <h1 class="page-title">Find candidates</h1>
        <p class="page-intro">Search jobseeker profiles by skill, qualification, region and experience. Verified candidates are listed first.</p>
    </x-slot>

    <form method="GET" role="search" class="card mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="filter-skill" class="label">Skill <x-tip align="start">Type part of a skill, such as Excel or Customer. Suggestions come from the platform skill list, and matching ignores capitals.</x-tip></label>
            <input id="filter-skill" type="search" name="skill" value="{{ $filters['skill'] ?? '' }}" placeholder="e.g. Excel…" list="skill-options" autocomplete="off" class="input">
            <datalist id="skill-options">@foreach (\App\Support\SkillCatalog::all() as $skill)<option value="{{ $skill }}">@endforeach</datalist>
        </div>
        <div>
            <label for="filter-qualification" class="label">Qualification</label>
            <select id="filter-qualification" name="qualification_level" class="input">
                <option value="">Any qualification</option>
                @foreach (['Certificate', 'Diploma', 'Bachelor', 'Postgraduate'] as $level)
                    <option value="{{ $level }}" @selected(($filters['qualification_level'] ?? '') === $level)>{{ $level }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-region" class="label">Region</label>
            <select id="filter-region" name="region" class="input">
                <option value="">All regions</option>
                @foreach (['Central Division', 'Western Division', 'Northern Division', 'Eastern Division'] as $region)
                    <option value="{{ $region }}" @selected(($filters['region'] ?? '') === $region)>{{ $region }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-experience" class="label">Minimum experience (years)</label>
            <input id="filter-experience" type="number" min="0" max="60" inputmode="numeric" name="min_experience" value="{{ $filters['min_experience'] ?? '' }}" class="input">
        </div>
        <div class="flex flex-wrap items-center gap-4 sm:col-span-2 lg:col-span-4">
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="verified_only" value="1" class="rounded text-ocean-700" @checked(! empty($filters['verified_only']))> Verified credentials only <x-tip align="start">Only show candidates whose academic credential an administrator has checked and approved.</x-tip></label>
            <div class="ml-auto flex gap-2">
                @if (array_filter($filters))<a href="{{ route('employer.candidates.index') }}" class="btn-secondary">Clear filters</a>@endif
                <button type="submit" class="btn-primary">Search candidates</button>
            </div>
        </div>
    </form>

    <p class="mb-4 text-sm font-semibold text-slate-700" role="status"><span class="tabular-nums">{{ $candidates->total() }}</span> {{ Str::plural('candidate', $candidates->total()) }} found</p>

    @if ($candidates->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($candidates as $candidate)
                <a href="{{ route('employer.candidates.show', $candidate) }}" class="card-link">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-fiji-900">{{ $candidate->name }}</p>
                        @if ($candidate->is_verified)<span class="badge-verified">Verified</span>@endif
                    </div>
                    <p class="mt-1 text-sm text-slate-600">{{ $candidate->qualification_level ?: 'Qualification not set' }}. <span class="tabular-nums">{{ $candidate->years_experience ?? 0 }}</span> {{ Str::plural('year', $candidate->years_experience ?? 0) }}. {{ $candidate->region }}</p>
                    @if ($candidate->about_me)<p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $candidate->about_me }}</p>@endif
                    <ul class="mt-3 flex flex-wrap gap-1.5" aria-label="Skills">
                        @foreach (array_slice($candidate->skillsList(), 0, 4) as $skill)<li class="skill-chip">{{ $skill }}</li>@endforeach
                        @if (count($candidate->skillsList()) > 4)<li class="skill-chip">+{{ count($candidate->skillsList()) - 4 }} more</li>@endif
                    </ul>
                </a>
            @endforeach
        </div>
    @else
        <div class="card py-12 text-center">
            <h2 class="text-lg font-semibold text-fiji-900">No candidates match those filters</h2>
            <p class="mt-2 text-sm text-slate-600">Remove a filter or search for a related skill.</p>
        </div>
    @endif

    <div class="mt-6">{{ $candidates->links() }}</div>
</x-app-layout>
