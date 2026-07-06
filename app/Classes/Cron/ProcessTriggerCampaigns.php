<?php

namespace App\Classes\Cron;

use App\Services\Finance\TriggerCampaignMailService;
use App\Services\Finance\TriggerCampaignService;
use App\Services\UserNotificationPreferenceService;
use App\TriggerCampaign;
use App\User;
use Illuminate\Support\Facades\Log;

/**
 * Автоотправка триггерных рассылок (win-back по неактивности).
 * Запуск: scheduler everyMinute — лимит send_rate_per_minute на кампанию.
 * Ручная проверка: php artisan finance:process-trigger-campaigns --dry-run
 */
class ProcessTriggerCampaigns
{
    public function __invoke(): void
    {
        $result = static::run(false);
        if ($result['sent'] > 0 || $result['failed'] > 0) {
            Log::info($result['message']);
        }
    }

    /**
     * @return array{
     *     campaigns: int,
     *     candidates: int,
     *     sent: int,
     *     skipped: int,
     *     failed: int,
     *     message: string
     * }
     */
    public static function run(bool $dryRun = false): array
    {
        $campaigns = TriggerCampaign::query()
            ->where('is_active', true)
            ->where('is_paused', false)
            ->orderBy('trigger_days')
            ->get();

        $campaignService = app(TriggerCampaignService::class);
        $mailService = app(TriggerCampaignMailService::class);
        $preferences = app(UserNotificationPreferenceService::class);
        $actor = static::systemActor();

        $totals = [
            'campaigns' => $campaigns->count(),
            'candidates' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if ($campaigns->isEmpty()) {
            return $totals + [
                'message' => 'ProcessTriggerCampaigns: no running campaigns',
            ];
        }

        if ($actor === null && !$dryRun && $campaigns->contains(static function (TriggerCampaign $campaign) {
            return $campaign->sendsPromo();
        })) {
            return $totals + [
                'message' => 'ProcessTriggerCampaigns: no admin user for promo creation',
            ];
        }

        foreach ($campaigns as $campaign) {
            $limit = $campaign->resolvedSendRatePerMinute();
            $users = $campaignService->audienceQuery($campaign)
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($users as $user) {
                $totals['candidates']++;

                if (!$preferences->isEnabled($user, $preferences->triggerPreferenceKey($campaign))) {
                    $totals['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                $dispatch = null;

                try {
                    $dispatch = $campaignService->createDispatchForUser($campaign, $user, $actor ?: $user, false);
                    $mailService->sendToUser($campaign, $user, $dispatch);
                    $campaignService->markDispatchSent($dispatch);
                    $totals['sent']++;
                } catch (\Throwable $e) {
                    if ($dispatch !== null) {
                        $campaignService->markDispatchFailed($dispatch, $e->getMessage());
                    }
                    $totals['failed']++;
                    Log::warning('ProcessTriggerCampaigns: send failed', [
                        'campaign_id' => $campaign->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $totals + [
            'message' => $dryRun
                ? sprintf(
                    'ProcessTriggerCampaigns (dry-run): %d campaign(s), %d candidate(s), %d would skip (opt-out).',
                    $totals['campaigns'],
                    $totals['candidates'],
                    $totals['skipped']
                )
                : sprintf(
                    'ProcessTriggerCampaigns: sent %d, skipped %d (opt-out), failed %d.',
                    $totals['sent'],
                    $totals['skipped'],
                    $totals['failed']
                ),
        ];
    }

    private static function systemActor(): ?User
    {
        $roles = (array) config('cabinet-finance-admin.exclude_admin_roles', ['admin', 'Super Admin']);

        return User::query()
            ->whereHas('roles', static function ($query) use ($roles) {
                $query->whereIn('name', $roles);
            })
            ->orderBy('id')
            ->first();
    }
}
