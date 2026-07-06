<?php

namespace App\Services\Finance;

use App\Mail\TriggerCampaignMail;
use App\Support\NotificationLocale;
use App\Services\UserNotificationPreferenceService;
use App\TriggerCampaign;
use App\TriggerCampaignDispatch;
use App\User;
use Illuminate\Support\Facades\Mail;

class TriggerCampaignMailService
{
    /**
     * @return array{ok: bool, message: string, dispatch: ?TriggerCampaignDispatch}
     */
    public function sendTestToAdmin(
        TriggerCampaign $campaign,
        User $admin,
        TriggerCampaignService $campaigns,
        string $lang = 'ru'
    ): array {
        if (trim((string) $admin->email) === '') {
            return ['ok' => false, 'message' => __('Trigger campaign test no email'), 'dispatch' => null];
        }

        $lang = in_array($lang, ['ru', 'en'], true) ? $lang : 'ru';
        $dispatch = $campaigns->createDispatchForUser($campaign, $admin, $admin, true);
        $tariffContext = $campaigns->tariffContextForUser($campaign, $admin, true);

        if ($campaign->isTariffExpiringTrigger() && $tariffContext === null) {
            $tariffContext = [
                'tariff_name' => __('Trigger campaign email tariff test name'),
                'active_to' => now()->addDays(max(1, (int) $campaign->trigger_days))->locale($lang)->isoFormat('LL'),
                'days_left' => max(1, (int) $campaign->trigger_days),
            ];
        }

        if ($campaign->isTariffExpiredTrigger() && $tariffContext === null) {
            $tariffContext = [
                'tariff_name' => __('Trigger campaign email tariff test name'),
                'active_to' => now()->subDays(max(1, (int) $campaign->trigger_days))->locale($lang)->isoFormat('LL'),
                'days_left' => 0,
                'is_expired' => true,
                'days_since_expiry' => max(1, (int) $campaign->trigger_days),
            ];
        }

        try {
            NotificationLocale::override($lang);
            Mail::to($admin->email)->send(new TriggerCampaignMail(
                $campaign,
                $admin,
                $dispatch->promoCode,
                true,
                null,
                $tariffContext
            ));
            $campaigns->markDispatchSent($dispatch);

            return [
                'ok' => true,
                'message' => __('Trigger campaign test sent', [
                    'email' => $admin->email,
                    'lang' => strtoupper($lang),
                ]),
                'dispatch' => $dispatch->fresh(['promoCode']),
            ];
        } catch (\Throwable $e) {
            $campaigns->markDispatchFailed($dispatch, $e->getMessage());

            return [
                'ok' => false,
                'message' => __('Trigger campaign test failed', ['error' => $e->getMessage()]),
                'dispatch' => $dispatch->fresh(['promoCode']),
            ];
        } finally {
            NotificationLocale::clear();
        }
    }

    public function sendToUser(TriggerCampaign $campaign, User $user, TriggerCampaignDispatch $dispatch): void
    {
        $preferences = app(UserNotificationPreferenceService::class);
        $key = $preferences->triggerPreferenceKey($campaign);

        if (!$preferences->isEnabled($user, $key)) {
            throw new \RuntimeException('User opted out of trigger campaign mail');
        }

        $tariffContext = app(TriggerCampaignService::class)->tariffContextForUser($campaign, $user, false);

        Mail::to($user->email)->send(new TriggerCampaignMail(
            $campaign,
            $user,
            $dispatch->promoCode,
            false,
            $dispatch->tracking_token,
            $tariffContext
        ));
    }
}
