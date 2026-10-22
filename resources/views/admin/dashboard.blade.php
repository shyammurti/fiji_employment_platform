<x-app-layout title="Admin dashboard">
    <x-slot name="header">
        <h1 class="page-title">Admin dashboard</h1>
        <p class="page-intro">Platform activity, user counts and everything waiting for a decision.</p>
    </x-slot>

    <section aria-labelledby="queues-title">
        <h2 id="queues-title" class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Waiting for review</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Employer accounts', $totals['pending_employers'], route('admin.employers.pending')],
                ['Job listings', $totals['pending_jobs'], route('admin.jobs.pending')],
                ['Credentials', $totals['pending_verifications'], route('admin.verifications.index', ['status' => 'pending'])],
            ] as [$label, $count, $url])
                <a href="{{ $url }}" class="card-link flex items-center justify-between gap-4 {{ $count ? 'border-amber-300' : '' }}">
                    <div>
                        <p class="stat-value {{ $count ? 'text-amber-700' : '' }}">{{ $count }}</p>
                        <p class="stat-label">{{ $label }}</p>
                    </div>
                    <span class="text-sm font-semibold text-ocean-700">{{ $count ? 'Review' : 'Clear' }} <span aria-hidden="true">&rarr;</span></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-8" aria-labelledby="metrics-title">
        <h2 id="metrics-title" class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Platform metrics</h2>
        <div class="card grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Total users', $totals['total_users'], '+'.$totals['new_users_week'].' this week'],
                ['Jobseekers', $totals['jobseekers'], null],
                ['Employers', $totals['employers'], null],
                ['Mentors', $totals['mentors'], null],
                ['Live job listings', $totals['live_jobs'], null],
                ['Applications', $totals['applications'], '+'.$totals['applications_week'].' this week'],
                ['Candidates hired', $totals['hires'], null],
                ['Mentorship sessions', $totals['mentorship_sessions'], null],
            ] as [$label, $value, $note])
                <div>
                    <p class="stat-value">{{ number_format($value) }}</p>
                    <p class="stat-label">{{ $label }}</p>
                    @if ($note)<p class="mt-1 text-xs font-medium tabular-nums text-success-700">{{ $note }}</p>@endif
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-8 grid items-start gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2" aria-labelledby="activity-title">
            <h2 id="activity-title" class="text-lg font-semibold text-fiji-900">Recent application activity</h2>
            <ol class="mt-4 divide-y divide-slate-100">
                @forelse ($recentActivity as $event)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <p class="min-w-0 text-slate-700">
                            <span class="font-medium text-fiji-900">{{ $event->application?->jobseeker?->name ?? 'Applicant' }}</span>
                            {{ $event->from_status ? 'moved to' : 'applied for' }}
                            @if ($event->from_status)<span class="status-pill bg-slate-100 text-slate-700">{{ $event->to_status }}</span> on @endif
                            <span class="font-medium">{{ $event->application?->jobListing?->title }}</span>
                        </p>
                        <time class="text-xs text-slate-500" datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->diffForHumans() }}</time>
                    </li>
                @empty
                    <li class="py-3 text-sm text-slate-600">No application activity yet.</li>
                @endforelse
            </ol>
        </section>

        <div class="space-y-6">
            <section class="card" aria-labelledby="employer-requests-title">
                <h2 id="employer-requests-title" class="font-semibold text-fiji-900">Latest employer requests</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    @forelse ($recentEmployerRequests as $employer)
                        <li class="py-2 text-sm"><a href="{{ route('admin.employers.pending') }}" class="link">{{ $employer->company_name }}</a><span class="block text-slate-600">{{ $employer->name }}</span></li>
                    @empty
                        <li class="py-2 text-sm text-slate-600">Nothing pending.</li>
                    @endforelse
                </ul>
            </section>
            <section class="card" aria-labelledby="job-requests-title">
                <h2 id="job-requests-title" class="font-semibold text-fiji-900">Latest listing submissions</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    @forelse ($recentJobRequests as $job)
                        <li class="py-2 text-sm"><a href="{{ route('admin.jobs.review', $job) }}" class="link">{{ $job->title }}</a><span class="block text-slate-600">{{ $job->employer->company_name }}</span></li>
                    @empty
                        <li class="py-2 text-sm text-slate-600">Nothing pending.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
