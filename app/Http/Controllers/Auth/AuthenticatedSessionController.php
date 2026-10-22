<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            'loginRole' => null,
            'loginTitle' => 'Account Login',
            'loginIntro' => 'Access your Fiji Employment Hub dashboard.',
        ]);
    }

    public function createEmployer(): View
    {
        return view('auth.login', [
            'loginRole' => 'employer',
            'loginTitle' => 'Employer Login',
            'loginIntro' => 'Manage job listings, review applications, and search verified candidates.',
        ]);
    }

    public function createMentor(): View
    {
        return view('auth.login', [
            'loginRole' => 'mentor',
            'loginTitle' => 'Mentor Login',
            'loginIntro' => 'Review mentorship requests and support Fiji jobseekers.',
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $expectedRole = $request->input('expected_role');

        if ($expectedRole && $request->user()->role !== $expectedRole) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Please use the correct login page for this account type.',
            ]);
        }

        $dashboard = $request->user()->isAdmin()
            ? route('admin.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($dashboard)
            ->with('status', 'Welcome back, '.$request->user()->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
