<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\WelcomeToPlatform;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Marks the address as verified from the emailed link, then sends the
     * welcome email (registration waits for this before welcoming).
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false))->with('status', 'Your email address is already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            // New accounts get the welcome email; verifying a changed address later does not.
            if ($user->created_at->gt(now()->subDays(3))) {
                $user->notify(new WelcomeToPlatform());
            }
        }

        return redirect()->intended(route('dashboard', absolute: false))->with('status', 'Thank you. Your email address is verified.');
    }
}
