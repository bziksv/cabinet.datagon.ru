<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationDispatchLogger
{
    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_EMAIL = 'email';

    public static function log(string $eventId, string $channel, ?int $userId = null, string $source = 'system'): void
    {
        if ($eventId === '' || !Schema::hasTable('notification_dispatch_logs')) {
            return;
        }

        if (!in_array($channel, [self::CHANNEL_TELEGRAM, self::CHANNEL_EMAIL], true)) {
            return;
        }

        DB::table('notification_dispatch_logs')->insert([
            'event_id' => $eventId,
            'channel' => $channel,
            'user_id' => $userId,
            'source' => $source,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $eventIds
     * @return array<string, array{today: array{telegram: int, email: int}, yesterday: array{telegram: int, email: int}, month: array{telegram: int, email: int}}>
     */
    public static function statsForEvents(array $eventIds): array
    {
        $empty = static function (): array {
            return [
                'today' => ['telegram' => 0, 'email' => 0],
                'yesterday' => ['telegram' => 0, 'email' => 0],
                'month' => ['telegram' => 0, 'email' => 0],
            ];
        };

        $out = [];
        foreach ($eventIds as $eventId) {
            $out[$eventId] = $empty();
        }

        if ($eventIds === [] || !Schema::hasTable('notification_dispatch_logs')) {
            return $out;
        }

        $todayStart = Carbon::today();
        $yesterdayStart = Carbon::yesterday();
        $monthStart = Carbon::now()->startOfMonth();

        $rows = DB::table('notification_dispatch_logs')
            ->select('event_id', 'channel')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS cnt_today', [$todayStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS cnt_yesterday', [$yesterdayStart, $todayStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS cnt_month', [$monthStart])
            ->whereIn('event_id', $eventIds)
            ->whereIn('channel', [self::CHANNEL_TELEGRAM, self::CHANNEL_EMAIL])
            ->groupBy('event_id', 'channel')
            ->get();

        foreach ($rows as $row) {
            $eventId = (string) $row->event_id;
            if (!isset($out[$eventId])) {
                continue;
            }

            $channel = $row->channel === self::CHANNEL_EMAIL ? 'email' : 'telegram';
            $out[$eventId]['today'][$channel] = (int) $row->cnt_today;
            $out[$eventId]['yesterday'][$channel] = (int) $row->cnt_yesterday;
            $out[$eventId]['month'][$channel] = (int) $row->cnt_month;
        }

        return $out;
    }

    /**
     * @return array{today: array{telegram: int, email: int}, yesterday: array{telegram: int, email: int}, month: array{telegram: int, email: int}}
     */
    public static function totals(): array
    {
        $totals = [
            'today' => ['telegram' => 0, 'email' => 0],
            'yesterday' => ['telegram' => 0, 'email' => 0],
            'month' => ['telegram' => 0, 'email' => 0],
        ];

        if (!Schema::hasTable('notification_dispatch_logs')) {
            return $totals;
        }

        $todayStart = Carbon::today();
        $yesterdayStart = Carbon::yesterday();
        $monthStart = Carbon::now()->startOfMonth();

        $rows = DB::table('notification_dispatch_logs')
            ->select('channel')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS cnt_today', [$todayStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS cnt_yesterday', [$yesterdayStart, $todayStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS cnt_month', [$monthStart])
            ->whereIn('channel', [self::CHANNEL_TELEGRAM, self::CHANNEL_EMAIL])
            ->groupBy('channel')
            ->get();

        foreach ($rows as $row) {
            $channel = $row->channel === self::CHANNEL_EMAIL ? 'email' : 'telegram';
            $totals['today'][$channel] = (int) $row->cnt_today;
            $totals['yesterday'][$channel] = (int) $row->cnt_yesterday;
            $totals['month'][$channel] = (int) $row->cnt_month;
        }

        return $totals;
    }

    /**
     * @param  object  $notification
     */
    public static function resolveEmailEventId(string $notificationClass, $notification = null): ?string
    {
        if ($notificationClass === \App\Notifications\BrokenDomainNotification::class && $notification !== null) {
            return $notification->dispatchEventId ?? 'site-mon-broken';
        }

        $map = [
            \App\Notifications\RegisterPasswordEmail::class => 'profile-password-reset',
            \App\Notifications\BrokenLinkNotification::class => 'backlink-email-link',
            \App\Notifications\BrokenDomainNotification::class => 'site-mon-broken',
            \App\Notifications\RepairDomainNotification::class => 'site-mon-repaired',
            \App\Notifications\sendNotificationAboutChangeDNS::class => 'domain-dns-changed',
            \App\Notifications\sendNotificationAboutExpirationRegistrationPeriod::class => 'domain-expiration',
            \App\Notifications\MonitoringLimitExhaustedNotification::class => 'monitoring-limit-exhausted',
            \App\Notifications\DomainInformationNotification::class => 'domain-dns-changed',
            \Illuminate\Auth\Notifications\VerifyEmail::class => 'auth-verify-email',
        ];

        return $map[$notificationClass] ?? null;
    }
}
