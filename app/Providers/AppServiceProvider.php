<?php

namespace App\Providers;

use App\Notifications\Channels\SafeMailChannel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MailChannel::class, SafeMailChannel::class);
    }

    public function boot(): void
    {
        // M5 (Admin Governance) access gate: `can:admin` in routes/web.php.
        Gate::define('admin', function ($user) {
            return $user->role === 'admin';
        });

        // Email header logo: attach it inline to any email whose HTML uses cid:logo.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            if (str_contains((string) $event->message->getHtmlBody(), 'cid:logo')) {
                $event->message->embedFromPath(public_path('images/logo.png'), 'logo', 'image/png');
            }
        });

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Verify your email for the Fiji Employment Hub')
            ->greeting('Bula '.$notifiable->name.'!')
            ->line('Please confirm this is your email address so we can send you application updates, job matches and mentorship reminders.')
            ->action('Verify email address', $url)
            ->line('This link expires in 60 minutes. If you did not create an account, you can ignore this email.'));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Reset your Fiji Employment Hub password')
            ->greeting('Bula '.$notifiable->name.',')
            ->line('We received a request to reset the password for your account.')
            ->action('Choose a new password', route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('This link expires in '.config('auth.passwords.users.expire', 60).' minutes.')
            ->line('If you did not ask to reset your password, you can ignore this email. Your password will not change.'));

        // Every list uses the range / per-page / numbered pagination bar.
        Paginator::defaultView('pagination.numbered');

        // Proposal risk plan: require stronger passwords than length alone.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
