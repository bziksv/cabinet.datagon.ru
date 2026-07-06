<?php

namespace App\Support;

use Illuminate\Support\Collection;

class CabinetModuleNotifyRegistry
{
    private const TARIFF_ORDER = [
        'Free' => 1,
        'Optimal' => 2,
        'Ultimate' => 3,
        'Maximum' => 4,
    ];

    /**
     * @param  Collection|\Illuminate\Database\Eloquent\Collection  $roleNames
     */
    public static function tariffLabel($roleNames): string
    {
        foreach (['Maximum', 'Ultimate', 'Optimal', 'Free'] as $code) {
            if ($roleNames->contains($code)) {
                return $code;
            }
        }

        if ($roleNames->contains('user')) {
            return 'Free';
        }

        $first = $roleNames->first(static function ($name) {
            return $name !== 'user';
        });

        return $first ?: '—';
    }

    public static function tariffDisplayName(string $code): string
    {
        if ($code === '—' || $code === '') {
            return '—';
        }

        return (string) __($code);
    }

    public static function tariffSortKey(string $code): int
    {
        return self::TARIFF_ORDER[$code] ?? 99;
    }

    /**
     * @return array{email: bool, telegram: bool, mode: string, sort: int}
     */
    public static function deliveryChannels(
        bool $notifyTelegram,
        bool $notifyEmail,
        bool $canReceiveEmail,
        bool $telegramConnected,
        bool $emailGlobal,
        bool $telegramGlobal
    ): array {
        if (!$notifyTelegram && !$notifyEmail) {
            return [
                'email' => false,
                'telegram' => false,
                'mode' => 'off',
                'sort' => 0,
            ];
        }

        $email = $notifyEmail && $emailGlobal && $canReceiveEmail;
        $telegram = $notifyTelegram && $telegramGlobal && $telegramConnected;

        if (!$email && !$telegram) {
            return [
                'email' => false,
                'telegram' => false,
                'mode' => 'none',
                'sort' => 1,
            ];
        }

        if ($email && $telegram) {
            return [
                'email' => true,
                'telegram' => true,
                'mode' => 'both',
                'sort' => 4,
            ];
        }

        if ($email) {
            return [
                'email' => true,
                'telegram' => false,
                'mode' => 'email',
                'sort' => 3,
            ];
        }

        return [
            'email' => false,
            'telegram' => true,
            'mode' => 'telegram',
            'sort' => 2,
        ];
    }

    /**
     * @param array{email: bool, telegram: bool, mode: string, sort: int} $delivery
     */
    public static function deliveryHint(
        bool $notifyEnabled,
        array $delivery,
        bool $onFree,
        bool $telegramConnected,
        bool $emailGlobal,
        bool $telegramGlobal
    ): string {
        if (!$notifyEnabled) {
            return (string) __('Site monitoring registry notify off hint');
        }

        if ($delivery['mode'] === 'both') {
            return (string) __('Site monitoring registry notify both hint');
        }

        if ($delivery['mode'] === 'email') {
            return (string) __('Site monitoring registry notify email hint');
        }

        if ($delivery['mode'] === 'telegram') {
            return (string) __('Site monitoring registry notify telegram hint');
        }

        $parts = [];
        if (!$telegramGlobal) {
            $parts[] = (string) __('Site monitoring registry notify blocked telegram global');
        } elseif (!$telegramConnected) {
            $parts[] = (string) __('Site monitoring registry notify blocked telegram user');
        }
        if (!$emailGlobal) {
            $parts[] = (string) __('Site monitoring registry notify blocked email global');
        } elseif ($onFree) {
            $parts[] = (string) __('Site monitoring registry notify blocked email free');
        }

        return $parts !== []
            ? implode(' ', $parts)
            : (string) __('Site monitoring registry notify none hint');
    }
}
