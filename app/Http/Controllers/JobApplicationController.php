<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\MentorshipSession;
use App\Notifications\ApplicationStatusUpdated;
use App\Notifications\MentorshipSessionUpdated;
use App\Notifications\NewQualifiedMatch;
use App\Notifications\PlatformAlert;
use App\Services\ScheduleGuard;
use App\Services\SkillMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Module M4 Application Tracking & Skill Matching, developed by Ammara Dean (S11220213); Module M2 Employer Portal, developed by Hritish Pawar (S11219177); Module M3 AI Chatbot & Mentorship, developed by Shyam Murti (S11229913).
 */
class JobApplicationController extends Controller
{
    public function __construct(private readonly SkillMatchService $matcher, private readonly ScheduleGuard $schedule)
    {
    }

    /**
     * FR4.1 + FR4.2: accept a direct application, store the CV, and
     * immediately compute the skill-match score. Strong matches (>=70%)
     * trigger a "you're a strong match" notification (FR4.2 tail clause).
     */
    public function store(Request $request, JobListing $jobListing): RedirectResponse
    {
        abort_unless($request->user()->isJobseeker(), 403, 'Only jobseekers can apply for jobs.');
        abort_unless($jobListing->isAcceptingApplications() && $jobListing->employer->isApprovedEmployer(), 403, $jobListing->hasClosed() ? 'Applications for this vacancy closed on '.$jobListing->closing_date->format('d M Y').'.' : 'This listing is not currently accepting applications.');

        if ($jobListing->applications()->where('user_id', $request->user()->id)->exists()) {
            return redirect()->route('applications.mine')->with('warning', 'You have already applied for "'.$jobListing->title.'". Track it below.');
        }

        $validated = $request->validate([
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ], [
            'resume.required' => 'Attach your resume or CV to apply.',
            'resume.mimes' => 'Your resume must be a PDF, DOC or DOCX file.',
            'resume.max' => 'Your resume must be 5 MB or smaller.',
        ]);

        $resumePath = $request->file('resume')->store('resumes', 'local');

        $matchPercentage = $this->matcher->calculate($request->user(), $jobListing);

        $application = $jobListing->applications()->create([
            'user_id' => $request->user()->id,
            'cover_letter' => $validated['cover_letter'] ?? null,
            'resume_path' => $resumePath,
            'match_percentage' => $matchPercentage,
        ]);

        $jobListing->employer->notify(new PlatformAlert(
            "{$request->user()->name} applied for {$jobListing->title}.",
            'Review applications',
            route('employer.jobs.applications', $jobListing)
        ));

        return redirect()->route('applications.mine')
            ->with('status', "Application submitted - skill match score: {$matchPercentage}%.");
    }

    /**
     * FR4.5: complete candidate application history/timeline.
     */
    public function myApplications(Request $request): View
    {
        $applications = $request->user()->jobApplications()
            ->with(['jobListing', 'statusChanges'])
            ->latest()
            ->paginate(\App\Support\PerPage::resolve(10))->withQueryString();

        return view('applications.mine', ['applications' => $applications]);
    }

    // ---- Employer application management (FR2.5) ----

    public function employerIndex(Request $request): View
    {
        $listingIds = $request->user()->jobListings()->pluck('id');

        $applications = JobApplication::whereIn('job_listing_id', $listingIds)
            ->with(['jobListing', 'jobseeker'])
            ->latest()
            ->paginate(\App\Support\PerPage::resolve())->withQueryString();

        return view('employer.applications.index', ['applications' => $applications]);
    }

    public function showApplicationsForJob(JobListing $jobListing): View
    {
        abort_unless($jobListing->user_id === auth()->id(), 403);

        $applications = $jobListing->applications()
            ->with('jobseeker')
            ->orderByDesc('match_percentage')
            ->paginate(\App\Support\PerPage::resolve())->withQueryString();

        return view('employer.jobs.applications', ['job' => $jobListing, 'applications' => $applications]);
    }

    /**
     * FR2.5 / FR4.3 / FR4.4: transitions an application through the
     * pipeline, optionally records an interview time + notes, and fires
     * status-change notifications (email + in-app).
     */
    public function updateStatus(Request $request, JobApplication $application): RedirectResponse
    {
        abort_unless($application->jobListing->user_id === auth()->id(), 403);
        abort_if($application->jobListing->is_archived || $application->jobListing->recruitment_completed_at, 422, 'This recruitment record is closed and retained for history.');
        $this->normalizeLocalTime($request, 'interview_at');

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', JobApplication::STATUSES)],
            'interview_at' => ['exclude_unless:status,Interview Scheduled', 'required_if:status,Interview Scheduled', 'date', 'after:now'],
            'employer_notes' => ['nullable', 'string', 'max:2000'],
        ], ['interview_at.after' => 'The interview must be scheduled in the future.', 'interview_at.required_if' => 'Choose the interview date and time.']);

        if ($validated['status'] !== 'Interview Scheduled') {
            $validated['interview_at'] = null;
        } else {
            $time = Carbon::parse($validated['interview_at']);
            // Only re-check the clock when the interview time actually changes.
            if (! $application->interview_at || ! $application->interview_at->equalTo($time)) {
                $this->schedule->assertBookableTime($time, 'interview_at', ScheduleGuard::INTERVIEW_WINDOW_DAYS);
            }
            $this->schedule->assertInterviewSlotFree($application, $time);
        }
        $application->update($validated);

        // FR4.4: notify on status or interview changes. Employer notes stay internal.
        if ($application->wasChanged(['status', 'interview_at'])) {
            $application->jobseeker->notify(new ApplicationStatusUpdated($application));

            return back()->with('status', "Application status updated to \"{$application->status}\". The applicant has been notified.");
        }

        return back()->with('status', 'Notes saved. The applicant was not notified because the status did not change.');
    }

    // ---- Mentorship booking (M3: AI Chatbot & Mentorship Engine, FR3.5) ----

    public function scheduleSession(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isJobseeker(), 403);
        $this->normalizeLocalTime($request, 'scheduled_at');
        $validated = $request->validate([
            'mentor_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'mentor')->where('mentor_available', true)],
            'topic' => ['required', 'string', 'min:3', 'max:255'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'escalated_from_chatbot' => ['nullable', 'boolean'],
        ], [
            'mentor_id.exists' => 'This mentor is not accepting new requests right now.',
            'scheduled_at.after' => 'Choose a time in the future. Past dates cannot be booked.',
        ]);

        $session = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request) {
            // Lock both people so two simultaneous requests cannot claim the same hour.
            \App\Models\User::whereIn('id', [$validated['mentor_id'], $request->user()->id])->orderBy('id')->lockForUpdate()->get();
            $time = Carbon::parse($validated['scheduled_at']);
            $this->schedule->assertBookableTime($time, 'scheduled_at', ScheduleGuard::MENTORSHIP_WINDOW_DAYS);
            $this->schedule->assertMentorshipSlotFree((int) $validated['mentor_id'], $request->user()->id, $time);
            if (MentorshipSession::where('mentor_id', $validated['mentor_id'])->where('mentee_id', $request->user()->id)->where('status', 'pending')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['scheduled_at' => 'You already have a request waiting for this mentor. Wait for them to respond or cancel it first.']);
            }
            return MentorshipSession::create([
            'mentor_id' => $validated['mentor_id'],
            'mentee_id' => $request->user()->id,
            'topic' => $validated['topic'],
            'scheduled_at' => $validated['scheduled_at'],
            'escalated_from_chatbot' => $request->boolean('escalated_from_chatbot'),
            ]);
        });

        $session->mentor->notify(new MentorshipSessionUpdated($session));

        return redirect()->route('career.hub')->with('status', 'Mentorship session requested. The mentor will confirm shortly.');
    }

    private function normalizeLocalTime(Request $request, string $field): void
    {
        $value = $request->input($field);
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            try {
                $time = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d\TH:i', $value, 'Pacific/Fiji');
                if ($time->format('Y-m-d\TH:i') !== $value) { throw new \InvalidArgumentException('Invalid date.'); }
                $request->merge([$field => $time->utc()->format('Y-m-d H:i:s')]);
            } catch (\Throwable $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Enter a valid date and time.']);
            }
        }
    }

    public function updateSessionStatus(Request $request, MentorshipSession $mentorshipSession): RedirectResponse
    {
        abort_unless(
            in_array(auth()->id(), [$mentorshipSession->mentor_id, $mentorshipSession->mentee_id], true),
            403
        );

        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ]);

        $isMentor = $request->user()->isMentor() && $request->user()->id === $mentorshipSession->mentor_id;
        $allowed = $isMentor
            ? match ($mentorshipSession->status) { 'pending' => ['confirmed', 'cancelled'], 'confirmed' => ['completed', 'cancelled'], default => [] }
            : (in_array($mentorshipSession->status, ['pending', 'confirmed'], true) ? ['cancelled'] : []);
        abort_unless(in_array($validated['status'], $allowed, true), 422, 'This session transition is not available.');
        abort_if($validated['status'] === 'completed' && $mentorshipSession->scheduled_at->copy()->addHour()->isFuture(), 422, 'The one-hour session must finish before it can be completed.');
        if ($validated['status'] === 'confirmed') {
            if ($mentorshipSession->scheduled_at->isPast()) {
                return back()->withErrors(['session' => 'This request is for a time that has already passed. Cancel it and ask the jobseeker to book a new time.']);
            }
            // Another request may have been confirmed for the same hour since this one was made.
            $clash = MentorshipSession::where('status', 'confirmed')->whereKeyNot($mentorshipSession->id)
                ->where(fn ($q) => $q->where('mentor_id', $mentorshipSession->mentor_id)->orWhere('mentee_id', $mentorshipSession->mentee_id))
                ->where('scheduled_at', '>', $mentorshipSession->scheduled_at->copy()->subHour())
                ->where('scheduled_at', '<', $mentorshipSession->scheduled_at->copy()->addHour())->exists();
            if ($clash) {
                return back()->withErrors(['session' => 'A confirmed session already overlaps this time. Cancel this request or ask the jobseeker to rebook.']);
            }
        }
        if ($validated['status'] === 'cancelled' && $mentorshipSession->scheduled_at->isPast()) {
            return back()->withErrors(['session' => 'Sessions that have already started cannot be cancelled.']);
        }
        $mentorshipSession->update($validated);

        $recipient = auth()->id() === $mentorshipSession->mentor_id
            ? $mentorshipSession->mentee
            : $mentorshipSession->mentor;
        $recipient->notify(new MentorshipSessionUpdated($mentorshipSession));

        return back()->with('status', 'Session status updated.');
    }

    public function submitFeedback(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isJobseeker(), 403);
        $validated = $request->validate([
            'mentorship_session_id' => ['required', 'exists:mentorship_sessions,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = MentorshipSession::findOrFail($validated['mentorship_session_id']);
        abort_unless($session->mentee_id === $request->user()->id, 403);
        abort_unless(in_array($session->status, ['confirmed', 'completed'], true) && $session->scheduled_at->copy()->addHour()->isPast() && $session->rating === null, 422, 'Feedback is available once after a completed session.');

        $session->update([
            'rating' => $validated['rating'],
            'feedback' => $validated['feedback'] ?? null,
            'status' => 'completed',
        ]);

        return back()->with('status', 'Thanks for your feedback!');
    }
}
