<?php

namespace App\Support;

use App\Services\NotificationAdminTestService;
use App\Support\NotificationDispatchLogger;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник каналов уведомлений для админки /admin/notifications.
 */
class UserNotificationsRegistry
{
    public static function snapshot(): array
    {
        return Cache::remember('cabinet.users.notifications.snapshot', now()->addMinutes(5), static function () {
            return [
                'channels' => self::channelsForView(),
                'rules' => self::rulesForView(),
                'modals' => self::modalsForView(),
                'modules' => self::modulesForView(),
                'event_groups' => self::eventGroupsForView(),
                'stats' => self::stats(),
            ];
        });
    }

    /**
     * Плоская таблица событий для /admin/notifications (без кэша — нужны флаги тестов).
     *
     * @return list<array<string, mixed>>
     */
    public static function tableRowsForView(NotificationAdminTestService $tester, ?User $user): array
    {
        $channelLabels = self::channelLabelMap();
        $rows = [];
        $eventIds = [];

        foreach ((array) config('cabinet-users-notifications.events', []) as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                $eventId = (string) ($item['id'] ?? '');
                if ($eventId !== '') {
                    $eventIds[] = $eventId;
                }
            }
        }

        $dispatchStats = NotificationDispatchLogger::statsForEvents(array_values(array_unique($eventIds)));

        foreach ((array) config('cabinet-users-notifications.events', []) as $group) {
            $moduleTitle = __($group['title'] ?? '');
            $moduleUrl = self::routeUrl($group['route'] ?? null, $group['route_fragment'] ?? null);

            foreach ((array) ($group['items'] ?? []) as $item) {
                $eventId = (string) ($item['id'] ?? '');
                $channels = (array) ($item['channels'] ?? []);
                $channelBadges = [];
                foreach ($channels as $channelKey) {
                    if (isset($channelLabels[$channelKey])) {
                        $channelBadges[] = $channelLabels[$channelKey];
                    }
                }

                $rows[] = [
                    'id' => $eventId,
                    'module' => $moduleTitle,
                    'module_url' => $moduleUrl,
                    'title' => __($item['title'] ?? ''),
                    'trigger' => __($item['trigger'] ?? ''),
                    'audience' => __($item['audience'] ?? ''),
                    'tariff' => __($item['tariff'] ?? ''),
                    'cron' => $item['cron'] ?? null,
                    'channel_badges' => $channelBadges,
                    'can_preview_modal' => $tester->supportsModalPreview($eventId),
                    'can_test_telegram' => $tester->supportsTelegram($eventId),
                    'can_test_email' => $tester->supportsEmail($eventId),
                    'telegram_ready' => $user ? $user->isTelegramConnected() : false,
                    'dispatch_stats' => $dispatchStats[$eventId] ?? [
                        'today' => ['telegram' => 0, 'email' => 0],
                        'yesterday' => ['telegram' => 0, 'email' => 0],
                        'month' => ['telegram' => 0, 'email' => 0],
                    ],
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function channelsForView(): array
    {
        $out = [];
        foreach ((array) config('cabinet-users-notifications.channels', []) as $key => $channel) {
            $out[] = array_merge($channel, [
                'key' => $key,
                'title' => __($channel['title'] ?? $key),
                'description' => __($channel['description'] ?? ''),
            ]);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rulesForView(): array
    {
        $out = [];
        foreach ((array) config('cabinet-users-notifications.rules', []) as $rule) {
            $out[] = [
                'icon' => $rule['icon'] ?? 'bi-info-circle',
                'color' => $rule['color'] ?? 'secondary',
                'title' => __($rule['title'] ?? ''),
                'body' => __($rule['body'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function modalsForView(): array
    {
        $out = [];
        foreach ((array) config('cabinet-users-notifications.modals', []) as $modal) {
            $out[] = [
                'key' => $modal['key'] ?? '',
                'title' => __($modal['title'] ?? ''),
                'description' => __($modal['description'] ?? ''),
                'queue_priority' => (int) ($modal['queue_priority'] ?? 0),
                'pages' => __($modal['pages'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function modulesForView(): array
    {
        $out = [];
        foreach ((array) config('cabinet-users-notifications.modules', []) as $module) {
            $url = self::routeUrl($module['route'] ?? null, $module['route_fragment'] ?? null);
            $adminUrl = self::routeUrl($module['admin_route'] ?? null);

            $out[] = [
                'slug' => $module['slug'] ?? '',
                'title' => __($module['title'] ?? ''),
                'hint' => __($module['hint'] ?? ''),
                'url' => $url,
                'admin_url' => $adminUrl,
                'admin_label' => $adminUrl ? __('Users notify module admin link') : null,
                'cron' => $module['cron'] ?? null,
                'modal' => !empty($module['modal']),
                'telegram' => !empty($module['telegram']),
                'email_event' => !empty($module['email_event']),
                'email_service' => !empty($module['email_service']),
                'badge_only' => !empty($module['badge_only']),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function eventGroupsForView(): array
    {
        $channelLabels = self::channelLabelMap();
        $out = [];

        foreach ((array) config('cabinet-users-notifications.events', []) as $group) {
            $url = self::routeUrl($group['route'] ?? null, $group['route_fragment'] ?? null);
            $adminUrl = self::routeUrl($group['admin_route'] ?? null);
            $items = [];

            foreach ((array) ($group['items'] ?? []) as $item) {
                $channels = (array) ($item['channels'] ?? []);
                $channelBadges = [];
                foreach ($channels as $channelKey) {
                    if (isset($channelLabels[$channelKey])) {
                        $channelBadges[] = $channelLabels[$channelKey];
                    }
                }

                $items[] = [
                    'id' => $item['id'] ?? '',
                    'title' => __($item['title'] ?? ''),
                    'trigger' => __($item['trigger'] ?? ''),
                    'audience' => __($item['audience'] ?? ''),
                    'tariff' => __($item['tariff'] ?? ''),
                    'cron' => $item['cron'] ?? null,
                    'code_ref' => $item['code_ref'] ?? null,
                    'channels' => $channels,
                    'channel_badges' => $channelBadges,
                    'examples' => self::examplesForView($item),
                ];
            }

            $out[] = [
                'slug' => $group['slug'] ?? '',
                'title' => __($group['title'] ?? ''),
                'url' => $url,
                'admin_url' => $adminUrl,
                'admin_label' => $adminUrl ? __('Users notify module admin link') : null,
                'items' => $items,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array<string, string>>
     */
    private static function examplesForView(array $item): array
    {
        $map = [
            'example_telegram' => ['key' => 'telegram', 'label' => 'Users notify example telegram'],
            'example_email' => ['key' => 'email', 'label' => 'Users notify example email'],
            'example_modal' => ['key' => 'modal', 'label' => 'Users notify example modal'],
        ];
        $out = [];

        foreach ($map as $field => $meta) {
            if (empty($item[$field])) {
                continue;
            }
            $text = __($item[$field]);
            if ($text === $item[$field] || trim($text) === '') {
                continue;
            }
            $out[] = [
                'type' => $meta['key'],
                'label' => __($meta['label']),
                'text' => $text,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function channelLabelMap(): array
    {
        $channels = (array) config('cabinet-users-notifications.channels', []);

        return [
            'modal' => [
                'key' => 'modal',
                'label' => __($channels['modal']['title'] ?? 'modal'),
                'icon' => $channels['modal']['icon'] ?? 'bi-window-stack',
                'color' => $channels['modal']['color'] ?? 'primary',
            ],
            'telegram' => [
                'key' => 'telegram',
                'label' => __($channels['telegram']['title'] ?? 'telegram'),
                'icon' => $channels['telegram']['icon'] ?? 'bi-telegram',
                'color' => $channels['telegram']['color'] ?? 'info',
            ],
            'email_event' => [
                'key' => 'email_event',
                'label' => __($channels['email_event']['title'] ?? 'email_event'),
                'icon' => $channels['email_event']['icon'] ?? 'bi-envelope-exclamation',
                'color' => $channels['email_event']['color'] ?? 'email',
            ],
            'email_service' => [
                'key' => 'email_service',
                'label' => __($channels['email_service']['title'] ?? 'email_service'),
                'icon' => $channels['email_service']['icon'] ?? 'bi-envelope-paper',
                'color' => $channels['email_service']['color'] ?? 'secondary',
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function stats(): array
    {
        $stats = [
            'telegram_users' => (int) User::query()->whereNotNull('chat_id')->where('chat_id', '!=', '')->count(),
            'verified_email' => (int) User::query()->whereNotNull('email_verified_at')->count(),
            'site_mon_notify_projects' => 0,
            'domain_info_telegram_flags' => 0,
        ];

        if (Schema::hasTable('domain_monitoring') && Schema::hasColumn('domain_monitoring', 'send_notification')) {
            $stats['site_mon_notify_projects'] = (int) DB::table('domain_monitoring')->where('send_notification', 1)->count();
        }

        if (Schema::hasTable('domain_information')) {
            $query = DB::table('domain_information');
            if (Schema::hasColumn('domain_information', 'check_dns')) {
                $query->where(function ($q) {
                    $q->where('check_dns', 1);
                    if (Schema::hasColumn('domain_information', 'check_registration_date')) {
                        $q->orWhere('check_registration_date', 1);
                    }
                });
            }
            $stats['domain_info_telegram_flags'] = (int) $query->count();
        }

        return $stats;
    }

    private static function routeUrl(?string $name, ?string $fragment = null): ?string
    {
        if ($name === null || $name === '' || !Route::has($name)) {
            return null;
        }

        $url = route($name);
        if ($fragment) {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }
}
