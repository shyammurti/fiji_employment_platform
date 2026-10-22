<?php

use App\Http\Controllers\Admin\GovernanceController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobListingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------
// Public Routes
// ---------------------------------------------------------------------
Route::get('/', [JobListingController::class, 'home'])->name('home');
Route::view('/help', 'help')->name('help');
Route::get('/jobs', [JobListingController::class, 'index'])->name('jobs.index'); // Approved listings only
Route::get('/jobs/{jobListing}', [JobListingController::class, 'show'])->whereNumber('jobListing')->name('jobs.show');
Route::get('/jobs/{jobListing}/document', [JobListingController::class, 'document'])->whereNumber('jobListing')->name('jobs.document');

// ---------------------------------------------------------------------
// Authenticated Common Routes (Jobseekers, Mentors, Employers, Admins)
// ---------------------------------------------------------------------
// Profile Management (M1: Jobseeker Profile & Verification - FR1.1 to FR1.5).
// Available before email verification so a mistyped address can be corrected.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ProfileController::class, 'dashboard'])->name('dashboard');

    // Application Tracking & Skill Match (M4 - FR4.1 to FR4.5)
    Route::post('/jobs/{jobListing}/apply', [JobApplicationController::class, 'store'])->whereNumber('jobListing')->name('jobs.apply');
    Route::get('/my-applications', [JobApplicationController::class, 'myApplications'])->name('applications.mine');
    Route::get('/applications/{application}/resume', [\App\Http\Controllers\DocumentController::class, 'resume'])->name('applications.resume');

    // AI Chatbot & Mentorship Hub (M3 - FR3.1 to FR3.5)
    Route::get('/career-hub', [ChatbotController::class, 'hub'])->name('career.hub');
    Route::get('/chatbot/mentor-context', [ChatbotController::class, 'mentorContext'])->name('chatbot.mentorContext');
    Route::post('/chatbot/message', [ChatbotController::class, 'handleMessage'])->middleware('throttle:30,1')->name('chatbot.message');
    Route::post('/chatbot/stream', [ChatbotController::class, 'stream'])->middleware('throttle:30,1')->name('chatbot.stream');
    Route::post('/chatbot/warmup', [ChatbotController::class, 'warmup'])->middleware('throttle:6,1')->name('chatbot.warmup');
    Route::post('/chatbot/conversations', [ChatbotController::class, 'createConversation'])->name('chatbot.conversations.store');
    Route::get('/chatbot/conversations/{conversation}', [ChatbotController::class, 'showConversation'])->name('chatbot.conversations.show');
    Route::delete('/chatbot/conversations/{conversation}', [ChatbotController::class, 'destroyConversation'])->name('chatbot.conversations.destroy');
    Route::post('/chatbot/cv-builder', [ChatbotController::class, 'buildCv'])->name('chatbot.cvBuilder');
    Route::post('/career-hub/sessions', [JobApplicationController::class, 'scheduleSession'])->name('mentorship.schedule');
    Route::post('/career-hub/feedback', [JobApplicationController::class, 'submitFeedback'])->name('mentorship.feedback');
    Route::patch('/career-hub/sessions/{mentorshipSession}', [JobApplicationController::class, 'updateSessionStatus'])->name('mentorship.updateStatus');

    // In-app notifications
    Route::patch('/notifications/read-all', [ProfileController::class, 'markAllNotificationsRead'])->name('notifications.readAll');
    Route::patch('/notifications/{id}/read', [ProfileController::class, 'markNotificationRead'])->name('notifications.read');
    Route::get('/employers/{user}/business-document', [ProfileController::class, 'businessDocument'])->name('employers.document');
    Route::get('/credentials/{user}/document', [VerificationController::class, 'showDocument'])->name('credentials.document');
});

// ---------------------------------------------------------------------
// Employer Registration (any authenticated user may request employer role)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/employer/register', [ProfileController::class, 'employerRegistrationForm'])->name('employer.register.form');
    Route::post('/employer/register', [ProfileController::class, 'employerRegister'])->name('employer.register');
});

// ---------------------------------------------------------------------
// Employer Routes (M2: Employer Portal - FR2.1 to FR2.5) - requires role=employer
// AND employer_approval_status=approved
// ---------------------------------------------------------------------
Route::middleware(['auth', 'employer.approved'])->group(function () {
    Route::get('/jobs/create', [JobListingController::class, 'create'])->name('jobs.create');
    Route::post('/jobs', [JobListingController::class, 'store'])->name('jobs.store');
    Route::get('/jobs/{jobListing}/edit', [JobListingController::class, 'edit'])->whereNumber('jobListing')->name('jobs.edit');
    Route::patch('/jobs/{jobListing}', [JobListingController::class, 'update'])->whereNumber('jobListing')->name('jobs.update');
    Route::patch('/jobs/{jobListing}/archive', [JobListingController::class, 'archive'])->whereNumber('jobListing')->name('jobs.archive');
    Route::patch('/jobs/{jobListing}/close', [JobListingController::class, 'close'])->whereNumber('jobListing')->name('jobs.close');
    Route::patch('/jobs/{jobListing}/complete-recruitment', [JobListingController::class, 'completeRecruitment'])->whereNumber('jobListing')->name('jobs.complete');

    Route::get('/employer/jobs', [JobListingController::class, 'employerIndex'])->name('employer.jobs.index');
    Route::get('/employer/candidates', [JobListingController::class, 'candidateSearch'])->name('employer.candidates.index');
    Route::get('/employer/candidates/{user}', [JobListingController::class, 'candidateShow'])->name('employer.candidates.show');

    Route::get('/employer/applications', [JobApplicationController::class, 'employerIndex'])->name('employer.applications.index');
    Route::get('/jobs/{jobListing}/applications', [JobApplicationController::class, 'showApplicationsForJob'])->whereNumber('jobListing')->name('employer.jobs.applications');
    Route::patch('/employer/applications/{application}', [JobApplicationController::class, 'updateStatus'])->name('employer.applications.update');
});

// ---------------------------------------------------------------------
// Admin & Governance Routes (M5 - Admin Governance & Verification)
// ---------------------------------------------------------------------
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [\App\Http\Controllers\Admin\UserAccessController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [\App\Http\Controllers\Admin\UserAccessController::class, 'update'])->name('users.update');
    Route::get('/dashboard', [GovernanceController::class, 'dashboard'])->name('dashboard');

    Route::get('/employers/pending', [GovernanceController::class, 'pendingEmployers'])->name('employers.pending');
    Route::patch('/employers/{user}/approve', [GovernanceController::class, 'approveEmployer'])->name('employers.approve');
    Route::patch('/employers/{user}/reject', [GovernanceController::class, 'rejectEmployer'])->name('employers.reject');

    Route::get('/jobs/pending', [GovernanceController::class, 'pendingJobs'])->name('jobs.pending');
    Route::get('/jobs/{jobListing}/review', [GovernanceController::class, 'reviewJob'])->whereNumber('jobListing')->name('jobs.review');
    Route::patch('/jobs/{jobListing}/request-changes', [GovernanceController::class, 'requestJobChanges'])->whereNumber('jobListing')->name('jobs.requestChanges');
    Route::patch('/jobs/{jobListing}/archive', [GovernanceController::class, 'archiveJob'])->whereNumber('jobListing')->name('jobs.archive');
    Route::patch('/jobs/{jobListing}/approve', [GovernanceController::class, 'approveJob'])->whereNumber('jobListing')->name('jobs.approve');
    Route::patch('/jobs/{jobListing}/reject', [GovernanceController::class, 'rejectJob'])->whereNumber('jobListing')->name('jobs.reject');

    Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications.index');
    Route::patch('/verifications/{user}/toggle', [VerificationController::class, 'toggleVerification'])->name('verifications.toggle');

    Route::post('/governance/request-info', [GovernanceController::class, 'requestInfo'])->name('governance.requestInfo');
});

require __DIR__.'/auth.php';
