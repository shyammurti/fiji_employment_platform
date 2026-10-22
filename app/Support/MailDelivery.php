<?php

namespace App\Support;

/**
 * Whether email actually leaves the server. With MAIL_MAILER=log/array the
 * app cannot deliver verification links, so new addresses are trusted
 * instead of locking people out of their accounts.
 */
class MailDelivery
{
    public static function isLive(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /**
     * Reserved example domains (RFC 2606) used by seeded demo accounts. Mail
     * to them would only bounce back into the sender's inbox.
     */
    public static function isReservedAddress(?string $email): bool
    {
        if (! $email || ! str_contains($email, '@')) {
            return true;
        }
        $domain = strtolower(substr(strrchr($email, '@'), 1));

        return (bool) preg_match('/(^|\.)(example\.(com|org|net)|test|example|invalid|localhost)$/', $domain);
    }
}
