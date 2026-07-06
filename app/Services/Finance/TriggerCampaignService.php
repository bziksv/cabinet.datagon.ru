<?php

namespace App\Services\Finance;

use App\PromoCode;
use App\TariffPay;
use App\TriggerCampaign;
use App\TriggerCampaignDispatch;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Classes\Tariffs\FreeTariff;
use App\Support\VisitStatisticsHomeProjects;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TriggerCampaignService
{
    /**
     * @return list<TriggerCampaign>
     */
    public function listForAdmin(): array
    {
        return TriggerCampaign::allOrderedForAdmin();
    }

    /**
     * @return array{
     *     pending: int,
     *     sent: int,
     *     redeemed: int,
     *     failed: int,
     *     audience: int,
     *     conversion: float,
     *     opened: int,
     *     open_rate: float
     * }
     */
    public function statsForCampaign(TriggerCampaign $campaign): array
    {
        $rows = TriggerCampaignDispatch::query()
            ->where('trigger_campaign_id', $campaign->id)
            ->where('is_test', false)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $sent = (int) ($rows[TriggerCampaignDispatch::STATUS_SENT] ?? 0)
            + (int) ($rows[TriggerCampaignDispatch::STATUS_REDEEMED] ?? 0);
        $redeemed = (int) ($rows[TriggerCampaignDispatch::STATUS_REDEEMED] ?? 0);
        $opened = (int) TriggerCampaignDispatch::query()
            ->where('trigger_campaign_id', $campaign->id)
            ->where('is_test', false)
            ->whereNotNull('opened_at')
            ->count();

        return [
            'pending' => (int) ($rows[TriggerCampaignDispatch::STATUS_PENDING] ?? 0),
            'sent' => $sent,
            'redeemed' => $redeemed,
            'failed' => (int) ($rows[TriggerCampaignDispatch::STATUS_FAILED] ?? 0),
            'audience' => $this->audienceQuery($campaign)->count(),
            'conversion' => $sent > 0 ? round($redeemed / $sent * 100, 1) : 0.0,
            'opened' => $opened,
            'open_rate' => $sent > 0 ? round($opened / $sent * 100, 1) : 0.0,
        ];
    }

    public function audienceQuery(TriggerCampaign $campaign): Builder
    {
        $query = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('email_verified_at');

        if ($campaign->isInactiveDaysTrigger()) {
            $this->applyInactiveDaysAudience($query, $campaign);
        } elseif ($campaign->isRegisteredNoTopupTrigger()) {
            $this->applyRegisteredNoTopupAudience($query, $campaign);
        } elseif ($campaign->isRegisteredNoToolTrigger()) {
            $this->applyRegisteredNoToolAudience($query, $campaign);
        } elseif ($campaign->isTariffExpiringTrigger()) {
            $this->applyTariffExpiringAudience($query, $campaign);
        } elseif ($campaign->isTariffExpiredTrigger()) {
            $this->applyTariffExpiredAudience($query, $campaign);
        }

        $query->whereDoesntHave('roles', static function (Builder $roleQuery) {
            $roleQuery->whereIn('name', config('cabinet-finance-admin.exclude_admin_roles', ['admin', 'Super Admin']));
        });

        if (!$campaign->usesTariffPayDispatch()) {
            $alreadyDispatched = TriggerCampaignDispatch::query()
                ->where('trigger_campaign_id', $campaign->id)
                ->where('is_test', false)
                ->pluck('user_id');

            if ($alreadyDispatched->isNotEmpty()) {
                $query->whereNotIn('id', $alreadyDispatched);
            }
        }

        return $query;
    }

    private function applyInactiveDaysAudience(Builder $query, TriggerCampaign $campaign): void
    {
        $days = max(1, (int) $campaign->trigger_days);
        $lowerThreshold = Carbon::now()->subDays($days);
        $nextTierDays = $campaign->nextInactiveTierDays();
        $upperThreshold = $nextTierDays !== null
            ? Carbon::now()->subDays($nextTierDays)
            : null;

        $query->where(function (Builder $outer) use ($lowerThreshold, $upperThreshold) {
            $outer->where(function (Builder $online) use ($lowerThreshold, $upperThreshold) {
                $online->whereNotNull('last_online_at')
                    ->where('last_online_at', '<=', $lowerThreshold);
                if ($upperThreshold !== null) {
                    $online->where('last_online_at', '>', $upperThreshold);
                }
            })->orWhere(function (Builder $never) use ($lowerThreshold, $upperThreshold) {
                $never->whereNull('last_online_at')
                    ->where('created_at', '<=', $lowerThreshold);
                if ($upperThreshold !== null) {
                    $never->where('created_at', '>', $upperThreshold);
                }
            });
        });
    }

    private function applyRegisteredNoTopupAudience(Builder $query, TriggerCampaign $campaign): void
    {
        $days = max(1, (int) $campaign->trigger_days);
        $lowerThreshold = Carbon::now()->subDays($days);
        $nextTierDays = $campaign->nextRegisteredNoTopupTierDays();
        $upperThreshold = $nextTierDays !== null
            ? Carbon::now()->subDays($nextTierDays)
            : null;

        $query->where('balance', 0)
            ->where('created_at', '<=', $lowerThreshold);

        if ($upperThreshold !== null) {
            $query->where('created_at', '>', $upperThreshold);
        }

        $query->whereDoesntHave('balances', static function (Builder $balanceQuery) {
            $balanceQuery->where('status', 1)
                ->where('paid_sum', '>', 0);
        });
    }

    private function applyRegisteredNoToolAudience(Builder $query, TriggerCampaign $campaign): void
    {
        $days = max(1, (int) $campaign->trigger_days);
        $lowerThreshold = Carbon::now()->subDays($days);
        $nextTierDays = $campaign->nextRegisteredNoToolTierDays();
        $upperThreshold = $nextTierDays !== null
            ? Carbon::now()->subDays($nextTierDays)
            : null;
        $homeProjectIds = VisitStatisticsHomeProjects::ids();

        $query->where('created_at', '<=', $lowerThreshold);

        if ($upperThreshold !== null) {
            $query->where('created_at', '>', $upperThreshold);
        }

        $query->where(function (Builder $outer) use ($homeProjectIds) {
            $outer->whereDoesntHave('visitStatistics');

            if ($homeProjectIds !== []) {
                $outer->orWhere(function (Builder $onlyHome) use ($homeProjectIds) {
                    $onlyHome->whereHas('visitStatistics')
                        ->whereDoesntHave('visitStatistics', static function (Builder $visitQuery) use ($homeProjectIds) {
                            $visitQuery->whereNotIn('project_id', $homeProjectIds);
                        });
                });
            }
        });
    }

    private function applyTariffExpiringAudience(Builder $query, TriggerCampaign $campaign): void
    {
        $days = max(1, (int) $campaign->trigger_days);
        $targetDate = Carbon::now()->addDays($days)->toDateString();
        $dispatchedPayIds = TriggerCampaignDispatch::query()
            ->where('trigger_campaign_id', $campaign->id)
            ->where('is_test', false)
            ->whereNotNull('tariff_pay_id')
            ->pluck('tariff_pay_id');

        $query->whereHas('pay', static function (Builder $payQuery) use ($targetDate, $dispatchedPayIds) {
            $payQuery->where('status', true)
                ->where('class_tariff', '!=', FreeTariff::class)
                ->whereDate('active_to', $targetDate)
                ->where('active_to', '>', Carbon::now());

            if ($dispatchedPayIds->isNotEmpty()) {
                $payQuery->whereNotIn('id', $dispatchedPayIds);
            }
        });
    }

    private function applyTariffExpiredAudience(Builder $query, TriggerCampaign $campaign): void
    {
        $days = max(1, (int) $campaign->trigger_days);
        $targetDate = Carbon::now()->subDays($days)->toDateString();
        $nextTierDays = $campaign->nextTariffExpiredTierDays();
        $upperDate = $nextTierDays !== null
            ? Carbon::now()->subDays($nextTierDays)->toDateString()
            : null;
        $dispatchedPayIds = TriggerCampaignDispatch::query()
            ->where('trigger_campaign_id', $campaign->id)
            ->where('is_test', false)
            ->whereNotNull('tariff_pay_id')
            ->pluck('tariff_pay_id');

        $query->whereDoesntHave('pay', static function (Builder $payQuery) {
            $payQuery->where('status', true)
                ->where('class_tariff', '!=', FreeTariff::class)
                ->where('active_to', '>', Carbon::now());
        });

        $query->whereHas('pay', static function (Builder $payQuery) use ($targetDate, $upperDate, $dispatchedPayIds) {
            $payQuery->where('class_tariff', '!=', FreeTariff::class)
                ->whereDate('active_to', $targetDate)
                ->where('active_to', '<', Carbon::now());

            if ($upperDate !== null) {
                $payQuery->whereDate('active_to', '>', $upperDate);
            }

            if ($dispatchedPayIds->isNotEmpty()) {
                $payQuery->whereNotIn('id', $dispatchedPayIds);
            }
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCampaign(TriggerCampaign $campaign, array $data): TriggerCampaign
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? $campaign->name)),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'trigger_days' => max(1, (int) ($data['trigger_days'] ?? $campaign->trigger_days)),
            'send_rate_per_minute' => max(
                1,
                min(
                    TriggerCampaign::MAX_SEND_RATE_PER_MINUTE,
                    (int) ($data['send_rate_per_minute'] ?? $campaign->send_rate_per_minute ?: TriggerCampaign::DEFAULT_SEND_RATE_PER_MINUTE)
                )
            ),
            'email_subject' => trim((string) ($data['email_subject'] ?? $campaign->email_subject)),
            'email_intro' => trim((string) ($data['email_intro'] ?? '')) ?: null,
            'email_body' => trim((string) ($data['email_body'] ?? '')) ?: null,
            'email_subject_en' => trim((string) ($data['email_subject_en'] ?? '')) ?: null,
            'email_intro_en' => trim((string) ($data['email_intro_en'] ?? '')) ?: null,
            'email_body_en' => trim((string) ($data['email_body_en'] ?? '')) ?: null,
        ];

        if ($campaign->sendsPromo()) {
            $bonusType = (string) ($data['coupon_bonus_type'] ?? $campaign->couponBonusType());
            if (!in_array($bonusType, [PromoCode::BONUS_FIXED, PromoCode::BONUS_PERCENT], true)) {
                $bonusType = PromoCode::BONUS_FIXED;
            }

            $bonusValue = max(1, (int) ($data['coupon_bonus_value'] ?? $campaign->coupon_bonus_value));
            if ($bonusType === PromoCode::BONUS_PERCENT) {
                $bonusValue = min(100, $bonusValue);
            }

            $payload['coupon_bonus_type'] = $bonusType;
            $payload['coupon_bonus_value'] = $bonusValue;
            $payload['coupon_expires_days'] = max(1, (int) ($data['coupon_expires_days'] ?? $campaign->coupon_expires_days));
        }

        $campaign->update($payload);

        return $campaign->fresh();
    }

    public function toggleCampaign(TriggerCampaign $campaign): TriggerCampaign
    {
        if ($campaign->is_active) {
            $campaign->is_active = false;
            $campaign->is_paused = false;
        } else {
            $campaign->is_active = true;
            $campaign->is_paused = false;
        }

        $campaign->save();

        return $campaign;
    }

    public function pauseCampaign(TriggerCampaign $campaign): TriggerCampaign
    {
        if (!$campaign->is_active) {
            return $campaign;
        }

        $campaign->is_paused = true;
        $campaign->save();

        return $campaign;
    }

    public function resumeCampaign(TriggerCampaign $campaign): TriggerCampaign
    {
        if (!$campaign->is_active) {
            return $campaign;
        }

        $campaign->is_paused = false;
        $campaign->save();

        return $campaign;
    }

    /**
     * Создаёт dispatch (и купон, если кампания с промо).
     */
    public function createDispatchForUser(TriggerCampaign $campaign, User $user, ?User $admin, bool $isTest = false): TriggerCampaignDispatch
    {
        return DB::transaction(function () use ($campaign, $user, $admin, $isTest) {
            $dispatchKey = $this->resolveDispatchKey($campaign, $user, $isTest);

            $existing = TriggerCampaignDispatch::query()
                ->where('trigger_campaign_id', $campaign->id)
                ->where('dispatch_key', $dispatchKey)
                ->where('is_test', $isTest)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing->load(['promoCode', 'user']);
            }

            $tariffPay = null;
            $promoId = null;

            if ($campaign->usesTariffPayDispatch()) {
                $tariffPay = $this->resolveTariffPayForCampaign($campaign, $user, $isTest);
            }

            if ($campaign->sendsPromo()) {
                $promo = PromoCode::query()->create([
                    'code' => $this->generateUniqueCode($campaign, $user),
                    'title' => sprintf('%s · #%d', $campaign->name, $user->id),
                    'bonus_type' => $campaign->couponBonusType(),
                    'bonus_value' => (int) $campaign->coupon_bonus_value,
                    'usage_mode' => PromoCode::USAGE_ONCE,
                    'redeem_mode' => $campaign->resolvedPromoRedeemMode(),
                    'max_uses' => 1,
                    'uses_count' => 0,
                    'is_active' => true,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays(max(1, (int) $campaign->coupon_expires_days)),
                    'created_by' => $admin !== null ? $admin->id : null,
                    'trigger_campaign_id' => $campaign->id,
                    'assigned_user_id' => $user->id,
                ]);
                $promoId = $promo->id;
            }

            return TriggerCampaignDispatch::query()->create([
                'trigger_campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'dispatch_key' => $dispatchKey,
                'promo_code_id' => $promoId,
                'tariff_pay_id' => $tariffPay ? $tariffPay->id : null,
                'tracking_token' => Str::random(48),
                'status' => TriggerCampaignDispatch::STATUS_PENDING,
                'is_test' => $isTest,
                'queued_at' => now(),
            ])->load(['promoCode', 'user']);
        });
    }

    /**
     * @deprecated use createDispatchForUser()
     */
    public function generateCouponForUser(TriggerCampaign $campaign, User $user, User $admin, bool $isTest = false): TriggerCampaignDispatch
    {
        return $this->createDispatchForUser($campaign, $user, $admin, $isTest);
    }

    /**
     * @return array{tariff_name: string, active_to: string, days_left: int, is_expired?: bool, days_since_expiry?: int}|null
     */
    public function tariffContextForUser(TriggerCampaign $campaign, User $user, bool $isTest = false): ?array
    {
        $pay = $this->resolveTariffPayForCampaign($campaign, $user, $isTest);
        if ($pay === null) {
            return null;
        }

        $tariff = new $pay->class_tariff();
        $activeTo = $pay->active_to instanceof Carbon ? $pay->active_to : Carbon::parse($pay->active_to);

        if ($campaign->isTariffExpiredTrigger()) {
            $daysSince = max(0, $activeTo->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay()));

            return [
                'tariff_name' => $tariff->name(),
                'active_to' => $activeTo->locale(app()->getLocale())->isoFormat('LL'),
                'days_left' => 0,
                'is_expired' => true,
                'days_since_expiry' => $daysSince,
            ];
        }

        if (!$campaign->isTariffExpiringTrigger()) {
            return null;
        }

        return [
            'tariff_name' => $tariff->name(),
            'active_to' => $activeTo->locale(app()->getLocale())->isoFormat('LL'),
            'days_left' => max(0, Carbon::now()->startOfDay()->diffInDays($activeTo->copy()->startOfDay(), false)),
        ];
    }

    private function resolveDispatchKey(TriggerCampaign $campaign, User $user, bool $isTest = false): string
    {
        if ($campaign->usesTariffPayDispatch()) {
            $pay = $this->resolveTariffPayForCampaign($campaign, $user, $isTest);
            if ($pay !== null) {
                return 'pay:' . $pay->id;
            }
        }

        return 'user:' . $user->id;
    }

    private function resolveTariffPayForCampaign(TriggerCampaign $campaign, User $user, bool $isTest): ?TariffPay
    {
        if ($campaign->isTariffExpiringTrigger()) {
            return $this->resolveExpiringTariffPay($user, $campaign, $isTest);
        }

        if ($campaign->isTariffExpiredTrigger()) {
            return $this->resolveExpiredTariffPay($user, $campaign, $isTest);
        }

        return null;
    }

    private function resolveExpiringTariffPay(User $user, TriggerCampaign $campaign, bool $isTest): ?TariffPay
    {
        $days = max(1, (int) $campaign->trigger_days);
        $targetDate = Carbon::now()->addDays($days)->toDateString();

        $pay = $user->pay()
            ->where('status', true)
            ->where('class_tariff', '!=', FreeTariff::class)
            ->whereDate('active_to', $targetDate)
            ->where('active_to', '>', Carbon::now())
            ->orderByDesc('id')
            ->first();

        if ($pay !== null) {
            return $pay;
        }

        if ($isTest) {
            return $user->pay()
                ->where('status', true)
                ->where('class_tariff', '!=', FreeTariff::class)
                ->where('active_to', '>', Carbon::now())
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }

    private function resolveExpiredTariffPay(User $user, TriggerCampaign $campaign, bool $isTest): ?TariffPay
    {
        $days = max(1, (int) $campaign->trigger_days);
        $targetDate = Carbon::now()->subDays($days)->toDateString();
        $nextTierDays = $campaign->nextTariffExpiredTierDays();
        $upperDate = $nextTierDays !== null
            ? Carbon::now()->subDays($nextTierDays)->toDateString()
            : null;

        if ($this->userHasActivePaidTariff($user)) {
            return $isTest ? $this->latestExpiredPaidTariffPay($user) : null;
        }

        $query = $user->pay()
            ->where('class_tariff', '!=', FreeTariff::class)
            ->whereDate('active_to', $targetDate)
            ->where('active_to', '<', Carbon::now());

        if ($upperDate !== null) {
            $query->whereDate('active_to', '>', $upperDate);
        }

        $pay = $query->orderByDesc('id')->first();

        if ($pay !== null) {
            return $pay;
        }

        if ($isTest) {
            return $this->latestExpiredPaidTariffPay($user);
        }

        return null;
    }

    private function userHasActivePaidTariff(User $user): bool
    {
        return $user->pay()
            ->where('status', true)
            ->where('class_tariff', '!=', FreeTariff::class)
            ->where('active_to', '>', Carbon::now())
            ->exists();
    }

    private function latestExpiredPaidTariffPay(User $user): ?TariffPay
    {
        return $user->pay()
            ->where('class_tariff', '!=', FreeTariff::class)
            ->where('active_to', '<', Carbon::now())
            ->orderByDesc('id')
            ->first();
    }

    public function markDispatchSent(TriggerCampaignDispatch $dispatch): void
    {
        $dispatch->update([
            'status' => TriggerCampaignDispatch::STATUS_SENT,
            'sent_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markDispatchOpened(TriggerCampaignDispatch $dispatch): void
    {
        $updates = [
            'open_count' => (int) $dispatch->open_count + 1,
        ];

        if ($dispatch->opened_at === null) {
            $updates['opened_at'] = now();
        }

        $dispatch->update($updates);
    }

    public function markDispatchFailed(TriggerCampaignDispatch $dispatch, string $error): void
    {
        $dispatch->update([
            'status' => TriggerCampaignDispatch::STATUS_FAILED,
            'last_error' => mb_substr($error, 0, 2000),
        ]);
    }

    public function markDispatchRedeemed(TriggerCampaignDispatch $dispatch, int $balanceId): void
    {
        $dispatch->update([
            'status' => TriggerCampaignDispatch::STATUS_REDEEMED,
            'redeemed_at' => now(),
            'balance_id' => $balanceId,
        ]);
    }

    public function findDispatchForPromo(PromoCode $promo): ?TriggerCampaignDispatch
    {
        return TriggerCampaignDispatch::query()
            ->where('promo_code_id', $promo->id)
            ->first();
    }

    /**
     * @return array{
     *     funnel: array{sent: int, opened: int, redeemed: int},
     *     timeline: array{labels: list<string>, sent: list<int>, opened: list<int>, redeemed: list<int>}
     * }
     */
    public function chartForCampaign(TriggerCampaign $campaign, int $days = 30): array
    {
        $stats = $this->statsForCampaign($campaign);
        $funnel = [
            'sent' => $stats['sent'],
            'opened' => $stats['opened'],
            'redeemed' => $stats['redeemed'],
        ];

        $start = Carbon::now()->subDays(max(1, $days - 1))->startOfDay();
        $labels = [];
        $sentSeries = [];
        $openedSeries = [];
        $redeemedSeries = [];

        for ($i = 0; $i < $days; $i++) {
            $day = (clone $start)->addDays($i);
            $labels[] = $day->format('d.m');
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $base = TriggerCampaignDispatch::query()
                ->where('trigger_campaign_id', $campaign->id)
                ->where('is_test', false);

            $sentSeries[] = (int) (clone $base)
                ->whereIn('status', [TriggerCampaignDispatch::STATUS_SENT, TriggerCampaignDispatch::STATUS_REDEEMED])
                ->whereBetween('sent_at', [$dayStart, $dayEnd])
                ->count();

            $openedSeries[] = (int) (clone $base)
                ->whereNotNull('opened_at')
                ->whereBetween('opened_at', [$dayStart, $dayEnd])
                ->count();

            $redeemedSeries[] = (int) (clone $base)
                ->where('status', TriggerCampaignDispatch::STATUS_REDEEMED)
                ->whereBetween('redeemed_at', [$dayStart, $dayEnd])
                ->count();
        }

        return [
            'funnel' => $funnel,
            'timeline' => [
                'labels' => $labels,
                'sent' => $sentSeries,
                'opened' => $openedSeries,
                'redeemed' => $redeemedSeries,
            ],
        ];
    }

    public function dispatchesForCampaignStats(
        TriggerCampaign $campaign,
        ?string $filter = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        $query = TriggerCampaignDispatch::query()
            ->with(['user:id,name,last_name,email', 'promoCode:id,code,bonus_value'])
            ->where('trigger_campaign_id', $campaign->id)
            ->where('is_test', false)
            ->orderByDesc('id');

        if ($filter === 'sent') {
            $query->whereIn('status', [TriggerCampaignDispatch::STATUS_SENT, TriggerCampaignDispatch::STATUS_REDEEMED]);
        } elseif ($filter === 'opened') {
            $query->whereIn('status', [TriggerCampaignDispatch::STATUS_SENT, TriggerCampaignDispatch::STATUS_REDEEMED])
                ->whereNotNull('opened_at');
        } elseif ($filter === 'not_opened') {
            $query->whereIn('status', [TriggerCampaignDispatch::STATUS_SENT, TriggerCampaignDispatch::STATUS_REDEEMED])
                ->whereNull('opened_at');
        } elseif ($filter === 'redeemed') {
            $query->where('status', TriggerCampaignDispatch::STATUS_REDEEMED);
        } elseif ($filter === 'pending') {
            $query->where('status', TriggerCampaignDispatch::STATUS_PENDING);
        } elseif ($filter === 'failed') {
            $query->where('status', TriggerCampaignDispatch::STATUS_FAILED);
        }

        return $query->paginate($perPage)->appends(array_filter([
            'filter' => $filter !== null && $filter !== '' ? $filter : null,
        ]));
    }

    private function generateUniqueCode(TriggerCampaign $campaign, User $user): string
    {
        $prefix = strtoupper(Str::slug(Str::limit($campaign->slug, 8, ''), '_'));
        if ($prefix === '') {
            $prefix = 'GIFT';
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = sprintf('%s-U%d-%s', $prefix, $user->id, strtoupper(Str::random(4)));
            if (!PromoCode::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        return sprintf('%s-U%d-%s', $prefix, $user->id, strtoupper(Str::random(8)));
    }
}
