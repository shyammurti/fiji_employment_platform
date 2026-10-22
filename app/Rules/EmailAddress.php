<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Module M1 Jobseeker Profile & Verification, developed by Sameet Singh (S11231364).
 */
class EmailAddress implements ValidationRule
{
    public function __construct(private readonly bool $confirmedDomain = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)
            || ! preg_match('/^[^\s@]+@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/i', $value)) {
            $fail('Enter a complete email address, such as name@example.com.');
            return;
        }

        $domain = strtolower(substr(strrchr($value, '@'), 1));
        if ($domain === 'fmail.com' && ! $this->confirmedDomain) {
            $fail('Did you mean gmail.com? If fmail.com is intentional, tick the email domain confirmation below.');
            return;
        }
        $corrections = [
            'gmail.comm' => 'gmail.com', 'gmail.con' => 'gmail.com',
            'gmail.co' => 'gmail.com', 'gamil.com' => 'gmail.com',
            'gmial.com' => 'gmail.com', 'yahoo.comm' => 'yahoo.com',
            'yahoo.con' => 'yahoo.com', 'hotmail.comm' => 'hotmail.com',
            'hotmail.con' => 'hotmail.com', 'outlook.comm' => 'outlook.com',
            'outlook.con' => 'outlook.com',
        ];
        if (isset($corrections[$domain])) {
            $fail('Check the email domain. Did you mean '.$corrections[$domain].'?');
        }
    }
}
