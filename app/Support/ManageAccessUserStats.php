<?php

namespace App\Support;

use App\User;

/**
 * Сводка по пользователям для /manage-access (правая колонка).
 */
class ManageAccessUserStats
{
    public static function snapshot(): array
    {
        $total = (int) User::count();
        $verified = (int) User::whereNotNull('email_verified_at')->count();
        $telegram = (int) User::telegramConnected()->count();

        return [
            'total' => $total,
            'verified' => $verified,
            'telegram' => $telegram,
            'verified_percent' => $total > 0 ? round($verified / $total * 100, 1) : 0.0,
            'telegram_percent' => $total > 0 ? round($telegram / $total * 100, 1) : 0.0,
        ];
    }
}
