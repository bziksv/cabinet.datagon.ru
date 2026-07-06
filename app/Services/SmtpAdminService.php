<?php

namespace App\Services;

use App\Support\MailFromResolver;
use App\Support\SmtpSettingsRegistry;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;

class SmtpAdminService
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function sendTestEmail(string $to): array
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => __('SMTP admin invalid email')];
        }

        $config = SmtpSettingsRegistry::activeMailConfig() ?? SmtpSettingsRegistry::envDefaults();
        $host = trim((string) ($config['host'] ?? ''));
        if ($host === '') {
            return ['ok' => false, 'message' => __('SMTP admin host missing')];
        }

        $fromAddress = trim((string) ($config['from_address'] ?? ''));
        if ($fromAddress === '') {
            return ['ok' => false, 'message' => __('SMTP admin from missing')];
        }

        $locale = Auth::user()->lang ?? App::getLocale();
        App::setLocale($locale);
        $from = MailFromResolver::resolve($locale);

        try {
            $transport = (new Swift_SmtpTransport($host, (int) ($config['port'] ?? 587)))
                ->setUsername($config['username'] ?? null)
                ->setPassword($config['password'] ?? null);

            $encryption = $config['encryption'] ?? null;
            if ($encryption) {
                $transport->setEncryption($encryption);
            }

            $mailer = new Swift_Mailer($transport);

            $message = (new Swift_Message(__('SMTP admin test subject')))
                ->setFrom([$fromAddress => $from['name']])
                ->setTo([$to])
                ->setBody(
                    __('SMTP admin test body', ['time' => now()->format('Y-m-d H:i:s')]),
                    'text/plain',
                    'utf-8'
                );

            $sent = $mailer->send($message);
            if ($sent < 1) {
                return ['ok' => false, 'message' => __('SMTP admin test failed')];
            }

            return [
                'ok' => true,
                'message' => __('SMTP admin test sent', ['email' => $to]),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function statusSummary(): array
    {
        $settings = SmtpSettingsRegistry::forAdmin();
        $active = SmtpSettingsRegistry::activeMailConfig();

        return [
            'using_db' => !empty($settings['enabled']),
            'host' => $active['host'] ?? ($settings['host'] ?: $settings['env_host']),
            'from_address' => $active['from_address'] ?? ($settings['from_address'] ?: $settings['env_from_address']),
            'provider_label' => $settings['provider_label'],
            'password_set' => !empty($settings['password_set']),
        ];
    }
}
