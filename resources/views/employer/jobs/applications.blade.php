<x-app-layout :title="'Applications: '.$job->title" :breadcrumbs="['dashboard', ['My listings', route('employer.jobs.index')], [$job->title]]" help="faq-how-do-i-finish-recruitment">
    <x-slot name="header">
        <h1 class="page-title">Applications for {{ $job->title }}</h1>
        <p class="page-intro">Sorted by skill match. Changing a status or interview time notifies the applicant by email and in-app.</p>
    </x-slot>

    <div class="space-y-4">
        @forelse ($applications as $app)
            <article class="card" x-data="{ open: {{ $errors->any() && old('application_id') == $app->id ? 'true' : 'false' }}, status: @js(old('status', $app->status)) }" aria-labelledby="applicant-{{ $app->id }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="applicant-{{ $app->id }}" class="flex flex-wrap items-center gap-2 font-semibold text-fiji-900">
                            {{ $app->jobseeker->name }}
                            @if ($app->jobseeker->is_verified)<span class="badge-verified">Verified</span>@endif
                        </h2>
                        <p class="mt-1 break-all text-sm text-slate-600">{{ $app->jobseeker->email ?: $app->jobseeker->phone }}. {{ $app->jobseeker->region }}</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-ocean-700">{{ $app->match_percentage }}% skill match</p>
                    </div>
                    <div class="text-right">
                        <span class="status-pill {{ $app->statusColor() }}">{{ $app->status }}</span>
                        @if ($app->interview_at)<p class="mt-2 text-xs text-slate-600">Interview {{ $app->interview_at->timezone('Pacific/Fiji')->format('d M Y, g:i A') }}</p>@endif
                    </div>
                </div>

                @if ($app->jobseeker->skillsList())
                    <ul class="mt-3 flex flex-wrap gap-2" aria-label="Applicant skills">
                        @foreach ($app->jobseeker->skillsList() as $skill)<li class="skill-chip">{{ $skill }}</li>@endforeach
                    </ul>
                @endif
                @if ($app->cover_letter)
                    <details class="mt-3 text-sm"><summary class="cursor-pointer font-medium text-ocean-700">Cover letter</summary><p class="mt-2 whitespace-pre-line text-slate-700">{{ $app->cover_letter }}</p></details>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('applications.resume', $app) }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">View resume</a>
                    @if ($app->jobseeker->degree_certificate_path)
                        <a href="{{ route('credentials.document', $app->jobseeker) }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">View credential</a>
                    @endif
                    @if (! $job->is_archived && ! $job->recruitment_completed_at)
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="status-form-{{ $app->id }}" class="btn-primary btn-sm">Update status</button>
                    @else
                        <span class="text-sm text-slate-600">Recruitment record retained</span>
                    @endif
                </div>

                <form id="status-form-{{ $app->id }}" x-show="open" x-cloak method="POST" action="{{ route('employer.applications.update', $app) }}" class="mt-4 space-y-3 rounded-xl bg-slate-50 p-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="application_id" value="{{ $app->id }}">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="status-{{ $app->id }}" class="label">Status <x-tip align="start">Changing the status or interview time emails and notifies the applicant, and adds a step to their status history. Choose Interview Scheduled to set a date and time.</x-tip></label>
                            <select id="status-{{ $app->id }}" name="status" class="input" x-model="status">
                                @foreach (\App\Models\JobApplication::STATUSES as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="status === 'Interview Scheduled'">
                            <label for="interview-{{ $app->id }}" class="label">Interview date and time (Fiji)</label>
                            <input id="interview-{{ $app->id }}" type="datetime-local" name="interview_at" data-future step="900" value="{{ old('interview_at', $app->interview_at?->timezone('Pacific/Fiji')->format('Y-m-d\TH:i')) }}" :required="status === 'Interview Scheduled'" :disabled="status !== 'Interview Scheduled'" class="input">
                        </div>
                    </div>
                    <div>
                        <label for="notes-{{ $app->id }}" class="label">Internal notes <span class="font-normal text-slate-600">(not shared with the applicant)</span> <x-tip align="end">Private notes for your hiring team. Saving only notes does not notify the applicant.</x-tip></label>
                        <textarea id="notes-{{ $app->id }}" name="employer_notes" rows="2" maxlength="2000" class="input">{{ $app->employer_notes }}</textarea>
                    </div>
                    <button type="submit" class="btn-primary">Save</button>
                </form>
            </article>
        @empty
            <div class="card py-12 text-center">
                <h2 class="text-lg font-semibold text-fiji-900">No applications yet</h2>
                <p class="mt-2 text-sm text-slate-600">Applications appear here once jobseekers apply to this vacancy.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $applications->links() }}</div>
</x-app-layout>
