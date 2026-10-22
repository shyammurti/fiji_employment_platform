<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PlatformAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * FR5.4: Admin inspects uploaded degree/credential files and toggles
 * the is_verified flag that drives the green "Verified" badge (FR1.5).
 *
 * Module M5 Admin Governance & Verification, developed by Maimuna Nisha (S11229653).
 */
class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::whereNotNull('degree_certificate_path')->where('role', 'jobseeker');

        if (in_array($request->input('status'), ['pending', 'verified', 'rejected', 'changes_requested'], true)) {
            $query->where('credential_status', $request->input('status'));
        } elseif ($request->string('status')->toString() === 'unverified') {
            $query->where('is_verified', false);
        } elseif ($request->string('status')->toString() === 'verified') {
            $query->where('is_verified', true);
        }

        $users = $query->latest()->paginate(\App\Support\PerPage::resolve())->withQueryString();

        return view('admin.verifications.index', [
            'users' => $users,
            'filter' => $request->string('status')->toString(),
        ]);
    }

    public function toggleVerification(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isJobseeker() && $user->degree_certificate_path, 422, 'Only uploaded jobseeker credentials can be reviewed.');
        $validated = $request->validate(['decision' => ['required', 'in:verified,rejected,changes_requested'], 'credential_notes' => ['required_unless:decision,verified', 'nullable', 'string', 'min:10', 'max:2000']]);
        $status = $validated['decision'];
        $user->update(['is_verified' => $status === 'verified', 'credential_status' => $status, 'credential_notes' => $validated['credential_notes'] ?? null]);
        $user->notify(new PlatformAlert(
            'Credential review: '.str_replace('_', ' ', $status).'. '.($user->credential_notes ?? ''),
            'Open profile',
            route('profile.edit')
        ));

        return back()->with('status', "{$user->name}'s credentials marked as {$status}.");
    }

    public function showDocument(User $user): StreamedResponse
    {
        abort_unless($user->role === 'jobseeker' && $user->degree_certificate_path, 404);
        $viewer = request()->user();
        // FR2.4 + SRS 5.2: employers see verified credentials, or the credential of
        // someone who applied to one of their own listings. Nobody else does.
        $employerMayView = $viewer->isApprovedEmployer() && (
            $user->is_verified
            || $user->jobApplications()->whereHas('jobListing', fn ($listing) => $listing->where('user_id', $viewer->id))->exists()
        );
        abort_unless($viewer->isAdmin() || $viewer->id === $user->id || $employerMayView, 403);
        abort_unless(Storage::disk('local')->exists($user->degree_certificate_path), 404);

        return Storage::disk('local')->response($user->degree_certificate_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
