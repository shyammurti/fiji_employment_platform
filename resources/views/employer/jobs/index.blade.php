<x-app-layout title="My listings">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="page-title">My listings</h1>
            <a href="{{ route('jobs.create') }}" class="btn-primary">Post a job</a>
        </div>
    </x-slot>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Applications</th>
                    <th>Approval</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($jobs as $job)
                    <tr>
                        <td class="font-medium text-slate-900">
                            <a href="{{ route('jobs.show', $job) }}" class="text-ocean-700 hover:underline">{{ $job->title }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $job->company }} &middot; {{ $job->vacancy_count }} {{ $job->vacancy_count === 1 ? 'position' : 'positions' }}</p>
                            @if ($job->admin_notes)<p class="mt-2 max-w-sm whitespace-pre-line text-xs text-amber-800"><strong>Review comments:</strong> {{ $job->admin_notes }}</p>@endif
                        </td>
                        <td>
                            <a href="{{ route('employer.jobs.applications', $job) }}" class="text-ocean-700 hover:underline">{{ $job->applications_count }}</a>
                        </td>
                        <td>
                            <span class="status-pill {{ $job->approval_status === 'approved' ? 'bg-success-100 text-success-700' : ($job->approval_status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ str($job->approval_status)->replace('_', ' ')->title() }}
                            </span>
                        </td>
                        <td>
                            @if ($job->is_archived)
                                <span class="status-pill bg-slate-100 text-slate-700">Archived</span>
                            @elseif (! $job->is_active)
                                <span class="status-pill bg-slate-100 text-slate-700">Closed</span>
                            @else
                                <span class="status-pill bg-success-100 text-success-700">Active</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                @if (! $job->is_archived && ! $job->recruitment_completed_at)
                                <a href="{{ route('jobs.edit', $job) }}" class="text-xs font-medium text-ocean-700 hover:underline">Edit</a>
                                @endif
                                @if (! $job->is_archived)
                                    @if ($job->is_active)
                                    <form method="POST" action="{{ route('jobs.close', $job) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="text-xs font-medium text-slate-600 hover:underline">Close</button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('jobs.archive', $job) }}" onsubmit="return confirm('Archive this listing?');">
                                        @csrf
                                        @method('PATCH')
                                        <button class="text-xs font-medium text-red-700 hover:underline">Archive</button>
                                    </form>
                                @endif
                            </div>
                            @if ($job->hired_count > 0 && $job->awaiting_outcome_count > 0 && ! $job->is_archived && ! $job->recruitment_completed_at)
                                <form method="POST" action="{{ route('jobs.complete', $job) }}" class="mt-3 max-w-sm space-y-2">
                                    @csrf @method('PATCH')
                                    <label class="flex gap-2 text-xs text-slate-600"><input type="checkbox" name="confirm_completion" value="1" required> I have finished selecting candidates. Reject and notify the {{ $job->awaiting_outcome_count }} remaining applicants.</label>
                                    <p class="text-xs text-slate-600">Submitting closes this vacancy. Hired applicants keep their outcome, and all applicants receive a closure notice.</p>
                                    <button class="btn-secondary">Complete recruitment</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">No listings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $jobs->links() }}</div>
</x-app-layout>
