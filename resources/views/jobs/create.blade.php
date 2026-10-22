{{-- Module M2 Employer Portal, developed by Hritish Pawar (S11219177). --}}
<x-app-layout :title="$job->exists ? 'Edit listing' : 'Post a job'" :breadcrumbs="['dashboard', ['My listings', route('employer.jobs.index')], [$job->exists ? 'Edit listing' : 'Post a job']]" :help="$job->exists ? 'faq-how-do-i-correct-a-vacancy' : 'faq-how-do-i-publish-a-vacancy'">
    <x-slot name="header">
        <h1 class="page-title">{{ $job->exists ? 'Edit listing' : 'Post a job' }}</h1><p class="page-intro">Listings are reviewed by an administrator before they appear on the public job board.</p>
    </x-slot>

    @if ($job->admin_notes)
        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Administrator comments</strong><p class="mt-2 whitespace-pre-line">{{ $job->admin_notes }}</p><p class="mt-2">Address these comments, then resubmit your listing for review.</p></div>
    @endif
    <form method="POST" action="{{ $job->exists ? route('jobs.update', $job) : route('jobs.store') }}" enctype="multipart/form-data" class="card space-y-5">
        @csrf
        @if ($job->exists) @method('PATCH') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="title" class="label">Job Title</label>
                        <input aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" @error('title') aria-describedby="title-error" @enderror id="title" type="text" name="title" value="{{ old('title', $job->title) }}" required class="input">
                        <x-field-error name="title" />
            </div>
            <div>
                <label for="company" class="label">Company</label>
                        <input id="company" type="text" name="company" value="{{ auth()->user()->company_name }}" readonly class="input bg-slate-50" aria-label="Company from employer profile">
                <p class="mt-1 text-xs text-slate-500">Filled from your employer profile. <a href="{{ route('profile.edit') }}" class="underline">Update company details</a>.</p>
            </div>
            <div>
                <label for="location" class="label">Location (town/city)</label>
                        <input aria-invalid="{{ $errors->has('location') ? 'true' : 'false' }}" @error('location') aria-describedby="location-error" @enderror id="location" type="text" name="location" value="{{ old('location', $job->location) }}" required class="input">
                        <x-field-error name="location" />
            </div>
            <div>
                <label for="region" class="label">Region</label>
                        <select name="region" class="input" id="region" aria-invalid="{{ $errors->has('region') ? 'true' : 'false' }}" @error('region') aria-describedby="region-error" @enderror>
                    @foreach (['Central Division', 'Western Division', 'Northern Division', 'Eastern Division'] as $region)
                        <option value="{{ $region }}" @selected(old('region', $job->region) === $region)>{{ $region }}</option>
                    @endforeach
                </select>
                        <x-field-error name="region" />
            </div>
            <div>
                <label for="industry_sector" class="label">Industry Sector</label>
                        <input id="industry_sector" type="text" name="industry_sector" value="{{ auth()->user()->industry_sector }}" readonly class="input bg-slate-50" aria-label="Industry from employer profile">
            </div>
            <div>
                <label for="type" class="label">Employment Type</label>
                        <select name="type" class="input" required id="type" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" @error('type') aria-describedby="type-error" @enderror>
                    @foreach (['Full-time', 'Part-time', 'Contract', 'Remote'] as $type)
                        <option value="{{ $type }}" @selected(old('type', $job->type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                        <x-field-error name="type" />
            </div>
            <div>
                <label for="salary_range" class="label">Salary range <x-tip align="start">Include the currency, an amount and the pay period, for example FJD 12,000 - 15,000 per year or FJD 6.00 per hour.</x-tip></label>
                <input id="salary_range" type="text" name="salary_range" value="{{ old('salary_range', $job->salary_range) }}" required maxlength="100" placeholder="e.g. FJD 10,000 - 13,000 per year…" class="input" @error('salary_range') aria-invalid="true" aria-describedby="salary_range-error" @enderror>
                <p class="hint">Include the currency and pay period.</p>
                <x-field-error name="salary_range" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="vacancy-count" class="label">Positions <x-tip align="start">How many people you will hire for this vacancy. Complete recruitment becomes available once you have recorded at least one hire.</x-tip></label>
                    <input id="vacancy-count" type="number" name="vacancy_count" min="1" max="1000" inputmode="numeric" value="{{ old('vacancy_count', $job->vacancy_count ?? 1) }}" required class="input">
                    <x-field-error name="vacancy_count" />
                </div>
                <div>
                    <label for="closing_date" class="label">Closing date <x-tip align="end">The last day applications are accepted (Fiji time). After this date the listing leaves the job board automatically. It must be within the next 12 months.</x-tip></label>
                    <input id="closing_date" type="date" name="closing_date" data-future value="{{ old('closing_date', $job->closing_date?->format('Y-m-d')) }}" min="{{ now('Pacific/Fiji')->addDay()->format('Y-m-d') }}" max="{{ now('Pacific/Fiji')->addYear()->format('Y-m-d') }}" required class="input" @error('closing_date') aria-invalid="true" aria-describedby="closing_date-error" @enderror>
                    <x-field-error name="closing_date" />
                </div>
            </div>
        </div>

        <div>
            <label for="description" class="label">About the role</label>
            <textarea name="description" rows="5" required minlength="50" maxlength="20000" class="input" id="description" placeholder="Purpose of the role, team, working hours and location details…" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $job->description) }}</textarea>
            <p class="hint">At least 50 characters.</p>
            <x-field-error name="description" />
        </div>

        <div>
            <label for="responsibilities" class="label">Key responsibilities</label>
            <textarea name="responsibilities" rows="5" required minlength="30" maxlength="5000" class="input" id="responsibilities" placeholder="One duty per line, e.g.&#10;Greet guests and manage check-in&#10;Resolve booking issues…" @error('responsibilities') aria-invalid="true" aria-describedby="responsibilities-error" @enderror>{{ old('responsibilities', $job->responsibilities) }}</textarea>
            <x-field-error name="responsibilities" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="required_degree" class="label">Required qualification</label>
                <input id="required_degree" type="text" name="required_degree" value="{{ old('required_degree', $job->required_degree) }}" required maxlength="255" list="qualification-options" placeholder="e.g. Diploma in Hospitality or equivalent…" class="input" @error('required_degree') aria-invalid="true" aria-describedby="required_degree-error" @enderror>
                <datalist id="qualification-options">@foreach (['Certificate or equivalent experience', 'Diploma or equivalent', 'Bachelor degree', 'Postgraduate qualification'] as $option)<option value="{{ $option }}">@endforeach</datalist>
                <x-field-error name="required_degree" />
            </div>
            <div>
                <label for="required_skills" class="label">Required skills <x-tip align="end">Separate skills with commas. Applicants are scored by how many of these skills are on their profile, and matching jobseekers are notified when the listing is approved.</x-tip></label>
                <input id="required_skills" type="text" name="required_skills" value="{{ old('required_skills', implode(', ', $job->requiredSkillsList())) }}" required placeholder="e.g. Customer Service, Microsoft Excel…" class="input" @error('required_skills') aria-invalid="true" aria-describedby="required_skills-error" @enderror>
                <p class="hint">Separate with commas. Use platform skill names where possible, such as {{ implode(', ', array_slice(\App\Support\SkillCatalog::all(), 0, 4)) }}.</p>
                <x-field-error name="required_skills" />
            </div>
        </div>

        <div>
            <label for="job-document" class="label">Position description document <x-tip align="start">Required for administrator review. Applicants can download it once the listing is approved. PDF, DOC or DOCX up to 5 MB.</x-tip></label>
            @if ($job->document_path)
                <p class="mt-1 text-sm text-slate-600">On file: <a href="{{ route('jobs.document', $job) }}" class="link">{{ $job->document_name }}</a>. Upload a new file only to replace it.</p>
            @endif
            <input id="job-document" type="file" name="job_document" accept=".pdf,.doc,.docx" @required(! $job->document_path) class="input" aria-describedby="job-document-hint" @error('job_document') aria-invalid="true" @enderror>
            <p id="job-document-hint" class="hint">PDF, DOC or DOCX, up to 5&nbsp;MB. Required for administrator review; applicants can download it once the listing is approved.</p>
            <x-field-error name="job_document" />
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-600">An administrator reviews every listing before it appears publicly.</p>
            <button type="submit" class="btn-primary">{{ $job->exists ? 'Save and resubmit' : 'Submit for approval' }}</button>
        </div>
    </form>
</x-app-layout>
