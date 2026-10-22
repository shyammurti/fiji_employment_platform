<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\MentorshipSession;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Single place for appointment rules so mentorship sessions and interviews
 * never double-book anyone. Sessions and interviews are treated as one hour.
 *
 * - A mentor cannot have two active sessions within the same hour.
 * - A jobseeker cannot have two active sessions within the same hour, nor a
 *   session that clashes with a scheduled interview (and vice versa).
 * - Appointments must be at least an hour ahead and inside a booking window.
 *
 * Module M3 AI Chatbot & Mentorship, developed by Shyam Murti (S11229913); Module M2 Employer Portal, developed by Hritish Pawar (S11219177).
 */
class ScheduleGuard
{
    public const LEAD_MINUTES = 60;
    public const MENTORSHIP_WINDOW_DAYS = 90;
    public const INTERVIEW_WINDOW_DAYS = 180;

    public function assertBookableTime(Carbon $time, string $field, int $windowDays): void
    {
        if ($time->lt(now()->addMinutes(self::LEAD_MINUTES))) {
            throw ValidationException::withMessages([$field => 'Choose a time at least one hour from now. Past times cannot be booked.']);
        }
        if ($time->gt(now()->addDays($windowDays))) {
            throw ValidationException::withMessages([$field => "Choose a time within the next {$windowDays} days."]);
        }
    }

    public function assertMentorshipSlotFree(int $mentorId, int $menteeId, Carbon $time, ?int $ignoreSessionId = null): void
    {
        $sessions = MentorshipSession::whereIn('status', ['pending', 'confirmed'])
            ->when($ignoreSessionId, fn ($query) => $query->whereKeyNot($ignoreSessionId))
            ->where('scheduled_at', '>', $time->copy()->subHour())
            ->where('scheduled_at', '<', $time->copy()->addHour());

        if ((clone $sessions)->where('mentor_id', $mentorId)->exists()) {
            throw ValidationException::withMessages(['scheduled_at' => 'This mentor already has a session within an hour of that time. Choose another time.']);
        }
        if ((clone $sessions)->where('mentee_id', $menteeId)->exists()) {
            throw ValidationException::withMessages(['scheduled_at' => 'You already have a mentorship session within an hour of that time. Choose another time.']);
        }
        if ($this->interviewClash($menteeId, $time)) {
            throw ValidationException::withMessages(['scheduled_at' => 'You have a job interview within an hour of that time. Choose another time.']);
        }
    }

    public function assertInterviewSlotFree(JobApplication $application, Carbon $time): void
    {
        if ($this->interviewClash($application->user_id, $time, $application->id)) {
            throw ValidationException::withMessages(['interview_at' => 'This applicant already has another interview within an hour of that time. Choose another time.']);
        }
        $mentorship = MentorshipSession::whereIn('status', ['pending', 'confirmed'])->where('mentee_id', $application->user_id)
            ->where('scheduled_at', '>', $time->copy()->subHour())->where('scheduled_at', '<', $time->copy()->addHour())->exists();
        if ($mentorship) {
            throw ValidationException::withMessages(['interview_at' => 'This applicant has another appointment within an hour of that time. Choose another time.']);
        }
        $employerBusy = JobApplication::where('status', 'Interview Scheduled')->whereKeyNot($application->id)
            ->whereHas('jobListing', fn ($listing) => $listing->where('user_id', $application->jobListing->user_id))
            ->where('interview_at', '>', $time->copy()->subMinutes(30))->where('interview_at', '<', $time->copy()->addMinutes(30))->exists();
        if ($employerBusy) {
            throw ValidationException::withMessages(['interview_at' => 'You already have an interview within 30 minutes of that time. Choose another time.']);
        }
    }

    private function interviewClash(int $jobseekerId, Carbon $time, ?int $ignoreApplicationId = null): bool
    {
        return JobApplication::where('user_id', $jobseekerId)->where('status', 'Interview Scheduled')
            ->when($ignoreApplicationId, fn ($query) => $query->whereKeyNot($ignoreApplicationId))
            ->where('interview_at', '>', $time->copy()->subHour())->where('interview_at', '<', $time->copy()->addHour())->exists();
    }
}
