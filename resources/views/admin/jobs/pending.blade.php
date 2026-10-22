<x-app-layout title="Job listings" :breadcrumbs="['dashboard', ['Job listings']]" help="faq-what-should-i-review-before-approval">
    <x-slot name="header"><h1 class="page-title">Job Listing Moderation</h1></x-slot>
    <nav aria-label="Listing status" class="mb-6 flex flex-wrap gap-2">
        @foreach (['pending_approval' => 'Pending review', 'changes_requested' => 'Changes requested', 'approved' => 'Approved', 'rejected' => 'Rejected', 'archived' => 'Archived', 'all' => 'All listings'] as $value => $label)
            <a href="{{ route('admin.jobs.pending', ['status' => $value]) }}" class="btn-secondary {{ $status === $value ? 'ring-2 ring-ocean-600' : '' }}" @if ($status === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="space-y-4">
        @forelse ($jobs as $job)
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $job->title }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $job->company }} &middot; {{ $job->location }} &middot; {{ $job->type }}</p>
                        <p class="mt-1 text-sm text-slate-500">Submitted by {{ $job->employer->name }} on {{ $job->created_at->format('d M Y') }}</p>
                        <p class="mt-2 text-sm">{{ $job->vacancy_count }} {{ $job->vacancy_count === 1 ? 'position' : 'positions' }} &middot; {{ $job->salary_range ?: 'Salary not specified' }} &middot; {{ $job->applications_count }} applications</p>
                        <p class="mt-2 text-sm text-slate-600">{{ Str::limit($job->description, 180) }}</p>
                        <span class="status-pill mt-3 bg-slate-100 text-slate-700">{{ $job->is_archived ? 'Archived' : str($job->approval_status)->replace('_', ' ')->title() }}</span>
                    </div>
                    <a href="{{ route('admin.jobs.review', $job) }}" class="btn-primary">View full details</a>
                </div>
            </div>
        @empty
            <div class="card"><p class="text-sm text-slate-500">No listings match this status. Choose another filter to review existing listings.</p></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $jobs->links() }}</div>
</x-app-layout>
