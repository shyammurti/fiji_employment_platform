<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\User;
use App\Notifications\PlatformAlert;
use App\Services\ListingLifecycleService;
use App\Services\SkillMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobListingController extends Controller
{
    public function home(): View
    {
        return view('welcome', [
            'latestJobs' => JobListing::publiclyVisible()->latest()->take(3)->get(),
            'liveCount' => JobListing::publiclyVisible()->count(),
        ]);
    }

    /**
     * Public job board. Only approved, active, non-archived listings are
     * shown (FR2.2). Supports simple keyword/type/location filters.
     */
    public function index(Request $request): View
    {
        $query = JobListing::publiclyVisible()->with('employer');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($region = $request->string('region')->toString()) {
            $query->where('region', $region);
        }

        $jobs = $query->latest()->paginate(\App\Support\PerPage::resolve(9, \App\Support\PerPage::grid(9)))->withQueryString();

        // FR4.2: show a jobseeker how their saved skills line up with each vacancy.
        if ($request->user()?->isJobseeker()) {
            $matcher = app(SkillMatchService::class);
            $jobs->getCollection()->each(fn ($job) => $job->match_score = $job->requiredSkillsList() ? $matcher->calculate($request->user(), $job) : null);
        }

        return view('jobs.index', [
            'jobs' => $jobs,
            'filters' => $request->only(['q', 'type', 'region']),
        ]);
    }

    public function show(JobListing $jobListing): View
    {
        abort_unless(
            $jobListing->canBeViewedBy(auth()->user()),
            404
        );

        $alreadyApplied = false;
        if (auth()->check()) {
            $alreadyApplied = $jobListing->applications()
                ->where('user_id', auth()->id())
                ->exists();
        }

        $match = auth()->user()?->isJobseeker() && $jobListing->requiredSkillsList()
            ? app(SkillMatchService::class)->breakdown(auth()->user(), $jobListing)
            : null;

        return view('jobs.show', [
            'job' => $jobListing->load('employer'),
            'alreadyApplied' => $alreadyApplied,
            'match' => $match,
        ]);
    }

    // ---- Employer: create / manage listings (FR2.2) ----

    public function create(): View
    {
        return view('jobs.create', ['job' => new JobListing([
            'company' => auth()->user()->company_name,
            'industry_sector' => auth()->user()->industry_sector,
            'region' => auth()->user()->region,
            'vacancy_count' => 1,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        $job = $request->user()->jobListings()->create([
            ...$validated,
            'approval_status' => 'pending_approval', // FR2.2: queued for admin review
        ]);

        $this->notifyAdmins($job, "{$job->company} submitted \"{$job->title}\" for review.");

        return redirect()->route('employer.jobs.index')
            ->with('status', "\"{$job->title}\" submitted and is pending admin approval.");
    }

    public function edit(JobListing $jobListing): View
    {
        $this->authorizeOwner($jobListing);

        abort_if($jobListing->is_archived || $jobListing->recruitment_completed_at, 422, 'This listing is retained for history and cannot be edited.');

        return view('jobs.create', ['job' => $jobListing]);
    }

    public function update(Request $request, JobListing $jobListing): RedirectResponse
    {
        $this->authorizeOwner($jobListing);

        abort_if($jobListing->is_archived || $jobListing->recruitment_completed_at, 422, 'This listing is retained for history and cannot be edited.');

        $validated = $this->validatedData($request, $jobListing);

        $oldDocument = $jobListing->document_path;

        // Material edits re-enter the approval queue so admins can review changes.
        $jobListing->update([
            ...$validated,
            'approval_status' => 'pending_approval',
        ]);

        if (isset($validated['document_path']) && $oldDocument) {
            Storage::disk('local')->delete($oldDocument);
        }
        $this->notifyAdmins($jobListing, "{$jobListing->company} updated \"{$jobListing->title}\" and resubmitted it for review.");

        return redirect()->route('employer.jobs.index')
            ->with('status', "\"{$jobListing->title}\" updated and re-queued for admin approval.");
    }

    public function archive(JobListing $jobListing, ListingLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeOwner($jobListing);
        $lifecycle->close($jobListing, 'The employer has withdrawn this listing.', archive: true);

        return back()->with('status', 'Listing archived. Application history has been preserved and applicants notified.');
    }

    public function close(JobListing $jobListing, ListingLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeOwner($jobListing);
        $lifecycle->close($jobListing, 'The employer is no longer accepting new applications. Existing applications can still be reviewed.');

        return back()->with('status', 'Listing closed to new applications. Applicants have been notified.');
    }

    public function completeRecruitment(Request $request, JobListing $jobListing, ListingLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeOwner($jobListing);
        $request->validate(['confirm_completion' => ['accepted']]);
        $lifecycle->completeRecruitment($jobListing);

        return back()->with('status', 'Recruitment completed. Remaining applicants have been marked as rejected and notified; hired applicants are unchanged.');
    }

    public function document(JobListing $jobListing): StreamedResponse
    {
        abort_unless($jobListing->canBeViewedBy(auth()->user()) && $jobListing->document_path, 404);
        abort_unless(Storage::disk('local')->exists($jobListing->document_path), 404);

        return Storage::disk('local')->download($jobListing->document_path, $jobListing->document_name);
    }

    public function employerIndex(Request $request): View
    {
        $jobs = $request->user()->jobListings()
            ->withCount(['applications', 'applications as hired_count' => fn ($query) => $query->where('status', 'Hired'),
                'applications as awaiting_outcome_count' => fn ($query) => $query->whereNotIn('status', ['Hired', 'Rejected'])])
            ->latest()
            ->paginate(\App\Support\PerPage::resolve(10))->withQueryString();

        return view('employer.jobs.index', ['jobs' => $jobs]);
    }

    // ---- Employer: candidate search (FR2.3, FR2.4) ----

    public function candidateSearch(Request $request): View
    {
        $query = User::query()->where('role', 'jobseeker');

        if ($skill = $request->string('skill')->trim()->toString()) {
            $query->where('skills', 'like', "%{$skill}%");
        }

        if ($qualification = $request->string('qualification_level')->toString()) {
            $query->where('qualification_level', $qualification);
        }

        if ($region = $request->string('region')->toString()) {
            $query->where('region', $region);
        }

        if ($minExperience = $request->integer('min_experience')) {
            $query->where('years_experience', '>=', $minExperience);
        }

        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        $candidates = $query->orderByDesc('is_verified')->latest()->paginate(\App\Support\PerPage::resolve(12, \App\Support\PerPage::grid(12)))->withQueryString();

        return view('employer.candidates.index', [
            'candidates' => $candidates,
            'filters' => $request->only(['skill', 'qualification_level', 'region', 'min_experience', 'verified_only']),
        ]);
    }

    public function candidateShow(User $user): View
    {
        abort_unless($user->role === 'jobseeker', 404);

        return view('employer.candidates.show', ['candidate' => $user]);
    }

    // ---- Helpers ----

    private function notifyAdmins(JobListing $listing, string $message): void
    {
        User::where('role', 'admin')->get()->each->notify(new PlatformAlert($message, 'Review listing', route('admin.jobs.review', $listing)));
    }

    private function authorizeOwner(JobListing $jobListing): void
    {
        abort_unless($jobListing->user_id === auth()->id(), 403);
    }

    /**
     * FR2.2: the documentation an administrator needs to review a vacancy.
     * A position description document is required unless one is already on file.
     */
    private function validatedData(Request $request, ?JobListing $existing = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'location' => ['required', 'string', 'min:2', 'max:255'],
            'region' => ['required', 'in:Central Division,Western Division,Northern Division,Eastern Division'],
            'type' => ['required', 'in:Full-time,Part-time,Contract,Remote'],
            'salary_range' => ['required', 'string', 'max:100', 'regex:/\d/'],
            'description' => ['required', 'string', 'min:50', 'max:20000'],
            'responsibilities' => ['required', 'string', 'min:30', 'max:5000'],
            'required_degree' => ['required', 'string', 'max:255'],
            'required_skills' => ['required', 'string', 'max:2000', 'regex:/[A-Za-z]/'],
            'vacancy_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'closing_date' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'job_document' => [$existing?->document_path ? 'nullable' : 'required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ], [
            'salary_range.regex' => 'Include an amount in the salary range, for example FJD 12,000 - 15,000 per year.',
            'description.min' => 'Describe the role in at least 50 characters so the administrator and applicants understand it.',
            'responsibilities.required' => 'List the main duties and responsibilities of the role.',
            'required_skills.required' => 'Add at least one required skill. The matching engine uses these to score applicants.',
            'closing_date.after' => 'The closing date must be in the future.',
            'closing_date.before_or_equal' => 'The closing date must be within the next 12 months.',
            'job_document.required' => 'Attach a position description (PDF, DOC or DOCX) for the administrator to review.',
            'job_document.mimes' => 'The position description must be a PDF, DOC or DOCX file.',
        ]);

        $validated['company'] = $request->user()->company_name;
        $validated['industry_sector'] = $request->user()->industry_sector;
        unset($validated['job_document']);
        if ($request->hasFile('job_document')) {
            $validated['document_path'] = $request->file('job_document')->store('job-documents', 'local');
            $validated['document_name'] = $request->file('job_document')->getClientOriginalName();
        }

        $validated['required_skills'] = collect(explode(',', (string) ($validated['required_skills'] ?? '')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        return $validated;
    }
}
