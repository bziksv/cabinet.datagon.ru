<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ResetTelegramConnectionsForTitloServiceBot extends Migration
{
    /**
     * Смена бота RedBoxServiceBot → TitloServiceBot: подписка через /start у нового бота.
     * telegram_connect_bonus_at не трогаем — повторного бонуса не будет.
     */
    public function up(): void
    {
        DB::table('users')->update([
            'chat_id' => null,
            'telegram_bot_active' => false,
            'telegram_prompt_snoozed_until' => null,
        ]);
    }

    public function down(): void
    {
        // Нельзя восстановить прежние chat_id после смены бота.
    }
}
