<x-app-layout title="Dashboard">
    <x-slot name="header">
        <p class="eyebrow">{{ ucfirst($user->role) }} workspace</p>
        <h1 class="page-title mt-2">Welcome back, {{ $user->name }}</h1>
    </x-slot>

    @if ($user->isJobseeker())
        @php
            $checklist = [
                'Contact details and region' => $user->phone && $user->region,
                'Qualification and experience' => $user->qualification_level && $user->years_experience !== null,
                'At least three skills' => count($user->skillsList()) >= 3,
                'Credential uploaded' => (bool) $user->degree_certificate_path,
                'Credential verified' => (bool) $user->is_verified,
            ];
            $done = count(array_filter($checklist));
        @endphp

        @if ($done < count($checklist))
            <section class="card mb-6" aria-labelledby="checklist-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="checklist-title" class="text-lg font-semibold text-fiji-900">Complete your profile</h2>
                    <p class="text-sm font-semibold tabular-nums text-slate-700">{{ $done }} of {{ count($checklist) }} done</p>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ count($checklist) }}" aria-valuenow="{{ $done }}" aria-labelledby="checklist-title">
                    <div class="h-full rounded-full bg-ocean-600" style="width: {{ round($done / count($checklist) * 100) }}%"></div>
                </div>
                <ul class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($checklist as $item => $complete)
                        <li class="flex items-center gap-2 {{ $complete ? 'text-slate-600' : 'font-medium text-fiji-900' }}">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] {{ $complete ? 'bg-success-100 text-success-700' : 'border border-slate-300' }}" aria-hidden="true">{{ $complete ? '✓' : '' }}</span>
                            {{ $item }}<span class="sr-only">: {{ $complete ? 'done' : 'to do' }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($user->degree_certificate_path && ! $user->is_verified)
                    <p class="hint mt-3">Credential status: {{ str_replace('_', ' ', $user->credential_status) }}. An administrator reviews uploads; comments appear on your profile.</p>
                @endif
                <a href="{{ route('profile.edit') }}" class="btn-primary mt-5">Update profile</a>
            </section>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-3">
            <section class="card lg:col-span-2" aria-labelledby="recent-title">
                <div class="flex items-center justify-between">
                    <h2 id="recent-title" class="text-lg font-semibold text-fiji-900">Recent applications</h2>
                    <a href="{{ route('applications.mine') }}" class="link text-sm">View all</a>
                </div>
                <ul class="mt-4 divide-y divide-slate-100">
                    @forelse ($applications as $app)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-fiji-900">{{ $app->jobListing->title }}</p>
                                <p class="truncate text-sm text-slate-600">{{ $app->jobListing->company }}. <span class="tabular-nums">{{ $app->match_percentage }}% match</span></p>
                            </div>
                            <span class="status-pill {{ $app->statusColor() }}">{{ $app->status }}</span>
                        </li>
                    @empty
                        <li class="py-4 text-sm text-slate-600">No applications yet. <a href="{{ route('jobs.index') }}" class="link">Browse open jobs</a>.</li>
                    @endforelse
                </ul>
            </section>

            <section class="card" aria-labelledby="mentor-title">
                <h2 id="mentor-title" class="text-lg font-semibold text-fiji-900">Upcoming mentorship</h2>
                <ul class="mt-4 space-y-3">
                    @forelse ($upcomingSessions as $session)
                        <li class="rounded-xl bg-slate-50 p-3">
                            <p class="font-medium text-fiji-900">{{ $session->topic }}</p>
                            <p class="text-sm text-slate-600">With {{ $session->mentor->name }}, {{ $session->scheduled_at->timezone('Pacific/Fiji')->format('d M, g:i A') }}</p>
                            <span class="status-pill mt-2 bg-ocean-50 text-ocean-700">{{ ucfirst($session->status) }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">No sessions booked. <a href="{{ route('career.hub') }}#mentors" class="link">Find a mentor</a>.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <section class="mt-8" aria-labelledby="recommended-title">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h2 id="recommended-title" class="text-lg font-semibold text-fiji-900">Recommended for you</h2>
                    <p class="text-sm text-slate-600">Open vacancies ranked by how many of their required skills you already have.</p>
                </div>
                <a href="{{ route('jobs.index') }}" class="link shrink-0 text-sm">All jobs</a>
            </div>
            @if ($recommendedJobs->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($recommendedJobs as $job)
                        <x-job-card :job="$job" :score="$job->requiredSkillsList() ? $job->match_score : null" />
                    @endforeach
                </div>
            @else
                <div class="card text-sm text-slate-600">You have applied to every open vacancy. New listings will appear here after approval.</div>
            @endif
        </section>

    @elseif ($user->isEmployer())
        @if ($user->employer_approval_status === 'pending')
            <div class="alert-warning">Your employer account is awaiting administrator approval. You can post vacancies and search candidates once it is approved.</div>
        @elseif ($user->employer_approval_status === 'rejected')
            <div class="alert-error">
                <p>Your employer registration was not approved.</p>
                @if ($user->admin_notes)<p class="mt-1">Administrator note: {{ $user->admin_notes }}</p>@endif
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-3">
                <a href="{{ route('employer.jobs.index') }}" class="card-link">
                    <p class="stat-value">{{ $jobListings->count() }}</p>
                    <p class="stat-label">Recent listings</p>
                </a>
                <a href="{{ route('employer.applications.index') }}" class="card-link">
                    <p class="stat-value">{{ $pendingApplicationsCount }}</p>
                    <p class="stat-label">Applications awaiting review</p>
                </a>
                <div class="card flex flex-col justify-center gap-3">
                    <a href="{{ route('jobs.create') }}" class="btn-primary w-full">Post a job</a>
                    <a href="{{ route('employer.candidates.index') }}" class="btn-secondary w-full">Search candidates</a>
                </div>
            </div>

            <section class="card mt-6" aria-labelledby="listings-title">
                <h2 id="listings-title" class="text-lg font-semibold text-fiji-900">Your listings</h2>
                <ul class="mt-4 divide-y divide-slate-100">
                    @forelse ($jobListings as $job)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-fiji-900">{{ $job->title }}</p>
                                <p class="text-sm tabular-nums text-slate-600">{{ $job->applications_count }} {{ Str::plural('application', $job->applications_count) }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="status-pill {{ $job->approval_status === 'approved' ? 'bg-success-100 text-success-700' : ($job->approval_status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800') }}">{{ str($job->approval_status)->replace('_', ' ')->title() }}</span>
                                <a href="{{ route('employer.jobs.applications', $job) }}" class="link text-sm">Applications</a>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-sm text-slate-600">You have not posted any jobs yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif

    @elseif ($user->isMentor())
        <div class="grid gap-6 sm:grid-cols-2">
            <a href="{{ route('career.hub') }}" class="card-link">
                <p class="stat-value">{{ $pendingSessions }}</p>
                <p class="stat-label">Session requests awaiting your confirmation</p>
            </a>
            <section class="card" aria-labelledby="upcoming-title">
                <h2 id="upcoming-title" class="text-lg font-semibold text-fiji-900">Upcoming sessions</h2>
                <ul class="mt-3 space-y-2">
                    @forelse ($upcomingSessions as $session)
                        <li class="rounded-xl bg-slate-50 p-3 text-sm">
                            <span class="font-medium text-fiji-900">{{ $session->topic }}</span> with {{ $session->mentee->name }}, {{ $session->scheduled_at->timezone('Pacific/Fiji')->format('d M, g:i A') }}
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">No upcoming sessions.</li>
                    @endforelse
                </ul>
            </section>
        </div>
        @if (! $user->mentor_available)
            <div class="alert-warning mt-6">You are not accepting new requests. <a href="{{ route('profile.edit') }}" class="link">Change availability</a>.</div>
        @endif

    @elseif ($user->isAdmin())
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card"><p class="stat-value">{{ $totals['users'] }}</p><p class="stat-label">Total users</p></div>
            <a href="{{ route('admin.employers.pending') }}" class="card-link"><p class="stat-value text-amber-700">{{ $totals['pending_employers'] }}</p><p class="stat-label">Employer approvals pending</p></a>
            <a href="{{ route('admin.jobs.pending') }}" class="card-link"><p class="stat-value text-amber-700">{{ $totals['pending_jobs'] }}</p><p class="stat-label">Listings pending review</p></a>
            <a href="{{ route('admin.verifications.index', ['status' => 'pending']) }}" class="card-link"><p class="stat-value text-amber-700">{{ $totals['pending_verifications'] }}</p><p class="stat-label">Credentials pending</p></a>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn-primary mt-6">Open admin dashboard</a>
    @endif
</x-app-layout>
