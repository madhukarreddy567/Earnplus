<?php

namespace App\Services;

/**
 * Applies the mailer configuration from DB settings.
 *
 * - If mail_username is set: use SMTP with the stored host/port/credentials
 *   (seeded with Gmail SMTP defaults: smtp.gmail.com:587).
 * - If mail_username is empty: fall back to the "log" mailer so mails are
 *   logged instead of failing (dev + tests never break).
 */
class MailSettings
{
    public static function apply(): void
    {
        $username = (string) setting('mail_username', '');

        if ($username === '') {
            config(['mail.default' => 'log']);

            return;
        }

        config(['mail.default' => 'smtp']);

        $fromAddress = (string) setting('mail_from_address', '');

        config([
            'mail.mailers.smtp.host' => (string) setting('mail_host', 'smtp.gmail.com'),
            'mail.mailers.smtp.port' => setting_int('mail_port', 587),
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => (string) setting('mail_password', ''),
            'mail.mailers.smtp.encryption' => (string) setting('mail_encryption', 'tls'),
            'mail.from.address' => $fromAddress !== '' ? $fromAddress : $username,
            'mail.from.name' => (string) setting('mail_from_name', setting('site_name', 'EarnPlus')),
        ]);
    }
}
