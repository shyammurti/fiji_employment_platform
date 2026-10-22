{{-- Module M2 Employer Portal, developed by Hritish Pawar (S11219177). --}}
<x-app-layout title="My listings" :breadcrumbs="['dashboard', ['My listings']]" help="employers">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="page-title">My listings</h1>
            <a href="{{ route('jobs.create') }}" class="btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z"/></svg>
                Post a job
            </a>
        </div>
    </x-slot>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Applications</th>
                    <th><span class="inline-flex items-center gap-1">Approval <x-tip>Pending approval: waiting for an administrator. Changes requested: read the review comments, edit and resubmit. Approved: visible on the job board. Rejected: not published.</x-tip></span></th>
                    <th><span class="inline-flex items-center gap-1">Status <x-tip>Active: accepting applications. Closed: no new applications, but you can still manage existing ones. Archived: withdrawn and kept for history.</x-tip></span></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($jobs as $job)
                    <tr>
                        <td class="font-medium text-slate-900">
                            <a href="{{ route('jobs.show', $job) }}" class="text-ocean-700 hover:underline">{{ $job->title }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $job->company }} &middot; {{ $job->vacancy_count }} {{ $job->vacancy_count === 1 ? 'position' : 'positions' }}@if ($job->closing_date) &middot; closes {{ $job->closing_date->format('d M Y') }}@endif</p>
                            @if ($job->admin_notes)<p class="mt-2 max-w-sm whitespace-pre-line text-xs text-amber-800"><strong>Review comments:</strong> {{ $job->admin_notes }}</p>@endif
                        </td>
                        <td>
                            <a href="{{ route('employer.jobs.applications', $job) }}" class="btn-secondary btn-sm tabular-nums" title="Review applications for {{ $job->title }}">{{ $job->applications_count }} {{ Str::plural('application', $job->applications_count) }}</a>
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
                            @elseif ($job->hasClosed())
                                <span class="inline-flex items-center gap-1"><span class="status-pill bg-amber-100 text-amber-800">Closing date passed</span><x-tip label="Why this listing is hidden">The closing date has passed, so the listing no longer appears on the job board and new applications are blocked. Edit it to set a later closing date, or complete recruitment.</x-tip></span>
                            @else
                                <span class="status-pill bg-success-100 text-success-700">Active</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                @if (! $job->is_archived && ! $job->recruitment_completed_at)
                                    <a href="{{ route('jobs.edit', $job) }}" class="btn-secondary btn-sm">Edit</a>
                                @endif
                                @if (! $job->is_archived)
                                    @if ($job->is_active)
                                        <form method="POST" action="{{ route('jobs.close', $job) }}"
                                              data-confirm="New applications for &quot;{{ $job->title }}&quot; will stop and current applicants will be told. You can still manage existing applications."
                                              data-confirm-title="Close this listing?" data-confirm-button="Close listing">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-secondary btn-sm">Close</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('jobs.archive', $job) }}"
                                          data-confirm="&quot;{{ $job->title }}&quot; will be withdrawn and every applicant notified. Application records are kept for history."
                                          data-confirm-title="Archive this listing?" data-confirm-button="Archive listing" data-confirm-tone="danger">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-danger-soft btn-sm">Archive</button>
                                    </form>
                                @endif
                            </div>
                            @if ($job->hired_count > 0 && $job->awaiting_outcome_count > 0 && ! $job->is_archived && ! $job->recruitment_completed_at)
                                <form method="POST" action="{{ route('jobs.complete', $job) }}" class="mt-3 max-w-sm space-y-2">
                                    @csrf @method('PATCH')
                                    <label class="flex gap-2 text-xs text-slate-600"><input type="checkbox" name="confirm_completion" value="1" required> I have finished selecting candidates. Reject and notify the {{ $job->awaiting_outcome_count }} remaining applicants.</label>
                                    <p class="text-xs text-slate-600">Submitting closes this vacancy. Hired applicants keep their outcome, and all applicants receive a closure notice.</p>
                                    <button type="submit" class="btn-secondary btn-sm">Complete recruitment</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-slate-500">No listings yet. <a href="{{ route('jobs.create') }}" class="link">Post your first job</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $jobs->links() }}</div>
</x-app-layout>
