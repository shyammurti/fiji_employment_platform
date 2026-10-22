<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatusChange;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\MentorshipSession;
use App\Models\User;
use App\Notifications\PlatformAlert;
use App\Services\ListingLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * M5: Admin Governance & Verification Dashboard (FR5.1-FR5.5, FR2.1, FR2.2).
 */
class GovernanceController extends Controller
{
    public function dashboard(): View
    {
        $totals = [
            'total_users' => User::count(),
            'jobseekers' => User::where('role', 'jobseeker')->count(),
            'employers' => User::where('role', 'employer')->count(),
            'mentors' => User::where('role', 'mentor')->count(),
            'pending_employers' => User::where('role', 'employer')->where('employer_approval_status', 'pending')->count(),
            'pending_jobs' => JobListing::pendingApproval()->count(),
            'pending_verifications' => User::where('role', 'jobseeker')->where('credential_status', 'pending')->whereNotNull('degree_certificate_path')->count(),
            'live_jobs' => JobListing::publiclyVisible()->count(),
            'applications' => JobApplication::count(),
            'applications_week' => JobApplication::where('created_at', '>=', now()->subDays(7))->count(),
            'hires' => JobApplication::where('status', 'Hired')->count(),
            'mentorship_sessions' => MentorshipSession::count(),
            'new_users_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        // Platform activity feed: latest application stage changes across all employers.
        $recentActivity = ApplicationStatusChange::with(['application.jobListing', 'application.jobseeker'])
            ->latest()->latest('id')->take(8)->get();

        $recentEmployerRequests = User::where('role', 'employer')
            ->where('employer_approval_status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $recentJobRequests = JobListing::pendingApproval()->with('employer')->latest()->take(5)->get();

        return view('admin.dashboard', compact('totals', 'recentEmployerRequests', 'recentJobRequests', 'recentActivity'));
    }

    // ---- Employer activation (FR2.1, FR5.2) ----

    public function pendingEmployers(): View
    {
        $employers = User::where('role', 'employer')
            ->where('employer_approval_status', 'pending')
            ->latest()
            ->paginate(\App\Support\PerPage::resolve())->withQueryString();

        return view('admin.employers.pending', compact('employers'));
    }

    public function approveEmployer(User $user): RedirectResponse
    {
        abort_unless($user->role === 'employer', 404);
        if (! $user->business_document_path || ! $user->business_registration_number) {
            return back()->withErrors(['employer' => "{$user->company_name} has not supplied a registration number and certificate. Request the missing information before approving."]);
        }
        $user->update(['employer_approval_status' => 'approved', 'admin_notes' => null]);
        $user->notify(new PlatformAlert(
            'Your employer account has been approved. You can now post jobs and review applications.',
            'Open dashboard',
            route('dashboard')
        ));

        return back()->with('status', "{$user->company_name} approved as an employer.");
    }

    public function rejectEmployer(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'employer', 404);

        $validated = $request->validate(['admin_notes' => ['required', 'string', 'min:10', 'max:1000']]);

        $user->update([
            'employer_approval_status' => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? $user->admin_notes,
        ]);
        $user->notify(new PlatformAlert(
            'Your employer registration was not approved. Check your dashboard for admin notes.',
            'Open dashboard',
            route('dashboard')
        ));

        return back()->with('status', "{$user->company_name} registration rejected.");
    }

    // ---- Job listing moderation (FR2.2, FR5.3) ----

    public function pendingJobs(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', 'in:pending_approval,approved,rejected,changes_requested,archived,all']])['status'] ?? 'pending_approval';
        $query = JobListing::with('employer')->withCount('applications');
        if ($status === 'archived') {
            $query->where('is_archived', true);
        } elseif ($status !== 'all') {
            $query->where('approval_status', $status)->where('is_archived', false);
        }
        $jobs = $query->latest()->paginate(\App\Support\PerPage::resolve())->withQueryString();

        return view('admin.jobs.pending', compact('jobs', 'status'));
    }

    public function reviewJob(JobListing $jobListing): View
    {
        return view('admin.jobs.review', ['job' => $jobListing->load('employer')->loadCount('applications')]);
    }

    public function approveJob(JobListing $jobListing): RedirectResponse
    {
        abort_unless(! $jobListing->is_archived && $jobListing->approval_status === 'pending_approval', 422, 'Only a pending, non-archived listing can be approved.');
        abort_unless($jobListing->employer->isApprovedEmployer(), 422, 'Approve the employer account before approving this listing.');
        $jobListing->update(['approval_status' => 'approved']);
        app(\App\Services\JobMatchNotifier::class)->notify($jobListing);
        $jobListing->employer->notify(new PlatformAlert(
            "\"{$jobListing->title}\" has been approved.".($jobListing->is_active ? ' It is now public.' : ' It remains closed to new applications.'),
            'View my listings',
            route('employer.jobs.index')
        ));

        return back()->with('status', "\"{$jobListing->title}\" approved.");
    }

    public function rejectJob(Request $request, JobListing $jobListing): RedirectResponse
    {
        abort_unless(! $jobListing->is_archived && $jobListing->approval_status === 'pending_approval', 422, 'Only a pending listing can be rejected.');
        $validated = $request->validate(['admin_notes' => ['required', 'string', 'max:2000']], [
            'admin_notes.required' => 'Explain why the listing is being rejected so the employer can correct it.',
        ]);

        $jobListing->update([
            'approval_status' => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? $jobListing->admin_notes,
        ]);
        $jobListing->employer->notify(new PlatformAlert(
            "\"{$jobListing->title}\" was not approved. {$validated['admin_notes']} You can edit and resubmit the listing.",
            'View my listings',
            route('employer.jobs.index')
        ));

        return back()->with('status', "\"{$jobListing->title}\" rejected.");
    }

    public function requestJobChanges(Request $request, JobListing $jobListing): RedirectResponse
    {
        abort_unless(! $jobListing->is_archived && $jobListing->approval_status === 'pending_approval', 422, 'Only a pending listing can be returned for changes.');
        $validated = $request->validate(['admin_notes' => ['required', 'string', 'max:2000']]);
        $jobListing->update(['approval_status' => 'changes_requested', 'admin_notes' => $validated['admin_notes']]);
        $jobListing->employer->notify(new PlatformAlert(
            "Changes requested for \"{$jobListing->title}\": {$validated['admin_notes']} Edit and resubmit your listing for review.",
            'Edit listing', route('jobs.edit', $jobListing)
        ));

        return back()->with('status', 'Changes requested. The employer has received your comments and can resubmit.');
    }

    public function archiveJob(Request $request, JobListing $jobListing, ListingLifecycleService $lifecycle): RedirectResponse
    {
        $validated = $request->validate(['closure_reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->close($jobListing, $validated['closure_reason'], archive: true);

        return back()->with('status', 'Listing archived. The employer and applicants have been notified, and application history has been preserved.');
    }

    // ---- Admin flag / request-info system (FR5.5) ----

    public function requestInfo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', 'in:user,job_listing'],
            'target_id' => ['required', 'integer'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($validated['target_type'] === 'user') {
            $target = User::findOrFail($validated['target_id']);
        } else {
            $target = JobListing::findOrFail($validated['target_id']);
        }

        $target->update(['admin_notes' => $validated['note']]);

        $recipient = $validated['target_type'] === 'user' ? $target : $target->employer;
        $recipient->notify(new PlatformAlert(
            'The administrator requested more information: '.$validated['note'],
            'Review request', $validated['target_type'] === 'user' ? route('profile.edit') : route('jobs.edit', $target)
        ));

        return back()->with('status', 'Note sent - it will be visible to the user on their profile/listing.');
    }
}
