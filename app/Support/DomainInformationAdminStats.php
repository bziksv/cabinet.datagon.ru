<?php

namespace App\Support;

use App\DomainInformation;
use App\DomainInformationConfig;
use App\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

class DomainInformationAdminStats
{
    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public static function snapshot(): array
    {
        $projects = DomainInformation::query()
            ->with([
                'user' => static function ($query) {
                    $query->select('id', 'email', 'name', 'telegram_bot_active', 'chat_id', 'last_online_at');
                },
                'user.roles:id,name',
            ])
            ->orderBy('domain')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $emailGlobal = DomainInformationConfig::emailEnabled();
        $telegramGlobal = DomainInformationConfig::telegramEnabled();

        $rows = [];
        foreach ($projects as $project) {
            $user = $project->user;
            $roleNames = $user ? $user->getRoleNames() : collect();
            $tariffCode = CabinetModuleNotifyRegistry::tariffLabel($roleNames);
            $lastOnline = $user && $user->last_online_at
                ? Carbon::parse($user->last_online_at)
                : null;
            $telegramConnected = $user && $user->telegram_bot_active && $user->chat_id;
            $onFree = $user ? !$user->hasPaidTariffRole() : true;
            $canEmail = $user ? $user->canReceiveDomainInformationEmail() : false;

            $dnsDelivery = CabinetModuleNotifyRegistry::deliveryChannels(
                (bool) $project->check_dns,
                (bool) $project->check_dns_email,
                $canEmail,
                $telegramConnected,
                $emailGlobal,
                $telegramGlobal
            );
            $regDelivery = CabinetModuleNotifyRegistry::deliveryChannels(
                (bool) $project->check_registration_date,
                (bool) $project->check_registration_date_email,
                $canEmail,
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
                'tariff_label' => CabinetModuleNotifyRegistry::tariffDisplayName($tariffCode),
                'tariff_sort' => CabinetModuleNotifyRegistry::tariffSortKey($tariffCode),
                'domain' => $project->domain,
                'broken' => (bool) $project->broken,
                'dns_delivery' => $dnsDelivery,
                'dns_delivery_sort' => $dnsDelivery['sort'],
                'dns_delivery_hint' => CabinetModuleNotifyRegistry::deliveryHint(
                    (bool) $project->check_dns || (bool) $project->check_dns_email,
                    $dnsDelivery,
                    $onFree,
                    $telegramConnected,
                    $emailGlobal,
                    $telegramGlobal
                ),
                'registration_delivery' => $regDelivery,
                'registration_delivery_sort' => $regDelivery['sort'],
                'registration_delivery_hint' => CabinetModuleNotifyRegistry::deliveryHint(
                    (bool) $project->check_registration_date || (bool) $project->check_registration_date_email,
                    $regDelivery,
                    $onFree,
                    $telegramConnected,
                    $emailGlobal,
                    $telegramGlobal
                ),
                'last_check' => $project->last_check ?: null,
                'last_check_sort' => $project->last_check
                    ? Carbon::parse($project->last_check)->timestamp
                    : 0,
            ];
        }

        return [
            'summary' => [
                'domains_total' => $projects->count(),
                'domains_notify_dns' => $projects->filter(static function ($p) {
                    return (bool) $p->check_dns || (bool) $p->check_dns_email;
                })->count(),
                'domains_notify_registration' => $projects->filter(static function ($p) {
                    return (bool) $p->check_registration_date || (bool) $p->check_registration_date_email;
                })->count(),
                'domains_broken' => $projects->where('broken', true)->count(),
                'users_with_domains' => $projects->pluck('user_id')->unique()->count(),
                'users_telegram' => User::query()
                    ->where('telegram_bot_active', 1)
                    ->whereNotNull('chat_id')
                    ->count(),
            ],
            'rows' => $rows,
        ];
    }
}
