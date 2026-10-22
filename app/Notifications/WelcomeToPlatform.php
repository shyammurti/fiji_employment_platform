<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once after registration. Phone-only accounts get the in-app welcome
 * banner instead because there is no address to email.
 */
class WelcomeToPlatform extends Notification
{
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Welcome to the Fiji Employment Hub')
            ->greeting('Bula '.$notifiable->name.'!')
            ->line('Your '.$notifiable->role.' account has been created.');

        return match ($notifiable->role) {
            'employer' => $mail
                ->line('An administrator will review your business details and registration certificate. We will email you as soon as your account is approved.')
                ->line('Once approved you can post vacancies, search verified candidates and schedule interviews.')
                ->action('View your dashboard', route('dashboard')),
            'mentor' => $mail
                ->line('Jobseekers can now find you in the Career Hub and request one-hour sessions. You confirm each request.')
                ->action('Review your mentor profile', route('profile.edit')),
            default => $mail
                ->line('Complete your profile so employers can find you and the platform can match you to vacancies:')
                ->line('1. Add your skills, qualification and experience.')
                ->line('2. Upload your academic credential for verification.')
                ->line('3. Visit the Career Hub to practise interviews and build your CV.')
                ->action('Complete your profile', route('profile.edit')),
        };
    }
}
