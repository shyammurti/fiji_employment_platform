{{-- Module M4 Application Tracking & Skill Matching, developed by Ammara Dean (S11220213). --}}
<x-app-layout :title="$job->title" :breadcrumbs="[['Browse jobs', route('jobs.index')], [$job->title]]" help="faq-how-do-i-apply">
    <x-slot name="header">
        <h1 class="page-title">{{ $job->title }}</h1>
        <p class="mt-2 text-slate-600">{{ $job->company }}, {{ $job->location }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <span class="status-pill bg-ocean-50 text-ocean-700">{{ $job->type }}</span>
            @if ($job->region)<span class="skill-chip">{{ $job->region }}</span>@endif
            @if ($job->industry_sector)<span class="skill-chip">{{ $job->industry_sector }}</span>@endif
            <span class="skill-chip tabular-nums">{{ $job->vacancy_count }} {{ $job->vacancy_count === 1 ? 'position' : 'positions' }}</span>
            @if ($job->closing_date)<span class="status-pill {{ $job->hasClosed() ? 'bg-red-100 text-red-700' : 'bg-amber-50 text-amber-900' }}">{{ $job->hasClosed() ? 'Closed' : 'Closes' }} {{ $job->closing_date->format('d M Y') }}</span>@endif
        </div>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <article class="card lg:col-span-2">
            @if ($job->is_archived || ! $job->is_active || $job->approval_status !== 'approved')
                <div class="alert-warning" role="status"><strong>{{ $job->is_archived ? 'Listing archived' : (! $job->is_active ? 'Applications closed' : 'Listing under review') }}</strong><p class="mt-1">{{ $job->closure_reason ?: 'This listing is not accepting applications. Existing application records remain available.' }}</p></div>
            @endif
            <h2 class="text-lg font-semibold text-fiji-900">About the role</h2>
            <p class="mt-3 max-w-[70ch] whitespace-pre-line leading-7 text-slate-700">{{ $job->description }}</p>

            @if ($job->responsibilities)
                <h2 class="mt-8 text-lg font-semibold text-fiji-900">Key responsibilities</h2>
                <ul class="mt-3 max-w-[70ch] list-disc space-y-1 pl-5 leading-7 text-slate-700">
                    @foreach (preg_split('/\R/',trim($job->responsibilities)) as $duty)
                        @if (trim($duty) !== '')<li>{{ ltrim(trim($duty), '-*• ') }}</li>@endif
                    @endforeach
                </ul>
            @endif

            @if ($job->required_degree)
                <h2 class="mt-8 text-lg font-semibold text-fiji-900">Required qualification</h2>
                <p class="mt-2 text-slate-700">{{ $job->required_degree }}</p>
            @endif

            @if ($job->requiredSkillsList())
                <h2 class="mt-8 text-lg font-semibold text-fiji-900">Required skills</h2>
                <ul class="mt-3 flex flex-wrap gap-2" aria-label="Required skills">
                    @foreach ($job->requiredSkillsList() as $skill)
                        <li class="skill-chip">{{ $skill }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($job->document_path)
                <h2 class="mt-8 text-lg font-semibold text-fiji-900">Supporting document</h2>
                <a href="{{ route('jobs.document', $job) }}" class="link mt-2 inline-block text-sm">Download {{ $job->document_name }}</a>
            @endif
        </article>

        <aside class="space-y-6 lg:sticky lg:top-24">
            <div class="card">
                @if ($job->salary_range)
                    <p class="text-sm text-slate-600">Salary</p>
                    <p class="mb-5 font-semibold text-fiji-900">{{ $job->salary_range }}</p>
                @endif

                @auth
                    @if (auth()->user()->isJobseeker())
                        @if ($alreadyApplied)
                            <p class="rounded-xl bg-ocean-50 p-4 text-sm text-ocean-800">You have applied to this vacancy. <a href="{{ route('applications.mine') }}" class="link">Track your application</a>.</p>
                        @elseif ($job->isAcceptingApplications())
                            <h2 class="text-lg font-semibold text-fiji-900">Apply for this role</h2>
                            <form method="POST" action="{{ route('jobs.apply', $job) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                                @csrf
                                <div>
                                    <label for="resume" class="label">Resume or CV <x-tip align="start">Upload a PDF, DOC or DOCX up to 5 MB. Only this employer and administrators can open it. You can build one in the Career Hub with Build my CV.</x-tip></label>
                                    <input id="resume" type="file" name="resume" required accept=".pdf,.doc,.docx" class="input" aria-describedby="resume-hint" @error('resume') aria-invalid="true" @enderror>
                                    <p id="resume-hint" class="hint">PDF, DOC or DOCX, up to 5&nbsp;MB.</p>
                                    <x-field-error name="resume" />
                                </div>
                                <div>
                                    <label for="cover_letter" class="label">Cover letter <span class="font-normal text-slate-600">(optional)</span></label>
                                    <textarea id="cover_letter" name="cover_letter" rows="5" maxlength="5000" class="input" placeholder="Why are you a good fit for this role?…">{{ old('cover_letter') }}</textarea>
                                    <x-field-error name="cover_letter" />
                                </div>
                                <button type="submit" class="btn-primary w-full">Submit application</button>
                            </form>
                        @else
                            <p class="text-sm text-slate-600">This listing is not accepting applications.</p>
                        @endif
                    @else
                        <p class="text-sm text-slate-600">Only jobseeker accounts can apply to vacancies.</p>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-primary w-full">Log in to apply</a>
                    <p class="mt-3 text-center text-sm text-slate-600">New here? <a href="{{ route('register') }}" class="link">Create an account</a></p>
                @endauth
            </div>

            @if ($match)
                <div class="card">
                    <div class="flex items-baseline justify-between">
                        <h2 class="flex items-center gap-1 font-semibold text-fiji-900">Your skill match <x-tip label="How skill match is calculated">The share of this job's required skills found on your profile. Skills are matched ignoring capitals and spaces. Add any missing skills you genuinely have to improve your match.</x-tip></h2>
                        <p class="text-2xl font-bold tabular-nums {{ $match['score'] >= 70 ? 'text-success-700' : ($match['score'] >= 40 ? 'text-amber-800' : 'text-slate-700') }}">{{ $match['score'] }}%</p>
                    </div>
                    @if ($match['matched'])
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-600">You have</p>
                        <ul class="mt-2 flex flex-wrap gap-2">@foreach ($match['matched'] as $skill)<li class="status-pill bg-success-100 text-success-700">{{ $skill }}</li>@endforeach</ul>
                    @endif
                    @if ($match['missing'])
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-600">Not on your profile yet</p>
                        <ul class="mt-2 flex flex-wrap gap-2">@foreach ($match['missing'] as $skill)<li class="skill-chip">{{ $skill }}</li>@endforeach</ul>
                    @endif
                    <p class="hint mt-4">The score is guidance, not a hiring decision. <a href="{{ route('profile.edit') }}" class="link">Update your skills</a> if any are missing.</p>
                </div>
            @endif
        </aside>
    </div>
</x-app-layout>
