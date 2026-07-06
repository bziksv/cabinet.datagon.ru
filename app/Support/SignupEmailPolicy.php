<?php

namespace App\Support;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

class SignupEmailPolicy
{
    public static function applies(): bool
    {
        if (!config('cabinet-signup-email.enabled', true)) {
            return false;
        }

        $host = strtolower((string) request()->getHost());
        if ($host === '') {
            return false;
        }

        $localHosts = array_map('strtolower', (array) config('cabinet-signup-email.local_hosts', ['localhost', '127.0.0.1']));
        if (in_array($host, $localHosts, true)) {
            return (bool) config('cabinet-signup-email.enforce_on_local', false);
        }

        foreach (config('cabinet-signup-email.hosts', []) as $allowed) {
            if ($host === strtolower((string) $allowed)) {
                return true;
            }
        }

        foreach (config('cabinet-signup-email.host_suffixes', []) as $suffix) {
            $suffix = strtolower((string) $suffix);
            if ($suffix !== '' && substr($host, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return false;
    }

    public static function getDomain(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }

        return (string) substr(strrchr($email, '@'), 1);
    }

    public static function isAllowed(string $email): bool
    {
        $domain = self::getDomain($email);
        if ($domain === '') {
            return false;
        }

        $tlds = implode('|', array_map('preg_quote', config('cabinet-signup-email.allowed_tlds', ['ru', 'su'])));
        if ($tlds !== '' && preg_match('/\.(' . $tlds . ')$/u', $domain)) {
            return true;
        }

        foreach (config('cabinet-signup-email.allowed_providers', []) as $provider) {
            $provider = strtolower((string) $provider);
            if ($provider === '') {
                continue;
            }
            if ($domain === $provider || substr($domain, -strlen('.' . $provider)) === '.' . $provider) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{allowedTlds: array, allowedProviders: array, noticeHtml: string}|null
     */
    public static function clientConfig(): ?array
    {
        if (!self::applies()) {
            return null;
        }

        return [
            'allowedTlds' => config('cabinet-signup-email.allowed_tlds', []),
            'allowedProviders' => config('cabinet-signup-email.allowed_providers', []),
            'noticeHtml' => self::noticeHtml(),
        ];
    }

    public static function noticeHtml(): string
    {
        return view('auth.partials.signup-email-policy-notice')->render();
    }

    public static function appendValidation(Validator $validator, ?string $email): void
    {
        if (!self::applies()) {
            return;
        }

        $email = trim((string) $email);
        $validator->after(static function (Validator $v) use ($email) {
            if ($email === '' || self::isAllowed($email)) {
                return;
            }

            self::logBlockedAttempt($email);

            $v->errors()->add('email_policy_html', self::noticeHtml());
            $v->errors()->add('email', (string) __('Signup email policy blocked short'));
        });
    }

    public static function logBlockedAttempt(string $email): void
    {
        Log::info('signup.email_policy.blocked', [
            'email' => $email,
            'domain' => self::getDomain($email),
            'host' => request()->getHost(),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 300),
        ]);
    }
}
