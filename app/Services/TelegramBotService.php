<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected $url = 'https://api.telegram.org/bot';
    protected $token;
    protected $chat_id;

    public function __construct(int $chat_id)
    {
        $this->token = config('app.telegram_bot_token');
        $this->setChatId($chat_id);
    }

    public function updateUserChatID(string $email)
    {
        return User::where('email', $email)->update(['chat_id' => $this->getChatId(), 'telegram_bot_active' => 1]);
    }

    public function sendMsg(string $text, ?array $replyMarkup = null): bool
    {
        $payload = [
            'text' => $text,
            'chat_id' => $this->getChatId(),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode(
                $replyMarkup,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $ch = curl_init($this->api() . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            Log::warning('Telegram sendMessage failed', [
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'response' => is_string($body) ? mb_substr($body, 0, 500) : null,
                'chat_id' => $this->getChatId(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Telegram не принимает localhost / 127.0.0.1 в inline-кнопках (400 Bad Request).
     */
    public static function supportsInlineUrlButton(string $url): bool
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        return !in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true);
    }

    public function getChatId(): int
    {
        return $this->chat_id;
    }

    public function setChatId($chat_id): void
    {
        $this->chat_id = $chat_id;
    }

    private function api(): string
    {
        return $this->url . $this->token;
    }
}
