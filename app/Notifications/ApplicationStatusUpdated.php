<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Module M4 Application Tracking & Skill Matching, developed by Ammara Dean (S11220213).
 */
class ApplicationStatusUpdated extends Notification
{
    public function __construct(public JobApplication $application)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->application->jobListing;

        return (new MailMessage)
            ->subject("Application update: {$job->title} at {$job->company}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your application for \"{$job->title}\" at {$job->company} has moved to: {$this->application->status}.")
            ->when($this->application->status === 'Interview Scheduled' && $this->application->interview_at, function ($mail) {
                $mail->line('Interview time (Fiji): '.$this->application->interview_at->timezone('Pacific/Fiji')->format('D, d M Y g:i A'));
            })
            ->action('View my applications', route('applications.mine'))
            ->line('Thank you for using the Fiji Employment & Skill-Matching Platform.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_status_updated',
            'job_application_id' => $this->application->id,
            'job_title' => $this->application->jobListing->title,
            'company' => $this->application->jobListing->company,
            'status' => $this->application->status,
            'interview_at' => $this->application->interview_at?->toIso8601String(),
            'message' => "Your application for \"{$this->application->jobListing->title}\" is now: {$this->application->status}",
        ];
    }
}
