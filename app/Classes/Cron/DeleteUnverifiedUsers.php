<?php

namespace App\Classes\Cron;

use App\User;
use Illuminate\Support\Facades\Log;

/**
 * Удаление пользователей без подтверждения email старше N дней.
 * Запуск: Laravel scheduler (cron * * * * php artisan schedule:run).
 */
class DeleteUnverifiedUsers
{
    public function __invoke(): void
    {
        if (!filter_var(env('DELETE_UNVERIFIED_USERS', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $days = max(1, (int) env('DELETE_UNVERIFIED_USERS_DAYS', 30));
        $deleted = User::deleteUnverifiedOlderThan($days);

        if ($deleted > 0) {
            Log::info('DeleteUnverifiedUsers: removed ' . $deleted . ' account(s) older than ' . $days . ' days without email verification.');
        }
    }
}
