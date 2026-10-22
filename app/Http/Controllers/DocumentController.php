<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function resume(Request $request, JobApplication $application): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->id === $application->user_id
            || ($user->isApprovedEmployer() && $user->id === $application->jobListing->user_id), 403);
        abort_unless(Storage::disk('local')->exists($application->resume_path), 404);

        return Storage::disk('local')->response($application->resume_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
