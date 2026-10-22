<?php
namespace App\Services;

use App\Models\JobListing;
use App\Models\User;
use App\Notifications\NewQualifiedMatch;
use Illuminate\Support\Facades\DB;

/**
 * Module M4 Application Tracking & Skill Matching, developed by Ammara Dean (S11220213).
 */
class JobMatchNotifier
{
    public function notify(JobListing $listing): int
    {
        if (! JobListing::publiclyVisible()->whereKey($listing->id)->exists()) { return 0; }
        $sent = 0;
        User::where('role', 'jobseeker')->orderBy('id')->chunkById(100, function ($users) use ($listing, &$sent) {
            foreach ($users as $user) {
                $matcher = app(SkillMatchService::class);
                if (! $matcher->hasOverlap($user, $listing)) { continue; }
                $score = $matcher->calculate($user, $listing);
                $inserted = DB::table('job_match_alerts')->insertOrIgnore(['user_id' => $user->id, 'job_listing_id' => $listing->id, 'created_at' => now(), 'updated_at' => now()]);
                if ($inserted) {
                    $user->notify(new NewQualifiedMatch($listing, $score));
                    $sent++;
                }
            }
        });
        return $sent;
    }
}
