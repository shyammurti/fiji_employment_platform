<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->has('identifier')) {
            return ['identifier' => ['bail', 'required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (str_contains($value, '@')) {
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL) || ! preg_match('/@[^\s@]+\.[^\s@]+$/', $value)) {
                        $fail('Enter a complete email address.');
                    }
                } elseif (! \App\Support\PhoneNumber::normalize($value)) {
                    $fail('Enter an email address or a valid phone number, including its country code.');
                }
            }], 'password' => ['required', 'string']];
        }
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('identifier'))) {
            $this->merge(['identifier' => Str::lower(trim($this->input('identifier')))]);
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address, such as name@example.com.',
            'email.regex' => 'Enter a complete email address, such as name@example.com.',
            'password.required' => 'Enter your password.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = $this->input('identifier', $this->input('email'));
        $field = $this->has('identifier') ? 'identifier' : 'email';
        $credentials = [str_contains($identifier, '@') ? 'email' : 'login_phone' => str_contains($identifier, '@') ? $identifier : \App\Support\PhoneNumber::normalize($identifier), 'password' => $this->input('password')];
        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                $field => $field === 'email' ? 'The email address or password is incorrect. Check both fields and try again.' : 'The email, phone number or password is incorrect. Check your details and try again.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $identifier = (string) $this->input('identifier', $this->input('email'));
        $identifier = \App\Support\PhoneNumber::normalize($identifier) ?? $identifier;
        return Str::transliterate(Str::lower($identifier).'|'.$this->ip());
    }
}
