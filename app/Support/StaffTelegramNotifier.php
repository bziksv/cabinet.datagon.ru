<?php

namespace App\Support;

use App\FeatureIdea;
use App\Services\TelegramBotService;
use App\SupportTicket;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Telegram-уведомления staff (admin / Super Admin) о новых тикетах и идеях.
 */
class StaffTelegramNotifier
{
    public const EVENT_TICKET_CREATED = 'support-ticket-created';
    public const EVENT_TICKET_MESSAGE = 'support-ticket-message';
    public const EVENT_IDEA_CREATED = 'ideas-created';

    /**
     * @return Collection|User[]
     */
    public static function staffWithTelegram(?int $exceptUserId = null): Collection
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'Super Admin'])
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            $roleIds = collect([1, 3]);
        }

        $userIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('role_id', $roleIds->all())
            ->distinct()
            ->pluck('model_id');

        if ($userIds->isEmpty()) {
            return collect();
        }

        $query = User::query()
            ->whereIn('id', $userIds->all())
            ->where('telegram_bot_active', true)
            ->whereNotNull('chat_id')
            ->where('chat_id', '!=', '');

        if ($exceptUserId) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->get(['id', 'name', 'last_name', 'email', 'chat_id', 'lang']);
    }

    public static function notifyTicketCreated(SupportTicket $ticket): int
    {
        $ticket->loadMissing(['user:id,name,last_name,email', 'messages']);
        $author = $ticket->user;
        $firstMessage = $ticket->messages()->orderBy('id')->first();
        $bodyPreview = self::preview($firstMessage ? (string) $firstMessage->body : '');

        $url = route('support.show', $ticket);
        $text = self::buildMessage([
            '🔔 ' . __('Staff telegram new ticket title'),
            __('Staff telegram from') . ': ' . self::authorLine($author),
            __('Subject') . ': ' . self::escape((string) $ticket->subject),
            $bodyPreview !== '' ? (__('Message') . ":\n" . $bodyPreview) : null,
            __('Go to') . ': ' . $url,
        ]);

        return self::broadcast(
            $text,
            $url,
            self::EVENT_TICKET_CREATED,
            $author ? (int) $author->id : null,
            $author ? (int) $author->id : null
        );
    }

    public static function notifyTicketUserMessage(SupportTicket $ticket, string $body, User $author): int
    {
        $url = route('support.show', $ticket);
        $text = self::buildMessage([
            '💬 ' . __('Staff telegram ticket reply title'),
            __('Ticket') . ' #' . $ticket->id . ': ' . self::escape((string) $ticket->subject),
            __('Staff telegram from') . ': ' . self::authorLine($author),
            __('Message') . ":\n" . self::preview($body),
            __('Go to') . ': ' . $url,
        ]);

        return self::broadcast(
            $text,
            $url,
            self::EVENT_TICKET_MESSAGE,
            (int) $author->id,
            (int) $author->id
        );
    }

    public static function notifyIdeaCreated(FeatureIdea $idea): int
    {
        $idea->loadMissing(['user:id,name,last_name,email']);
        $author = $idea->user;
        $url = route('ideas.index', ['tab' => 'moderation']);

        $text = self::buildMessage([
            '💡 ' . __('Staff telegram new idea title'),
            __('Staff telegram from') . ': ' . self::authorLine($author),
            __('Title') . ': ' . self::escape((string) $idea->title),
            __('Idea description') . ":\n" . self::preview((string) $idea->body),
            __('Go to') . ': ' . $url,
        ]);

        return self::broadcast(
            $text,
            $url,
            self::EVENT_IDEA_CREATED,
            $author ? (int) $author->id : null,
            $author ? (int) $author->id : null
        );
    }

    /**
     * Тестовое сообщение одному пользователю (админка / ручной прогон).
     */
    public static function sendTestToUser(User $user, string $eventId, string $source = 'admin_test'): bool
    {
        if (!$user->isTelegramConnected()) {
            return false;
        }

        $prefix = '[' . __('Test') . '] ';

        if ($eventId === self::EVENT_TICKET_CREATED) {
            $url = url('/support');
            $text = $prefix . self::buildMessage([
                '🔔 ' . __('Staff telegram new ticket title'),
                __('Staff telegram from') . ': Demo User &lt;demo@example.com&gt;',
                __('Subject') . ': ' . self::escape(__('Staff telegram test ticket subject')),
                __('Message') . ":\n" . self::escape(__('Staff telegram test ticket body')),
                __('Go to') . ': ' . $url,
            ]);
        } elseif ($eventId === self::EVENT_TICKET_MESSAGE) {
            $url = url('/support');
            $text = $prefix . self::buildMessage([
                '💬 ' . __('Staff telegram ticket reply title'),
                __('Ticket') . ' #0: ' . self::escape(__('Staff telegram test ticket subject')),
                __('Staff telegram from') . ': Demo User &lt;demo@example.com&gt;',
                __('Message') . ":\n" . self::escape(__('Staff telegram test ticket body')),
                __('Go to') . ': ' . $url,
            ]);
        } elseif ($eventId === self::EVENT_IDEA_CREATED) {
            $url = url('/ideas?tab=moderation');
            $text = $prefix . self::buildMessage([
                '💡 ' . __('Staff telegram new idea title'),
                __('Staff telegram from') . ': Demo User &lt;demo@example.com&gt;',
                __('Title') . ': ' . self::escape(__('Staff telegram test idea title')),
                __('Idea description') . ":\n" . self::escape(__('Staff telegram test idea body')),
                __('Go to') . ': ' . $url,
            ]);
        } else {
            return false;
        }

        $markup = self::urlButton($url);

        return (new TelegramBotService((int) $user->chat_id))->sendMsg($text, $markup, [
            'event_id' => $eventId,
            'user_id' => (int) $user->id,
            'source' => $source,
        ]);
    }

    /**
     * @param  list<string|null>  $lines
     */
    private static function buildMessage(array $lines): string
    {
        return implode("\n", array_values(array_filter($lines, static function ($line) {
            return $line !== null && $line !== '';
        })));
    }

    private static function broadcast(
        string $text,
        string $url,
        string $eventId,
        ?int $exceptUserId,
        ?int $sourceUserId
    ): int {
        $staff = self::staffWithTelegram($exceptUserId);
        if ($staff->isEmpty()) {
            return 0;
        }

        $markup = self::urlButton($url);
        $sent = 0;

        foreach ($staff as $admin) {
            try {
                $ok = (new TelegramBotService((int) $admin->chat_id))->sendMsg($text, $markup, [
                    'event_id' => $eventId,
                    'user_id' => $sourceUserId,
                    'source' => 'system',
                ]);
                if ($ok) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('StaffTelegramNotifier failed', [
                    'event_id' => $eventId,
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    private static function urlButton(string $url): ?array
    {
        if (!TelegramBotService::supportsInlineUrlButton($url)) {
            return null;
        }

        return [
            'inline_keyboard' => [[
                [
                    'text' => __('Open'),
                    'url' => $url,
                ],
            ]],
        ];
    }

    private static function authorLine(?User $user): string
    {
        if (!$user) {
            return '—';
        }

        $name = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($name === '') {
            $name = 'User #' . $user->id;
        }

        return self::escape($name) . ' &lt;' . self::escape((string) $user->email) . '&gt;';
    }

    private static function preview(string $text, int $limit = 400): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit - 1) . '…';
        }

        return self::escape($text);
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
