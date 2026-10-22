<x-app-layout :title="'Review: '.$job->title" :breadcrumbs="['dashboard', ['Job listings', route('admin.jobs.pending')], ['Review: '.$job->title]]" help="faq-what-should-i-review-before-approval">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="page-title">Review Job Listing</h1><p class="text-sm text-slate-500">Review the full submission before making a decision.</p></div>
            <a href="{{ route('admin.jobs.pending', ['status' => 'all']) }}" class="btn-secondary">All listings</a>
        </div>
    </x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <h3 class="text-xl font-semibold text-slate-900">{{ $job->title }}</h3>
                <p class="mt-1 text-sm text-slate-500">Listing #{{ $job->id }} &middot; {{ str($job->approval_status)->replace('_', ' ')->title() }} &middot; {{ $job->is_archived ? 'Archived' : ($job->is_active ? 'Open' : 'Closed') }}</p>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    @foreach (['Company' => $job->company, 'Location' => $job->location, 'Region' => $job->region, 'Industry' => $job->industry_sector, 'Employment type' => $job->type, 'Salary' => $job->salary_range ?: 'Not specified', 'Positions available' => $job->vacancy_count, 'Closing date' => $job->closing_date?->format('d M Y') ?? 'Not set', 'Applications received' => $job->applications_count, 'Submitted' => $job->created_at->format('d M Y, g:i A'), 'Last updated' => $job->updated_at->format('d M Y, g:i A')] as $label => $value)
                        <div><dt class="font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 text-slate-900">{{ $value ?? 'Not provided' }}</dd></div>
                    @endforeach
                </dl>
                <h4 class="mt-6 font-semibold text-slate-900">Full job description</h4>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $job->description }}</p>
                <h4 class="mt-6 font-semibold text-fiji-900">Key responsibilities</h4>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $job->responsibilities ?: 'Not provided' }}</p>
                <h4 class="mt-6 font-semibold text-slate-900">Required qualification</h4>
                <p class="mt-2 text-sm text-slate-700">{{ $job->required_degree ?: 'Not specified' }}</p>
                <h4 class="mt-6 font-semibold text-slate-900">Required skills</h4>
                <p class="mt-2 text-sm text-slate-700">{{ implode(', ', $job->requiredSkillsList()) ?: 'Not specified' }}</p>
                <h4 class="mt-6 font-semibold text-slate-900">Supporting document</h4>
                @if ($job->document_path)
                    <a href="{{ route('jobs.document', $job) }}" class="mt-2 inline-block text-sm text-ocean-700 underline">Download {{ $job->document_name }}</a>
                @else
                    <p class="mt-2 text-sm text-slate-500">No document attached.</p>
                @endif
            </div>
            @if ($job->admin_notes)
                <div class="card border-amber-200"><h3 class="font-semibold">Latest review comments</h3><p class="mt-2 whitespace-pre-line text-sm">{{ $job->admin_notes }}</p></div>
            @endif
            @if ($job->closure_reason)
                <div class="card"><h3 class="font-semibold">Closure information</h3><p class="mt-2 text-sm">{{ $job->closure_reason }}</p></div>
            @endif
        </div>
        <div class="space-y-6">
            <div class="card">
                <h3 class="font-semibold text-slate-900">Submitted by</h3>
                <p class="mt-2 text-sm">{{ $job->employer->name }}</p><p class="mt-1 break-all text-sm">{{ $job->employer->email }}</p>
                <p class="mt-1 text-sm">{{ $job->employer->phone ?: 'Phone not provided' }}</p>
                <p class="mt-3 text-sm">Registered company: {{ $job->employer->company_name }}</p>
                <p class="mt-1 text-sm">Employer approval: {{ ucfirst($job->employer->employer_approval_status) }}</p>
            </div>
            @if (! $job->is_archived && $job->approval_status === 'pending_approval')
                <div class="card space-y-5">
                    <h3 class="font-semibold text-slate-900">Review decision</h3>
                    <form method="POST" action="{{ route('admin.jobs.approve', $job) }}">@csrf @method('PATCH')<button class="btn-primary">Approve listing</button></form>
                    <form method="POST" action="{{ route('admin.jobs.requestChanges', $job) }}" class="space-y-2">
                        @csrf @method('PATCH')
                        <label for="change-comments" class="label">Changes needed</label>
                        <textarea id="change-comments" name="admin_notes" required maxlength="2000" rows="4" class="input" placeholder="Explain what is missing or needs correction.">{{ old('admin_notes') }}</textarea>
                        <button class="btn-secondary">Request changes and resubmission</button>
                    </form>
                    <form method="POST" action="{{ route('admin.jobs.reject', $job) }}" class="space-y-2">
                        @csrf @method('PATCH')
                        <label for="reject-comments" class="label">Reason for rejection</label>
                        <textarea id="reject-comments" name="admin_notes" required maxlength="2000" rows="3" class="input">{{ old('admin_notes') }}</textarea>
                        <button class="btn-danger-soft">Reject with comments</button>
                    </form>
                </div>
            @endif
            @if (! $job->is_archived)
                <form method="POST" action="{{ route('admin.jobs.archive', $job) }}" class="card space-y-3" data-confirm="The listing is removed from the job board and the employer and every applicant are notified with your reason. Application records are kept." data-confirm-title="Archive this listing?" data-confirm-button="Archive and notify" data-confirm-tone="danger">
                    @csrf @method('PATCH')
                    <h3 class="font-semibold text-slate-900">Withdraw listing</h3>
                    <p class="text-sm text-slate-500">Archive unavailable or unsuitable vacancies. Applicants keep their application history and receive your explanation.</p>
                    <label for="closure-reason" class="label">Reason sent to the employer and applicants</label>
                    <textarea id="closure-reason" name="closure_reason" required maxlength="1000" rows="3" class="input">{{ old('closure_reason') }}</textarea>
                    <button class="btn-danger-soft">Archive and notify</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
