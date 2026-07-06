<?php

namespace App\Services;

use App\Support\TelegramStartPayload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TelegramUpdateHandler
{
    public function handleMessage(array $message): ?string
    {
        if (empty($message['chat']['id']) || empty($message['text'])) {
            return null;
        }

        $chatId = (int) $message['chat']['id'];
        $text = explode(' ', trim((string) $message['text']), 2);

        if (!isset($text[1])) {
            return __('Telegram connect start hint');
        }

        $email = TelegramStartPayload::decodeEmail($text[1]);
        if ($email === null) {
            return __('Telegram connect start invalid');
        }

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email'],
        ]);

        if (!$validator->passes()) {
            return __('Telegram connect start invalid');
        }

        $result = app(TelegramConnectBonusService::class)
            ->linkUserFromTelegramStart($chatId, $email);

        if (!$result['linked']) {
            Log::warning('Telegram /start: user not found', ['email' => $email, 'chat_id' => $chatId]);

            return __('Telegram connect start user not found');
        }

        if ($result['bonus_granted']) {
            $amount = app(TelegramConnectBonusService::class)->bonusAmount();

            return __('Telegram connect bonus success message', [
                'amount' => number_format($amount, 0, '.', ' '),
            ]);
        }

        return __('You have successfully subscribed to the notification newsletter');
    }
}
