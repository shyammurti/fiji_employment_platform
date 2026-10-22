<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PlatformAlert;
use App\Notifications\WelcomeToPlatform;
use App\Support\EmployerDetails;
use App\Support\MailDelivery;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Module M1 Jobseeker Profile & Verification, developed by Sameet Singh (S11231364).
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', ['registrationRole' => $request->routeIs('mentor.register') ? 'mentor' : 'jobseeker']);
    }

    /**
     * FR1.1: registration with email/phone + password. New accounts
     * default to role=jobseeker; employer status is requested separately
     * via /employer/register (FR2.1) once logged in.
     */
    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $phone = is_string($request->input('phone')) ? \App\Support\PhoneNumber::normalize($request->input('phone')) : null;
        $request->merge(['login_phone' => $phone]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[\pL\pM\s.\'\-]+$/u'],
            'email' => ['bail', 'required_without:phone', 'nullable', 'string', 'max:255', new \App\Rules\EmailAddress($request->boolean('confirm_email_domain')), 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'login_phone' => ['required_with:phone', 'nullable', 'unique:users,login_phone'],
            'role' => ['sometimes', 'in:jobseeker,mentor,employer'],
            'mentor_expertise' => ['required_if:role,mentor', 'nullable', 'string', 'max:255'],
            'mentor_bio' => ['required_if:role,mentor', 'nullable', 'string', 'min:30', 'max:2000'],
            'mentor_industry' => ['nullable', 'string', 'max:255'],
            ...EmployerDetails::rules(documentRequired: true, whenEmployerRole: true),
        ], [
            'name.regex' => 'Your name can contain letters, spaces, apostrophes, dots and hyphens only.',
            'login_phone.required_with' => 'Enter a valid phone number, including its country code.',
            'login_phone.unique' => 'This phone number is already registered.',
            'mentor_bio.min' => 'Describe your professional background in at least 30 characters.',
            ...EmployerDetails::messages(),
        ]);
        $role = $request->input('role', 'jobseeker');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            // Verified by the emailed link when mail is live; trusted otherwise.
            'email_verified_at' => MailDelivery::isLive() ? null : now(),
            'phone' => $validated['phone'] ?? null,
            'login_phone' => $phone,
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'mentor_expertise' => $role === 'mentor' ? $validated['mentor_expertise'] : null,
            'mentor_bio' => $role === 'mentor' ? $validated['mentor_bio'] : null,
            'mentor_industry' => $role === 'mentor' ? ($validated['mentor_industry'] ?? null) : null,
            'employer_approval_status' => $role === 'employer' ? 'pending' : 'approved',
            'is_verified' => false,
        ]);

        if ($role === 'employer') {
            EmployerDetails::apply($user, $request, $validated);
            $user->save();
            User::where('role', 'admin')->get()->each->notify(new PlatformAlert(
                "{$user->company_name} registered as an employer and submitted business documents for review.",
                'Review employers', route('admin.employers.pending')
            ));
        }

        // Registered sends the verification link (Laravel's built-in listener) to
        // unverified email accounts. The welcome email follows verification.
        event(new Registered($user));
        Auth::login($user);

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('status', "Welcome, {$user->name}! We sent a verification link to {$user->email}. Open it to activate your account.");
        }

        $user->notify(new WelcomeToPlatform());

        return redirect(route('dashboard', absolute: false))->with('status', 'Welcome, '.$user->name.'! Your account is ready.');
    }
}
