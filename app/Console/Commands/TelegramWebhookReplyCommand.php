<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramWebhookReplyCommand extends Command
{
    protected $signature = 'telegram:webhook-reply {payload}';

    protected $description = 'Send deferred Telegram reply after webhook returned 200';

    public function handle(): int
    {
        $raw = base64_decode((string) $this->argument('payload'), true);
        if ($raw === false) {
            return 1;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['chat_id']) || empty($data['text'])) {
            return 1;
        }

        $chatId = (int) $data['chat_id'];
        $text = (string) $data['text'];

        $sent = (new TelegramBotService($chatId))->sendMsg($text);
        if (!$sent) {
            Log::warning('Telegram webhook deferred reply failed', [
                'chat_id' => $chatId,
                'error' => TelegramBotService::$lastError,
            ]);

            return 1;
        }

        return 0;
    }
}
