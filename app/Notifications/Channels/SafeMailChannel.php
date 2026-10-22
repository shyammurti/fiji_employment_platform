<?php

namespace App\Notifications\Channels;

use App\Support\MailDelivery;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Email is sent alongside every in-app notification. Two safeguards:
 * - demo accounts on reserved domains (example.com, .test) are skipped, so
 *   real SMTP never bounces mail back into the sending inbox;
 * - if the SMTP server is down or misconfigured the problem is logged and the
 *   action (approving an employer, updating an application...) still succeeds.
 */
class SafeMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        $address = $notifiable->routeNotificationFor('mail', $notification);
        if (MailDelivery::isLive() && is_string($address) && MailDelivery::isReservedAddress($address)) {
            Log::info('Email skipped for demo address', ['to' => $address, 'notification' => class_basename($notification)]);

            return null;
        }

        try {
            return parent::send($notifiable, $notification);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
