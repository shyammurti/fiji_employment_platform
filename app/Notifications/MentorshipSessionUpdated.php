<?php

namespace App\Notifications;

use App\Models\MentorshipSession;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class MentorshipSessionUpdated extends Notification
{
    public function __construct(public MentorshipSession $session)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Mentorship session {$this->session->status}: {$this->session->topic}")
            ->greeting('Hi '.$notifiable->name.',')
            ->line("Your mentorship session on \"{$this->session->topic}\" is now: {$this->session->status}.")
            ->line('Scheduled for (Fiji): '.$this->session->scheduled_at->timezone('Pacific/Fiji')->format('D, d M Y g:i A'))
            ->action('View Career Hub', route('career.hub'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mentorship_session_updated',
            'mentorship_session_id' => $this->session->id,
            'topic' => $this->session->topic,
            'status' => $this->session->status,
            'scheduled_at' => $this->session->scheduled_at->toIso8601String(),
            'message' => "Mentorship session \"{$this->session->topic}\" is now: {$this->session->status}",
        ];
    }
}
