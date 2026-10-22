<?php

namespace App\Notifications;

use App\Models\JobListing;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Dispatched to a jobseeker when their application is scored with a
 * strong skill match (FR4.2: "...calculate a match score and notify
 * qualified candidates").
 *
 * Module M4 Application Tracking & Skill Matching, developed by Ammara Dean (S11220213).
 */
class NewQualifiedMatch extends Notification
{
    public function __construct(public JobListing $listing, public int $matchPercentage)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New matching vacancy: {$this->listing->title} ({$this->matchPercentage}% skill match)")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your profile is a {$this->matchPercentage}% skill match for \"{$this->listing->title}\" at {$this->listing->company}.")
            ->action('View vacancy', route('jobs.show', $this->listing));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_qualified_match',
            'action_url' => route('jobs.show', $this->listing),
            'job_listing_id' => $this->listing->id,
            'job_title' => $this->listing->title,
            'company' => $this->listing->company,
            'match_percentage' => $this->matchPercentage,
            'message' => "You're a {$this->matchPercentage}% skill match for \"{$this->listing->title}\" at {$this->listing->company}",
        ];
    }
}
