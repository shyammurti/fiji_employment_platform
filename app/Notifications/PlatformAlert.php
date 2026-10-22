<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformAlert extends Notification
{
    public function __construct(
        public string $message,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(\Illuminate\Support\Str::limit(strtok($this->message, "\n"), 78))
            ->greeting("Hi {$notifiable->name},")
            ->line($this->message);

        if ($this->actionText && $this->actionUrl) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'platform_alert',
            'message' => $this->message,
            'action_text' => $this->actionText,
            'action_url' => $this->actionUrl,
        ];
    }
}
