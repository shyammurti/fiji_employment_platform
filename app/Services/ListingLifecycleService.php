<?php

namespace App\Services;

use App\Models\JobListing;
use App\Notifications\ApplicationStatusUpdated;
use App\Notifications\PlatformAlert;
use Illuminate\Support\Facades\DB;

class ListingLifecycleService
{
    public function close(JobListing $listing, string $reason, bool $archive = false): bool
    {
        $changed = DB::transaction(function () use ($listing, $reason, $archive) {
            $current = JobListing::lockForUpdate()->findOrFail($listing->id);
            if ($current->is_archived || (! $archive && ! $current->is_active)) {
                return false;
            }
            $current->update([
                'is_active' => false,
                'is_archived' => $archive,
                'closure_reason' => $reason,
            ]);

            return true;
        });

        if ($changed) {
            $listing->refresh();
            $action = $archive ? 'archived' : 'closed';
            $this->notifyClosure($listing, $reason, $action);
        }

        return $changed;
    }

    public function completeRecruitment(JobListing $listing): void
    {
        $applications = DB::transaction(function () use ($listing) {
            $current = JobListing::lockForUpdate()->findOrFail($listing->id);
            abort_if($current->is_archived, 422, 'Archived listings cannot be changed.');
            if ($current->recruitment_completed_at) {
                return null;
            }
            abort_unless($current->applications()->where('status', 'Hired')->exists(), 422, 'Record the successful applicant before completing recruitment.');
            $applications = $current->applications()->whereNotIn('status', ['Hired', 'Rejected'])->lockForUpdate()->get();
            foreach ($applications as $application) {
                $application->update(['status' => 'Rejected', 'interview_at' => null]);
            }
            $current->update([
                'is_active' => false,
                'closure_reason' => 'Recruitment is complete. All applicants have been given an outcome.',
                'recruitment_completed_at' => now(),
            ]);

            return $applications;
        });

        if ($applications !== null) {
            foreach ($applications as $application) {
                $application->jobseeker->notify(new ApplicationStatusUpdated($application));
            }
            $listing->refresh();
            $this->notifyClosure($listing, $listing->closure_reason, 'closed');
        }
    }

    private function notifyClosure(JobListing $listing, string $reason, string $action): void
    {
        $listing->applications()->with('jobseeker')->get()->each(function ($application) use ($listing, $reason, $action) {
            $application->jobseeker->notify(new PlatformAlert(
                "The listing for \"{$listing->title}\" at {$listing->company} has been {$action}. {$reason} Your application record and outcome remain available.",
                'View my applications', route('applications.mine')
            ));
        });
        $listing->employer->notify(new PlatformAlert(
            "\"{$listing->title}\" has been {$action}. {$reason}",
            'View my listings', route('employer.jobs.index')
        ));
    }
}
