<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Отправка с обходом внешнего SMTP для локальных ящиков.
 *
 * smtp.bz → MX mail.prime-ltd.su (тот же сервер) падает с
 * «Not allowed sender for this authenticated user» для From info@titlo.ru.
 * Для доменов с локальным MX шлём через sendmail/Exim напрямую.
 */
class CabinetMail
{
    /**
     * @param string|array $to
     */
    public static function send($to, Mailable $mailable): void
    {
        $emails = is_array($to) ? $to : [$to];
        $useLocal = false;
        foreach ($emails as $email) {
            if (is_string($email) && self::isLocalRecipientDomain($email)) {
                $useLocal = true;
                break;
            }
            if (is_array($email)) {
                foreach (array_keys($email) as $addr) {
                    if (self::isLocalRecipientDomain((string) $addr)) {
                        $useLocal = true;
                        break 2;
                    }
                }
            }
        }

        if (!$useLocal) {
            Mail::to($to)->send($mailable);

            return;
        }

        $previous = [
            'driver' => config('mail.driver'),
            'host' => config('mail.host'),
            'port' => config('mail.port'),
            'encryption' => config('mail.encryption'),
            'username' => config('mail.username'),
            'password' => config('mail.password'),
        ];

        try {
            config([
                'mail.driver' => 'sendmail',
                'mail.host' => null,
                'mail.port' => null,
                'mail.encryption' => null,
                'mail.username' => null,
                'mail.password' => null,
            ]);
            self::flushMailer();
            Mail::to($to)->send($mailable);
            Log::info('CabinetMail: local sendmail for recipient on local MX', [
                'to' => $emails,
            ]);
        } finally {
            config([
                'mail.driver' => $previous['driver'],
                'mail.host' => $previous['host'],
                'mail.port' => $previous['port'],
                'mail.encryption' => $previous['encryption'],
                'mail.username' => $previous['username'],
                'mail.password' => $previous['password'],
            ]);
            SmtpSettingsRegistry::applyToConfig();
            self::flushMailer();
        }
    }

    public static function isLocalRecipientDomain(string $email): bool
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }
        $domain = substr($email, $at + 1);
        if ($domain === '') {
            return false;
        }

        $configured = config('mail.local_recipient_domains', []);
        if (!is_array($configured)) {
            $configured = [];
        }
        $configured = array_map('strtolower', array_filter(array_map('trim', $configured)));

        if (in_array($domain, $configured, true)) {
            return true;
        }

        // Поддомены явных локальных зон (mail.prime-ltd.su и т.п. как получатель редко, но на всякий).
        foreach ($configured as $root) {
            if ($root !== '' && substr($domain, -strlen('.' . $root)) === '.' . $root) {
                return true;
            }
        }

        return false;
    }

    private static function flushMailer(): void
    {
        app()->forgetInstance('swift.mailer');
        app()->forgetInstance('mailer');
        Mail::clearResolvedInstances();
    }
}
