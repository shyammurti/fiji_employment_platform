<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards employer-only routes (M2: Employer Portal). A user must have role=employer
 * AND employer_approval_status=approved (set by an Admin via the
 * Governance panel, FR2.1 / FR5.2) before they can post jobs or manage
 * applications.
 */
class EnsureEmployerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'employer') {
            abort(403, 'Only registered employer accounts can access this area.');
        }

        if ($user->employer_approval_status === 'pending') {
            return redirect()->route('dashboard')
                ->with('warning', 'Your employer account is awaiting admin approval. You will be able to post jobs once approved.');
        }

        if ($user->employer_approval_status === 'rejected') {
            abort(403, 'Your employer account registration was not approved. Contact support for details.');
        }

        return $next($request);
    }
}
