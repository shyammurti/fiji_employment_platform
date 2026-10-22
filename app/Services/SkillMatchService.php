<?php

namespace App\Services;

use App\Models\JobListing;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Core of M4 - Application Tracking & Skill Matching Engine: compares a
 * jobseeker's skill tags against a
 * job listing's required_skills to produce a 0-100 match_percentage.
 *
 * Matching is case-insensitive and tolerant of minor formatting differences
 * (e.g. "PHP", "php", " Php ") so the score reflects genuine overlap rather
 * than exact-string coincidence.
 */
class SkillMatchService
{
    public function hasOverlap(User $jobseeker, JobListing $listing): bool
    {
        return count(array_intersect($this->normalize($listing->requiredSkillsList()), $this->normalize($jobseeker->skillsList()))) > 0;
    }

    public function calculate(User $jobseeker, JobListing $listing): int
    {
        $required = $this->normalize($listing->requiredSkillsList());
        $candidate = $this->normalize($jobseeker->skillsList());

        if (empty($required)) {
            // No criteria means there is no evidence of a strong skill match.
            return 0;
        }

        $overlap = array_intersect($required, $candidate);

        return (int) round((count($overlap) / count($required)) * 100);
    }

    /**
     * Required skills split into those the jobseeker has and those they
     * are missing, keeping the employer's original wording for display.
     */
    public function breakdown(User $jobseeker, JobListing $listing): array
    {
        $candidate = $this->normalize($jobseeker->skillsList());
        $matched = [];
        $missing = [];
        foreach (array_unique($listing->requiredSkillsList()) as $skill) {
            if (in_array(Str::lower(trim((string) $skill)), $candidate, true)) {
                $matched[] = $skill;
            } else {
                $missing[] = $skill;
            }
        }

        return ['score' => $this->calculate($jobseeker, $listing), 'matched' => $matched, 'missing' => $missing];
    }

    private function normalize(array $skills): array
    {
        return array_values(array_unique(array_map(
            fn ($skill) => Str::lower(trim((string) $skill)),
            $skills
        )));
    }
}
