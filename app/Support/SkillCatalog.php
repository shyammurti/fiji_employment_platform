<?php

namespace App\Support;

/**
 * Predefined skill list for FR1.4. Jobseekers pick from these groups or
 * type a custom skill; employers see the same names when listing
 * requirements so the matching engine compares like with like.
 *
 * Module M1 Jobseeker Profile & Verification, developed by Sameet Singh (S11231364).
 */
class SkillCatalog
{
    public const GROUPS = [
        'Workplace' => ['Communication', 'Teamwork', 'Customer Service', 'Problem Solving', 'Time Management', 'Leadership'],
        'Office & Business' => ['Microsoft Excel', 'Microsoft Word', 'Data Entry', 'Bookkeeping', 'Accounting', 'Sales', 'Report Writing'],
        'Tourism & Hospitality' => ['Front Office Operations', 'Food & Beverage Service', 'Housekeeping', 'Tour Guiding', 'Conflict Resolution'],
        'Technology' => ['PHP', 'Laravel', 'JavaScript', 'MySQL', 'Networking', 'IT Support', 'Graphic Design'],
        'Trades & Field' => ['Carpentry', 'Electrical Work', 'Plumbing', 'Driving', 'Agriculture', 'Health & Safety'],
    ];

    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }
}
