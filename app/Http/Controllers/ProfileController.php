<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\MentorshipSession;
use App\Models\User;
use App\Notifications\PlatformAlert;
use App\Support\EmployerDetails;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Module M1 Jobseeker Profile & Verification, developed by Sameet Singh (S11231364).
 */
class ProfileController extends Controller
{
    /**
     * Role-aware dashboard: each role sees the widgets relevant to it
     * (FR5.1 admin totals, FR2.x employer queue, FR4.3/4.5 jobseeker
     * tracking, FR3.4/3.5 mentor sessions).
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();

        $data = ['user' => $user];

        if ($user->isJobseeker()) {
            $data['applications'] = $user->jobApplications()
                ->with('jobListing')
                ->latest()
                ->take(5)
                ->get();
            $data['upcomingSessions'] = $user->menteeSessions()
                ->with('mentor')
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')
                ->take(3)
                ->get();
            // FR4.2: recommend open vacancies by skill overlap, not just recency.
            $matcher = app(\App\Services\SkillMatchService::class);
            $appliedIds = $user->jobApplications()->pluck('job_listing_id');
            $data['recommendedJobs'] = JobListing::publiclyVisible()->whereNotIn('id', $appliedIds)->latest()->take(50)->get()
                ->each(fn ($job) => $job->match_score = $matcher->calculate($user, $job))
                ->sortByDesc('match_score')->take(4)->values();
        } elseif ($user->isEmployer()) {
            $data['jobListings'] = $user->jobListings()->withCount('applications')->latest()->take(5)->get();
            $data['pendingApplicationsCount'] = JobApplication::whereIn(
                'job_listing_id',
                $user->jobListings()->pluck('id')
            )->whereIn('status', ['Submitted', 'Under Review'])->count();
        } elseif ($user->isMentor()) {
            $data['upcomingSessions'] = $user->mentorSessions()
                ->with('mentee')
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')
                ->get();
            $data['pendingSessions'] = $user->mentorSessions()->where('status', 'pending')->count();
        } elseif ($user->isAdmin()) {
            $data['totals'] = [
                'users' => \App\Models\User::count(),
                'pending_employers' => \App\Models\User::where('role', 'employer')->where('employer_approval_status', 'pending')->count(),
                'pending_jobs' => JobListing::pendingApproval()->count(),
                'pending_verifications' => \App\Models\User::where('role', 'jobseeker')->where('credential_status', 'pending')->whereNotNull('degree_certificate_path')->count(),
            ];
        }

        return view('dashboard', $data);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Handles FR1.4 (contact/skills/credential edits). Employer/mentor
     * specific fields are only persisted for those roles.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required_without:phone', 'nullable', 'string', 'max:255', new \App\Rules\EmailAddress($request->boolean('confirm_email_domain') || $request->input('email') === $user->email), 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'region' => ['nullable', 'string', 'max:100'],
            'qualification_level' => ['nullable', 'string', 'max:100'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skills' => ['nullable', 'string'], // comma-separated from the tag input
            'custom_skills' => ['nullable', 'string'],
            'degree_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'mentor_expertise' => ['nullable', 'string', 'max:255'],
            'mentor_bio' => ['nullable', 'string', 'max:2000'],
            'mentor_industry' => ['nullable', 'string', 'max:255'],
            'about_me' => ['nullable', 'string', 'max:1000'],
        ];

        if ($user->isJobseeker()) {
            $rules['phone'] = ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'];
            $rules['region'] = ['required', 'string', 'in:Central Division,Western Division,Northern Division,Eastern Division'];
            $rules['qualification_level'] = ['required', 'string', 'in:Certificate,Diploma,Bachelor,Postgraduate'];
            $rules['years_experience'] = ['required', 'integer', 'min:0', 'max:60'];
            $rules['skills'] = ['required', 'string', 'max:2000'];
            $rules['degree_certificate'] = [
                $user->degree_certificate_path ? 'nullable' : 'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ];
        }

        if ($user->isMentor()) {
            $rules['mentor_expertise'][0] = 'required';
            $rules['mentor_bio'][0] = 'required';
        }
        if ($user->isEmployer()) {
            $rules += EmployerDetails::rules(documentRequired: ! $user->business_document_path);
        }

        $validated = $request->validate($rules, EmployerDetails::messages() + [
            'phone.regex' => 'Enter a valid phone number using digits, spaces, brackets, +, or -.',
            'skills.required' => 'Add at least one skill to your jobseeker profile.',
            'degree_certificate.required' => 'Upload your academic credential before saving your jobseeker profile.',
            'degree_certificate.mimes' => 'Credentials must be a PDF, JPG or PNG file. Convert the document to one of these formats and upload it again.',
            'degree_certificate.max' => 'Credentials must be 5 MB or smaller.',
        ]);

        $newPhone = \App\Support\PhoneNumber::normalize($validated['phone'] ?? null);
        if (($validated['phone'] ?? null) !== $user->phone) {
            if (($validated['phone'] ?? null) && ! $newPhone) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone' => 'Enter a valid phone number with a country code, or a seven-digit Fiji number.']);
            }
            if ($newPhone && User::where('login_phone', $newPhone)->where('id', '!=', $user->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone' => 'This phone number is already registered.']);
            }
            $user->login_phone = $newPhone;
        }
        $emailChanged = $user->email !== ($validated['email'] ?? null);
        if ($emailChanged) {
            $user->email_verified_at = \App\Support\MailDelivery::isLive() ? null : now();
        }

        // Merge predefined tag selections with free-text custom skills (FR1.3).
        $predefined = collect(explode(',', (string) $request->input('skills')))
            ->map(fn ($s) => trim($s))
            ->filter();
        $custom = collect(explode(',', (string) $request->input('custom_skills')))
            ->map(fn ($s) => trim($s))
            ->filter();
        $skills = $predefined->merge($custom)->unique()->values()->all();
        if ($user->isJobseeker() && count($skills) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['skills' => 'Add at least one skill to your profile.']);
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'region' => $validated['region'] ?? null,
            'qualification_level' => $validated['qualification_level'] ?? null,
            'years_experience' => $validated['years_experience'] ?? null,
            'skills' => $skills,
            'about_me' => $validated['about_me'] ?? null,
        ]);

        $resubmitted = false;
        if ($user->isEmployer()) {
            // A new certificate after a rejection sends the account back for review.
            if (EmployerDetails::apply($user, $request, $validated) && $user->employer_approval_status === 'rejected') {
                $user->employer_approval_status = 'pending';
                $resubmitted = true;
                User::where('role', 'admin')->get()->each->notify(new PlatformAlert(
                    "{$user->company_name} uploaded a new registration certificate and resubmitted their employer request.",
                    'Review employers', route('admin.employers.pending')
                ));
            }
        }

        if ($user->isMentor()) {
            $user->mentor_expertise = $validated['mentor_expertise'] ?? $user->mentor_expertise;
            $user->mentor_bio = $validated['mentor_bio'] ?? $user->mentor_bio;
            $user->mentor_industry = $validated['mentor_industry'] ?? null;
            $user->mentor_available = $request->boolean('mentor_available');
        }

        // FR1.2: academic credential upload. A new upload resets verification
        // status so an admin re-checks the latest document (FR1.5, FR5.4).
        if ($request->hasFile('degree_certificate')) {
            if ($user->degree_certificate_path) {
                Storage::disk('local')->delete($user->degree_certificate_path);
            }
            $path = $request->file('degree_certificate')->store('credentials', 'local');
            $user->degree_certificate_path = $path;
            $user->is_verified = false;
            $user->credential_status = 'pending';
            $user->credential_notes = null;

            User::where('role', 'admin')->get()->each->notify(new PlatformAlert(
                "{$user->name} uploaded a credential for verification.",
                'Review credential',
                route('admin.verifications.index', ['status' => 'unverified'])
            ));
        }

        $user->save();

        if ($emailChanged && $user->email && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', "Your profile was saved. We sent a verification link to {$user->email}; open it to confirm your new address.");
        }

        return redirect()->route('profile.edit')->with('status', $resubmitted
            ? 'Your details were saved and your employer request was sent back to an administrator for review.'
            : 'Your profile has been updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->mentorSessions()->exists() || $user->menteeSessions()->exists() || $user->jobApplications()->exists()) {
            return back()->withErrors(['account' => 'Your account has shared application or mentorship records. Contact an administrator about account closure; these records must be preserved for the other participants.']);
        }
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['account' => 'The last administrator account cannot be deleted.']);
        }

        if ($user->jobListings()->whereHas('applications')->exists()) {
            return back()->withErrors(['account' => 'This account owns listings with application records. Archive its listings and contact the administrator about account closure so applicant history can be preserved.']);
        }

        Auth::logout();

        if ($user->degree_certificate_path) {
            Storage::disk('local')->delete($user->degree_certificate_path);
        }

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account has been deleted.');
    }

    // ---- Employer registration (FR2.1) ----

    public function employerRegistrationForm(Request $request): View|RedirectResponse
    {
        if ($request->user()->role !== 'jobseeker') {
            return redirect()->route('dashboard');
        }

        return view('profile.employer-register');
    }

    public function employerRegister(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isJobseeker(), 403);
        $user = $request->user();
        if ($user->jobApplications()->exists() || $user->menteeSessions()->exists()) {
            return back()->withErrors(['company_name' => 'This account has job applications or mentorship sessions as a jobseeker. Create a separate account for your business so that history stays with you.']);
        }
        $validated = $request->validate(EmployerDetails::rules(documentRequired: true), EmployerDetails::messages());

        $user->fill(['role' => 'employer', 'employer_approval_status' => 'pending']);
        EmployerDetails::apply($user, $request, $validated);
        $user->save();

        User::where('role', 'admin')->get()->each->notify(new PlatformAlert(
            "{$user->company_name} requested employer approval and uploaded business documents.",
            'Review employers',
            route('admin.employers.pending')
        ));

        return redirect()->route('dashboard')
            ->with('status', 'Your employer request has been sent for administrator review.');
    }

    /**
     * Private business registration certificate: administrators and the
     * employer who uploaded it only.
     */
    public function businessDocument(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->id === $user->id, 403);
        abort_unless($user->business_document_path && Storage::disk('local')->exists($user->business_document_path), 404);

        return Storage::disk('local')->response($user->business_document_path, $user->business_document_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function markNotificationRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return back();
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
