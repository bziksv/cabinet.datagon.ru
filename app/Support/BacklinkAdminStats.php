<?php

namespace App\Support;

use App\BacklinkConfig;
use App\ProjectTracking;
use App\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

class BacklinkAdminStats
{
    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public static function snapshot(): array
    {
        $projects = ProjectTracking::query()
            ->with([
                'user' => static function ($query) {
                    $query->select('id', 'email', 'name', 'telegram_bot_active', 'chat_id', 'last_online_at');
                },
                'user.roles:id,name',
            ])
            ->orderBy('project_name')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $emailGlobal = BacklinkConfig::emailEnabled();
        $telegramGlobal = BacklinkConfig::telegramEnabled();

        $rows = [];
        $linksTotal = 0;
        $linksBroken = 0;

        foreach ($projects as $project) {
            $user = $project->user;
            $roleNames = $user ? $user->getRoleNames() : collect();
            $tariffCode = CabinetModuleNotifyRegistry::tariffLabel($roleNames);
            $lastOnline = $user && $user->last_online_at
                ? Carbon::parse($user->last_online_at)
                : null;
            $telegramConnected = $user && $user->telegram_bot_active && $user->chat_id;
            $onFree = $user ? !$user->hasPaidTariffRole() : true;
            $canEmail = $user ? $user->canReceiveBacklinkEmail() : false;

            $notifyTelegram = (bool) $project->notify_telegram;
            $notifyEmail = (bool) $project->notify_email;
            $delivery = CabinetModuleNotifyRegistry::deliveryChannels(
                $notifyTelegram,
                $notifyEmail,
                $canEmail,
                $telegramConnected,
                $emailGlobal,
                $telegramGlobal
            );

            $totalLinks = (int) $project->total_link;
            $brokenLinks = (int) ($project->total_broken_link ?? 0);
            $linksTotal += $totalLinks;
            $linksBroken += $brokenLinks;

            $rows[] = [
                'user_id' => (int) $project->user_id,
                'email' => $user ? $user->email : '—',
                'name' => $user && $user->name ? $user->name : '',
                'last_online_at' => $lastOnline ? $lastOnline->format('d.m.Y H:i') : null,
                'last_online_human' => $lastOnline ? $lastOnline->diffForHumans() : null,
                'last_online_sort' => $lastOnline ? $lastOnline->timestamp : 0,
                'tariff_code' => $tariffCode,
                'tariff_label' => CabinetModuleNotifyRegistry::tariffDisplayName($tariffCode),
                'tariff_sort' => CabinetModuleNotifyRegistry::tariffSortKey($tariffCode),
                'project_id' => $project->id,
                'project_name' => $project->project_name,
                'total_link' => $totalLinks,
                'total_broken_link' => $brokenLinks,
                'notify_delivery_mode' => $delivery['mode'],
                'notify_delivery_sort' => $delivery['sort'],
                'notify_telegram' => $delivery['telegram'],
                'notify_email' => $delivery['email'],
                'notify_delivery_hint' => CabinetModuleNotifyRegistry::deliveryHint(
                    $notifyTelegram || $notifyEmail,
                    $delivery,
                    $onFree,
                    $telegramConnected,
                    $emailGlobal,
                    $telegramGlobal
                ),
            ];
        }

        return [
            'summary' => [
                'projects_total' => $projects->count(),
                'projects_notify_on' => $projects->filter(static function ($p) {
                    return (bool) $p->notify_telegram || (bool) $p->notify_email;
                })->count(),
                'links_total' => $linksTotal,
                'links_broken' => $linksBroken,
                'users_with_projects' => $projects->pluck('user_id')->unique()->count(),
                'users_telegram' => User::query()
                    ->where('telegram_bot_active', 1)
                    ->whereNotNull('chat_id')
                    ->count(),
            ],
            'rows' => $rows,
        ];
    }
}
