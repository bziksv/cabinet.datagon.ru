<?php

namespace App\Support;

use App\SmtpSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP для email-уведомлений (таблица smtp_settings, одна запись default).
 */
class SmtpSettingsRegistry
{
    /**
     * @return array<string, mixed>
     */
    public static function envDefaults(): array
    {
        return [
            'enabled' => false,
            'provider_label' => null,
            'driver' => (string) config('mail.driver', env('MAIL_DRIVER', 'smtp')),
            'host' => (string) config('mail.host', env('MAIL_HOST', '')),
            'port' => (int) config('mail.port', env('MAIL_PORT', 587)),
            'encryption' => config('mail.encryption'),
            'username' => config('mail.username'),
            'password' => config('mail.password'),
            'from_address' => (string) config('mail.from.address', env('MAIL_FROM_ADDRESS', '')),
            'from_name' => (string) config('mail.from.name', env('MAIL_FROM_NAME', '')),
            'from_name_en' => (string) env('MAIL_FROM_NAME_EN', ''),
        ];
    }

    public static function model(): ?SmtpSetting
    {
        if (!Schema::hasTable('smtp_settings')) {
            return null;
        }

        return SmtpSetting::query()->find(SmtpSetting::PRIMARY_ID);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        $row = self::model();
        $env = self::envDefaults();

        if ($row === null) {
            return array_merge($env, [
                'source' => 'env',
                'password_set' => !empty($env['password']),
                'password_masked' => self::maskSecret($env['password'] ?? null),
            ]);
        }

        return [
            'enabled' => (bool) $row->enabled,
            'provider_label' => $row->provider_label,
            'driver' => $row->driver ?: 'smtp',
            'host' => $row->host,
            'port' => (int) $row->port,
            'encryption' => $row->encryption,
            'username' => $row->username,
            'from_address' => $row->from_address,
            'from_name' => $row->from_name,
            'from_name_en' => $row->from_name_en,
            'source' => $row->enabled ? 'db' : 'env',
            'password_set' => !empty($row->password),
            'password_masked' => self::maskSecret(self::decryptPassword($row->password)),
            'env_host' => $env['host'],
            'env_from_address' => $env['from_address'],
            'updated_at' => optional($row->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>|null Активные параметры отправки (DB если enabled, иначе null).
     */
    public static function activeMailConfig(): ?array
    {
        $row = self::model();
        if ($row === null || !$row->enabled) {
            return null;
        }

        if (trim((string) $row->host) === '' || trim((string) $row->from_address) === '') {
            return null;
        }

        return [
            'driver' => $row->driver ?: 'smtp',
            'host' => trim((string) $row->host),
            'port' => (int) $row->port,
            'encryption' => $row->encryption ?: null,
            'username' => $row->username,
            'password' => self::decryptPassword($row->password),
            'from_address' => trim((string) $row->from_address),
            'from_name' => self::nullableString($row->from_name),
            'from_name_en' => self::nullableString($row->from_name_en),
        ];
    }

    public static function applyToConfig(): void
    {
        $active = self::activeMailConfig();
        if ($active === null) {
            return;
        }

        config([
            'mail.driver' => $active['driver'],
            'mail.host' => $active['host'],
            'mail.port' => $active['port'],
            'mail.encryption' => $active['encryption'],
            'mail.username' => $active['username'],
            'mail.password' => $active['password'],
            'mail.from.address' => $active['from_address'],
            'mail.from.name' => MailFromResolver::nameForLocale('ru', $active),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data): SmtpSetting
    {
        $row = self::model() ?? new SmtpSetting(['id' => SmtpSetting::PRIMARY_ID]);

        $row->enabled = !empty($data['enabled']);
        $row->provider_label = self::nullableString($data['provider_label'] ?? null);
        $row->driver = self::nullableString($data['driver'] ?? 'smtp') ?: 'smtp';
        $row->host = self::nullableString($data['host'] ?? null);
        $row->port = (int) ($data['port'] ?? 587);
        $row->encryption = self::normalizeEncryption($data['encryption'] ?? null);
        $row->username = self::nullableString($data['username'] ?? null);
        $row->from_address = self::nullableString($data['from_address'] ?? null);
        $row->from_name = self::nullableString($data['from_name'] ?? null);
        $row->from_name_en = self::nullableString($data['from_name_en'] ?? null);

        $password = trim((string) ($data['password'] ?? ''));
        if ($password !== '') {
            $row->password = Crypt::encryptString($password);
        }

        $row->save();

        return $row;
    }

    public static function importFromEnv(): SmtpSetting
    {
        $env = self::envDefaults();

        return self::save([
            'enabled' => false,
            'provider_label' => __('SMTP admin import env label'),
            'driver' => $env['driver'],
            'host' => $env['host'],
            'port' => $env['port'],
            'encryption' => $env['encryption'],
            'username' => $env['username'],
            'password' => $env['password'],
            'from_address' => $env['from_address'],
            'from_name' => $env['from_name'],
            'from_name_en' => $env['from_name_en'],
        ]);
    }

    public static function decryptPassword(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public static function maskSecret(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $len = mb_strlen($value);
        if ($len <= 2) {
            return str_repeat('*', $len);
        }

        return mb_substr($value, 0, 1) . str_repeat('*', min(8, $len - 2)) . mb_substr($value, -1);
    }

    private static function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function normalizeEncryption($value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '' || $value === 'none') {
            return null;
        }

        return in_array($value, ['tls', 'ssl'], true) ? $value : null;
    }
}
