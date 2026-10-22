<?php
namespace App\Support;

/**
 * Module M1 Jobseeker Profile & Verification, developed by Sameet Singh (S11231364).
 */
class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '' || ! preg_match('/^[+\d\s()\-]+$/', $value)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) === 7) {
            $digits = '679'.$digits;
        }
        return preg_match('/^[1-9][0-9]{7,14}$/', $digits) ? '+'.$digits : null;
    }
}
