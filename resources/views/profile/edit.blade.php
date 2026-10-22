<x-app-layout title="Your profile" :breadcrumbs="['dashboard', ['Your profile']]" :help="$user->isJobseeker() ? 'faq-how-do-i-complete-my-jobseeker-profile' : ($user->isEmployer() ? 'employers' : ($user->isMentor() ? 'faq-how-do-i-become-a-mentor' : 'account'))">
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">Your profile</h1>
            @if ($user->isJobseeker())
                @if ($user->is_verified)
                    <span class="badge-verified"><svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>Verified credential</span>
                @elseif ($user->degree_certificate_path)
                    <span class="status-pill bg-amber-100 text-amber-800">Credential {{ str_replace('_', ' ', $user->credential_status) }}</span>
                @endif
            @endif
        </div>
        <p class="page-intro">Keep your details current. {{ $user->isJobseeker() ? 'Employers search these details and the matching engine uses your skills.' : '' }}</p>
    </x-slot>

    <div class="space-y-6">
        @if ($user->admin_notes)
            <div class="alert-warning !mb-0"><strong>Administrator comments</strong><p class="mt-2 whitespace-pre-line">{{ $user->admin_notes }}</p></div>
        @endif

        @php($initialSkills = old('skills') !== null ? array_values(array_filter(array_map('trim', explode(',', old('skills'))))) : $user->skillsList())
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-6"
              x-data="{ skills: {{ Illuminate\Support\Js::from($initialSkills) }}, newSkill: '',
                  has(skill) { return this.skills.some(s => s.toLowerCase() === skill.toLowerCase()); },
                  toggle(skill) { this.has(skill) ? this.skills = this.skills.filter(s => s.toLowerCase() !== skill.toLowerCase()) : this.skills.push(skill); },
                  addCustom() { const s = this.newSkill.trim().replace(/,/g, ''); if (s && !this.has(s)) this.skills.push(s); this.newSkill = ''; } }">
            @csrf
            @method('PATCH')

            <section class="card" aria-labelledby="contact-title">
                <h2 id="contact-title" class="text-lg font-semibold text-fiji-900">Contact details</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="label">Full name</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" class="input" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        <x-field-error name="name" />
                    </div>
                    <div>
                        <label for="email" class="label">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" spellcheck="false" class="input" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        <x-field-error name="email" />
                        @if(is_string(old('email')) && str_ends_with(strtolower(old('email')), '@fmail.com'))
                            <label class="mt-2 flex items-start gap-2 text-sm text-amber-800"><input type="checkbox" name="confirm_email_domain" value="1" class="mt-1 rounded"> I checked the address and intentionally use fmail.com, not gmail.com.</label>
                        @endif
                    </div>
                    <div>
                        <label for="phone" class="label">Phone</label>
                        <input id="phone" type="tel" name="phone" @required($user->isJobseeker()) value="{{ old('phone', $user->phone) }}" autocomplete="tel" inputmode="tel" class="input" placeholder="+679 7XXXXXX" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                        <x-field-error name="phone" />
                    </div>
                    <div>
                        <label for="region" class="label">Region</label>
                        <select name="region" @required($user->isJobseeker()) class="input" id="region" @error('region') aria-invalid="true" aria-describedby="region-error" @enderror>
                            <option value="">Select region</option>
                            @foreach (['Central Division', 'Western Division', 'Northern Division', 'Eastern Division'] as $region)
                                <option value="{{ $region }}" @selected(old('region', $user->region) === $region)>{{ $region }}</option>
                            @endforeach
                        </select>
                        <x-field-error name="region" />
                    </div>
                </div>

            </section>

            @if ($user->isEmployer())
                <section class="card" aria-labelledby="business-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="business-title" class="text-lg font-semibold text-fiji-900">Business details</h2>
                        <span class="status-pill {{ match ($user->employer_approval_status) { 'approved' => 'bg-success-100 text-success-700', 'rejected' => 'bg-red-100 text-red-700', default => 'bg-amber-100 text-amber-800' } }}">Employer {{ $user->employer_approval_status }}</span>
                    </div>
                    <p class="hint mb-5">These details fill new job listings automatically. {{ $user->employer_approval_status === 'rejected' ? 'Upload a corrected registration certificate to send your request back for review.' : '' }}</p>
                    @include('profile.partials.employer-fields', ['user' => $user])
                </section>
            @endif

            @if ($user->isJobseeker())
                <section class="card" aria-labelledby="background-title">
                    <h2 id="background-title" class="text-lg font-semibold text-fiji-900">Background</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="qualification_level" class="label">Highest qualification <x-tip align="start">Employers can filter candidates by this level, and it appears in the CV the Career Hub builds for you.</x-tip></label>
                            <select name="qualification_level" required class="input" id="qualification_level" @error('qualification_level') aria-invalid="true" aria-describedby="qualification_level-error" @enderror>
                                <option value="">Select level</option>
                                @foreach (['Certificate', 'Diploma', 'Bachelor', 'Postgraduate'] as $level)
                                    <option value="{{ $level }}" @selected(old('qualification_level', $user->qualification_level) === $level)>{{ $level }}</option>
                                @endforeach
                            </select>
                            <x-field-error name="qualification_level" />
                        </div>
                        <div>
                            <label for="years_experience" class="label">Years of experience</label>
                            <input id="years_experience" type="number" min="0" max="60" inputmode="numeric" name="years_experience" required value="{{ old('years_experience', $user->years_experience) }}" class="input" @error('years_experience') aria-invalid="true" aria-describedby="years_experience-error" @enderror>
                            <x-field-error name="years_experience" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="about_me" class="label">About me <span class="font-normal text-slate-600">(optional)</span></label>
                            <textarea id="about_me" name="about_me" rows="3" maxlength="1000" class="input" placeholder="A short summary of your strengths and the work you are looking for…">{{ old('about_me', $user->about_me) }}</textarea>
                            <p class="hint">Shown to employers and used as the starting summary in the Career Hub CV Builder.</p>
                            <x-field-error name="about_me" />
                        </div>
                    </div>
                </section>

                <section class="card" aria-labelledby="skills-title">
                    <h2 id="skills-title" class="flex items-center gap-1 text-lg font-semibold text-fiji-900">Skills <x-tip align="start">Select skills from the list or type your own and press Enter. Your skill match score for each job compares these skills with the job's required skills.</x-tip></h2>
                    <p class="hint">Choose from the list or add your own. These tags power skill matching against job listings.</p>

                    <div class="mt-4 min-h-12 rounded-xl border border-dashed border-slate-300 p-3" aria-live="polite">
                        <p x-show="!skills.length" class="text-sm text-slate-600">No skills added yet.</p>
                        <ul class="flex flex-wrap gap-2" aria-label="Your skills">
                            <template x-for="(skill, i) in skills" :key="skill">
                                <li class="status-pill bg-brand py-1 pl-3 pr-1 text-white">
                                    <span x-text="skill"></span>
                                    <button type="button" class="ml-1 flex h-5 w-5 items-center justify-center rounded-full hover:bg-white/20" @click="skills.splice(i, 1)" :aria-label="'Remove ' + skill">&times;</button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <label for="new-skill" class="sr-only">Add a custom skill</label>
                        <input id="new-skill" type="text" x-model="newSkill" @keydown.enter.prevent="addCustom()" maxlength="60" autocomplete="off" placeholder="Type a custom skill, then press Enter…" class="input !mt-0">
                        <button type="button" class="btn-secondary" @click="addCustom()">Add</button>
                    </div>

                    <div class="mt-5 space-y-4">
                        @foreach (\App\Support\SkillCatalog::GROUPS as $group => $groupSkills)
                            <fieldset>
                                <legend class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $group }}</legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($groupSkills as $skill)
                                        <button type="button" @click="toggle(@js($skill))" :aria-pressed="has(@js($skill))"
                                                class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                                :class="has(@js($skill)) ? 'border-ocean-700 bg-ocean-50 text-ocean-800' : 'border-slate-300 text-slate-700 hover:border-ocean-500'">{{ $skill }}</button>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <input type="hidden" name="skills" :value="skills.join(',')">
                    <input type="hidden" name="custom_skills" value="">
                    <x-field-error name="skills" />
                </section>

                <section class="card" aria-labelledby="credential-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="credential-title" class="flex items-center gap-1 text-lg font-semibold text-fiji-900">Academic credential <x-tip align="start">Pending: waiting for an administrator. Verified: approved, and employers see a Verified badge. Rejected or More information: read the reviewer comment and upload a corrected document.</x-tip></h2>
                        <span class="status-pill {{ $user->is_verified ? 'bg-success-100 text-success-700' : 'bg-amber-100 text-amber-800' }}">{{ str($user->credential_status)->replace('_', ' ')->title() }}</span>
                    </div>
                    @if($user->credential_notes)<div class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><strong>Reviewer comment:</strong> {{ $user->credential_notes }}</div>@endif
                    @if ($user->degree_certificate_path)
                        <p class="mt-3 text-sm text-slate-600">A file is on record. <a href="{{ route('credentials.document', $user) }}" target="_blank" rel="noopener" class="link">View current file</a>. Uploading a new one restarts verification.</p>
                    @endif
                    <label for="degree_certificate" class="label mt-4">{{ $user->degree_certificate_path ? 'Replace credential' : 'Upload credential' }}</label>
                    <input id="degree_certificate" type="file" name="degree_certificate" @required(! $user->degree_certificate_path) accept=".pdf,.jpg,.jpeg,.png" class="input" aria-describedby="credential-hint" @error('degree_certificate') aria-invalid="true" @enderror>
                    <p id="credential-hint" class="hint">PDF, JPG or PNG, up to 5&nbsp;MB. Stored privately and reviewed by an administrator.</p>
                    <x-field-error name="degree_certificate" />
                </section>
            @endif

            @if ($user->isMentor())
                <section class="card space-y-4" aria-labelledby="mentor-title">
                    <h2 id="mentor-title" class="text-lg font-semibold text-fiji-900">Mentor profile</h2>
                    <label class="flex items-center gap-3 rounded-xl bg-ocean-50 p-4 text-sm"><input type="checkbox" name="mentor_available" value="1" class="rounded text-ocean-700" @checked(old('mentor_available', $user->mentor_available))> I am accepting new mentorship session requests.</label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="mentor_expertise" class="label">Area of expertise</label>
                            <input id="mentor_expertise" type="text" name="mentor_expertise" required value="{{ old('mentor_expertise', $user->mentor_expertise) }}" maxlength="255" class="input" @error('mentor_expertise') aria-invalid="true" aria-describedby="mentor_expertise-error" @enderror>
                            <x-field-error name="mentor_expertise" />
                        </div>
                        <div>
                            <label for="mentor_industry" class="label">Industry background</label>
                            <input id="mentor_industry" type="text" name="mentor_industry" value="{{ old('mentor_industry', $user->mentor_industry) }}" maxlength="255" class="input" placeholder="e.g. Hotels and resorts…">
                            <x-field-error name="mentor_industry" />
                        </div>
                    </div>
                    <div>
                        <label for="mentor_bio" class="label">Professional bio</label>
                        <textarea name="mentor_bio" rows="4" required maxlength="2000" class="input" id="mentor_bio" @error('mentor_bio') aria-invalid="true" aria-describedby="mentor_bio-error" @enderror>{{ old('mentor_bio', $user->mentor_bio) }}</textarea>
                        <x-field-error name="mentor_bio" />
                    </div>
                </section>
            @endif

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Save profile</button>
            </div>
        </form>

        <section class="card border-red-200" aria-labelledby="delete-title">
            <h2 id="delete-title" class="text-lg font-semibold text-red-800">Delete account</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">Confirm your password to permanently remove your account and uploaded files. Accounts with shared application or mentorship records cannot be deleted here, so other participants keep their history.</p>
            <form method="POST" action="{{ route('profile.destroy') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" data-confirm="Your account and uploaded files will be permanently removed. This cannot be undone." data-confirm-title="Delete your account?" data-confirm-button="Delete my account" data-confirm-tone="danger">
                @csrf
                @method('DELETE')
                <div class="flex-1 sm:max-w-sm">
                    <label for="delete-password" class="label">Current password</label>
                    <input id="delete-password" type="password" name="password" required autocomplete="current-password" class="input" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    <x-field-error name="password" />
                </div>
                <button type="submit" class="btn-danger">Delete account</button>
            </form>
        </section>
    </div>
</x-app-layout>
