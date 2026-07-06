<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\DB;

class TelegramConnectBonusService
{
    public function bonusAmount(): int
    {
        if (!config('cabinet-telegram.connect_bonus_enabled', true)) {
            return 0;
        }

        return max(0, (int) config('cabinet-telegram.connect_bonus_amount', 500));
    }

    public function userEligibleForBonus(User $user): bool
    {
        return $this->bonusAmount() > 0 && $user->telegram_connect_bonus_at === null;
    }

    /**
     * @return array{linked: bool, bonus_granted: bool, user: ?User}
     */
    public function linkUserFromTelegramStart(int $chatId, string $email): array
    {
        return DB::transaction(function () use ($chatId, $email) {
            /** @var User|null $user */
            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return ['linked' => false, 'bonus_granted' => false, 'user' => null];
            }

            $user->chat_id = (string) $chatId;
            $user->telegram_bot_active = true;

            $bonusGranted = false;
            if ($this->userEligibleForBonus($user)) {
                $this->grantBonus($user);
                $bonusGranted = true;
            } else {
                $user->save();
            }

            return [
                'linked' => true,
                'bonus_granted' => $bonusGranted,
                'user' => $user->fresh(),
            ];
        });
    }

    private function grantBonus(User $user): void
    {
        $amount = $this->bonusAmount();

        $user->balances()->create([
            'sum' => $amount,
            'status' => 1,
            'source' => __('Telegram connect bonus balance source'),
        ]);

        $user->balance = (int) $user->balance + $amount;
        $user->telegram_connect_bonus_at = now();
        $user->save();
    }
}
