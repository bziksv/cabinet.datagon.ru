<?php

namespace App\Support;

use App\DomainMonitoring;
use App\SiteMonitoringConfig;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class SiteMonitoringAdminStats
{
    private const TARIFF_ORDER = [
        'Free' => 1,
        'Optimal' => 2,
        'Ultimate' => 3,
        'Maximum' => 4,
    ];

    /**
     * @return array{summary: array<string, int|array<int, int>>, rows: array<int, array<string, mixed>>}
     */
    public static function snapshot(): array
    {
        $projects = DomainMonitoring::query()
            ->with([
                'user' => static function ($query) {
                    $query->select('id', 'email', 'name', 'telegram_bot_active', 'chat_id', 'last_online_at');
                },
                'user.roles:id,name',
            ])
            ->orderBy('project_name')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $emailGlobal = SiteMonitoringConfig::emailEnabled();
        $telegramGlobal = SiteMonitoringConfig::telegramEnabled();

        $timingBreakdown = [];
        foreach ([5, 10, 15, 20, 30, 60] as $minutes) {
            $timingBreakdown[$minutes] = 0;
        }

        $rows = [];
        foreach ($projects as $project) {
            $user = $project->user;
            $roleNames = $user ? $user->getRoleNames() : collect();
            $tariffCode = self::tariffLabel($roleNames);
            $lastOnline = $user && $user->last_online_at
                ? Carbon::parse($user->last_online_at)
                : null;

            $telegramConnected = $user && $user->telegram_bot_active && $user->chat_id;
            $notifyTelegram = (bool) $project->notify_telegram;
            $notifyEmail = (bool) $project->notify_email;
            $sendNotification = $notifyTelegram || $notifyEmail;
            $delivery = self::deliveryChannels(
                $notifyTelegram,
                $notifyEmail,
                $user ? $user->canReceiveSiteMonitoringEmail() : false,
                $telegramConnected,
                $emailGlobal,
                $telegramGlobal
            );

            $rows[] = [
                'user_id' => (int) $project->user_id,
                'email' => $user ? $user->email : '—',
                'name' => $user && $user->name ? $user->name : '',
                'last_online_at' => $lastOnline ? $lastOnline->format('d.m.Y H:i') : null,
                'last_online_human' => $lastOnline ? $lastOnline->diffForHumans() : null,
                'last_online_sort' => $lastOnline ? $lastOnline->timestamp : 0,
                'tariff_code' => $tariffCode,
                'tariff_label' => self::tariffDisplayName($tariffCode),
                'tariff_sort' => self::tariffSortKey($tariffCode),
                'on_free' => $user ? !$user->hasPaidTariffRole() : true,
                'telegram' => $telegramConnected,
                'notify_email' => $delivery['email'],
                'notify_telegram' => $delivery['telegram'],
                'notify_telegram_flag' => $notifyTelegram,
                'notify_email_flag' => $notifyEmail,
                'notify_delivery_mode' => $delivery['mode'],
                'notify_delivery_sort' => $delivery['sort'],
                'notify_delivery_hint' => self::deliveryHint(
                    $sendNotification,
                    $delivery,
                    $user ? !$user->hasPaidTariffRole() : true,
                    $telegramConnected,
                    $emailGlobal,
                    $telegramGlobal
                ),
                'project_id' => $project->id,
                'project_name' => $project->project_name,
                'link' => $project->link,
                'timing' => (int) $project->timing,
                'waiting_time' => (int) $project->waiting_time,
                'broken' => (bool) $project->broken,
                'send_notification' => $sendNotification,
                'status' => $project->status,
                'status_label' => $project->status ? __($project->status) : '',
                'code' => $project->code,
                'uptime_percent' => $project->uptime_percent,
                'last_check' => $project->last_check
                    ? Carbon::parse($project->last_check)->format('d.m.Y H:i')
                    : null,
                'last_check_sort' => $project->last_check
                    ? Carbon::parse($project->last_check)->timestamp
                    : 0,
            ];
        }

        foreach ($projects as $project) {
            $timing = (int) $project->timing;
            if (array_key_exists($timing, $timingBreakdown)) {
                $timingBreakdown[$timing]++;
            }
        }

        $distinctUsers = $projects->pluck('user_id')->unique()->count();

        return [
            'summary' => [
                'projects_total' => $projects->count(),
                'projects_notify_on' => $projects->filter(static function ($project) {
                    return (bool) $project->notify_telegram || (bool) $project->notify_email;
                })->count(),
                'projects_broken' => $projects->where('broken', true)->count(),
                'users_with_projects' => $distinctUsers,
                'users_telegram' => User::query()
                    ->where('telegram_bot_active', 1)
                    ->whereNotNull('chat_id')
                    ->count(),
                'timing_breakdown' => $timingBreakdown,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection|\Illuminate\Database\Eloquent\Collection  $roleNames
     */
    private static function tariffLabel($roleNames): string
    {
        foreach (['Maximum', 'Ultimate', 'Optimal', 'Free'] as $code) {
            if ($roleNames->contains($code)) {
                return $code;
            }
        }

        if ($roleNames->contains('user')) {
            return 'Free';
        }

        $first = $roleNames->first(static function ($name) {
            return $name !== 'user';
        });

        return $first ?: '—';
    }

    private static function tariffDisplayName(string $code): string
    {
        if ($code === '—' || $code === '') {
            return '—';
        }

        return (string) __($code);
    }

    private static function tariffSortKey(string $code): int
    {
        return self::TARIFF_ORDER[$code] ?? 99;
    }

    /**
     * @return array{email: bool, telegram: bool, mode: string, sort: int}
     */
    private static function deliveryChannels(
        bool $notifyTelegram,
        bool $notifyEmail,
        bool $canReceiveEmail,
        bool $telegramConnected,
        bool $emailGlobal,
        bool $telegramGlobal
    ): array {
        if (!$notifyTelegram && !$notifyEmail) {
            return [
                'email' => false,
                'telegram' => false,
                'mode' => 'off',
                'sort' => 0,
            ];
        }

        $email = $notifyEmail && $emailGlobal && $canReceiveEmail;
        $telegram = $notifyTelegram && $telegramGlobal && $telegramConnected;

        if (!$email && !$telegram) {
            return [
                'email' => false,
                'telegram' => false,
                'mode' => 'none',
                'sort' => 1,
            ];
        }

        if ($email && $telegram) {
            return [
                'email' => true,
                'telegram' => true,
                'mode' => 'both',
                'sort' => 4,
            ];
        }

        if ($email) {
            return [
                'email' => true,
                'telegram' => false,
                'mode' => 'email',
                'sort' => 3,
            ];
        }

        return [
            'email' => false,
            'telegram' => true,
            'mode' => 'telegram',
            'sort' => 2,
        ];
    }

    /**
     * @param array{email: bool, telegram: bool, mode: string, sort: int} $delivery
     */
    private static function deliveryHint(
        bool $sendNotification,
        array $delivery,
        bool $onFree,
        bool $telegramConnected,
        bool $emailGlobal,
        bool $telegramGlobal
    ): string {
        if (!$sendNotification) {
            return (string) __('Site monitoring registry notify off hint');
        }

        if ($delivery['mode'] === 'both') {
            return (string) __('Site monitoring registry notify both hint');
        }

        if ($delivery['mode'] === 'email') {
            return (string) __('Site monitoring registry notify email hint');
        }

        if ($delivery['mode'] === 'telegram') {
            return (string) __('Site monitoring registry notify telegram hint');
        }

        $parts = [];
        if (!$telegramGlobal) {
            $parts[] = (string) __('Site monitoring registry notify blocked telegram global');
        } elseif (!$telegramConnected) {
            $parts[] = (string) __('Site monitoring registry notify blocked telegram user');
        }
        if (!$emailGlobal) {
            $parts[] = (string) __('Site monitoring registry notify blocked email global');
        } elseif ($onFree) {
            $parts[] = (string) __('Site monitoring registry notify blocked email free');
        }

        return $parts !== []
            ? implode(' ', $parts)
            : (string) __('Site monitoring registry notify none hint');
    }
}
