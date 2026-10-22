<x-app-layout title="Browse jobs" :breadcrumbs="[['Browse jobs']]" help="jobs">
    <x-slot name="header">
        <h1 class="page-title">Find your next opportunity</h1>
        <p class="page-intro">Every vacancy here has been reviewed by an administrator and comes from an approved employer.</p>
    </x-slot>

    <form method="GET" role="search" class="card mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="filter-q" class="label">Keyword <x-tip align="start">Searches job titles, company names and descriptions. Combine it with a type or region to narrow the results.</x-tip></label>
            <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title, company or keyword…" autocomplete="off" class="input">
        </div>
        <div>
            <label for="filter-type" class="label">Employment type</label>
            <select id="filter-type" name="type" class="input">
                <option value="">Any type</option>
                @foreach (['Full-time', 'Part-time', 'Contract', 'Remote'] as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
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
        <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="btn-primary">Search jobs</button>
            @if (array_filter($filters))
                <a href="{{ route('jobs.index') }}" class="btn-secondary">Clear filters</a>
            @endif
        </div>
    </form>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-semibold text-slate-700" role="status"><span class="tabular-nums">{{ $jobs->total() }}</span> {{ Str::plural('vacancy', $jobs->total()) }} found</p>
        @if (auth()->user()?->isJobseeker())
            <p class="flex items-center gap-1 text-xs text-slate-600">Match scores compare your saved skills with each vacancy's required skills. <x-tip align="end" label="How skill match is calculated">The percentage of a job's required skills that are on your profile. 3 of 4 required skills = 75%. Add missing skills on your profile to raise your score. It is guidance only, not a hiring decision.</x-tip></p>
        @endif
    </div>

    @if ($jobs->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($jobs as $job)
                <x-job-card :job="$job" :score="$job->match_score ?? null" />
            @endforeach
        </div>
    @else
        <div class="card py-12 text-center">
            <h2 class="text-lg font-semibold text-fiji-900">No vacancies match your search</h2>
            <p class="mt-2 text-sm text-slate-600">Try a broader keyword or choose another region.</p>
            @if (array_filter($filters))<a href="{{ route('jobs.index') }}" class="btn-secondary mt-5">Show all vacancies</a>@endif
        </div>
    @endif

    <div class="mt-6">{{ $jobs->links() }}</div>
</x-app-layout>
