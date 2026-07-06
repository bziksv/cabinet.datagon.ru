<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

class MailFromResolver
{
    /**
     * @return array{address: string, name: string}
     */
    public static function resolve(?string $locale = null): array
    {
        $config = SmtpSettingsRegistry::activeMailConfig() ?? SmtpSettingsRegistry::envDefaults();
        $locale = self::normalizeLocale($locale ?: App::getLocale());

        return [
            'address' => trim((string) ($config['from_address'] ?? '')),
            'name' => self::nameForLocale($locale, $config),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function nameForLocale(string $locale, array $config): string
    {
        $ru = trim((string) ($config['from_name'] ?? ''));
        $en = trim((string) ($config['from_name_en'] ?? ''));

        if ($locale === 'en') {
            return $en !== '' ? $en : ($ru !== '' ? $ru : (string) config('app.name'));
        }

        return $ru !== '' ? $ru : ($en !== '' ? $en : (string) config('app.name'));
    }

    private static function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        return in_array($locale, ['ru', 'en'], true) ? $locale : 'ru';
    }
}
