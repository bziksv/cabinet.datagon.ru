<?php

namespace App\Services\Finance;

use App\Balance;
use App\PromoCode;
use App\PromoCodeRedemption;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PromoCodeService
{
    /**
     * @return array{valid: bool, message: string, promo_code: ?PromoCode, bonus_sum: int, paid_sum: int, total_sum: int}
     */
    public function previewForUser(User $user, string $rawCode, int $paidSum): array
    {
        $rawCode = trim($rawCode);
        $rateLimit = app(PromoCodeRateLimitService::class);

        if ($rawCode !== '') {
            $lockMessage = $rateLimit->lockMessage($user);
            if ($lockMessage !== null) {
                return $this->fail($lockMessage);
            }
        }

        $paidSum = max(0, $paidSum);

        if ($paidSum < 1) {
            return $this->fail(__('Promo preview need sum'));
        }

        if ($rawCode === '') {
            return $this->fail(__('Balance promo need code'));
        }

        $promo = $this->findByCode($rawCode);
        if ($promo === null) {
            $rateLimit->recordFailedAttempt($user, $rawCode);

            return $this->fail(__('Promo code not found'));
        }

        $error = $this->validatePromo($promo, $user);
        if ($error !== null) {
            $rateLimit->recordFailedAttempt($user, $rawCode);

            return $this->fail($error);
        }

        if ($promo->isStandaloneCredit()) {
            $rateLimit->recordFailedAttempt($user, $rawCode);

            return $this->fail(__('Promo code standalone only'));
        }

        $rateLimit->clearFailures($user);

        $bonusSum = $this->calculateBonus($promo, $paidSum);

        return [
            'valid' => true,
            'message' => __('Promo preview success', [
                'code' => $promo->code,
                'bonus' => FinanceAdminService::formatMoney($bonusSum),
                'total' => FinanceAdminService::formatMoney($paidSum + $bonusSum),
            ]),
            'promo_code' => $promo,
            'bonus_sum' => $bonusSum,
            'paid_sum' => $paidSum,
            'total_sum' => $paidSum + $bonusSum,
        ];
    }

    /**
     * @return array{valid: bool, message: string, promo_code: ?PromoCode, bonus_sum: int}
     */
    public function resolveForPayment(User $user, string $rawCode, int $paidSum): array
    {
        $preview = $this->previewForUser($user, $rawCode, $paidSum);
        if (!$preview['valid']) {
            return $preview;
        }

        return [
            'valid' => true,
            'message' => $preview['message'],
            'promo_code' => $preview['promo_code'],
            'bonus_sum' => $preview['bonus_sum'],
        ];
    }

    /**
     * @return array{valid: bool, message: string, promo_code: ?PromoCode, bonus_sum: int}
     */
    public function previewStandalone(User $user, string $rawCode): array
    {
        $rawCode = trim($rawCode);
        $rateLimit = app(PromoCodeRateLimitService::class);

        if ($rawCode === '') {
            return $this->failStandalone(__('Balance promo need code'));
        }

        $lockMessage = $rateLimit->lockMessage($user);
        if ($lockMessage !== null) {
            return $this->failStandalone($lockMessage);
        }

        $promo = $this->findByCode($rawCode);
        if ($promo === null) {
            $rateLimit->recordFailedAttempt($user, $rawCode);

            return $this->failStandalone(__('Promo code not found'));
        }

        $error = $this->validatePromo($promo, $user, true);
        if ($error !== null) {
            $rateLimit->recordFailedAttempt($user, $rawCode);

            return $this->failStandalone($error);
        }

        $rateLimit->clearFailures($user);
        $bonusSum = $this->calculateStandaloneBonus($promo);

        return [
            'valid' => true,
            'message' => __('Promo standalone preview success', [
                'code' => $promo->code,
                'bonus' => FinanceAdminService::formatMoney($bonusSum),
            ]),
            'promo_code' => $promo,
            'bonus_sum' => $bonusSum,
        ];
    }

    /**
     * @return array{valid: bool, message: string, promo_code: ?PromoCode, bonus_sum: int, balance: ?Balance}
     */
    public function redeemStandalone(User $user, string $rawCode): array
    {
        $preview = $this->previewStandalone($user, $rawCode);
        if (!$preview['valid']) {
            return array_merge($preview, ['balance' => null]);
        }

        /** @var PromoCode $promo */
        $promo = $preview['promo_code'];
        $bonusSum = (int) $preview['bonus_sum'];

        $balance = DB::transaction(function () use ($user, $promo, $bonusSum) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            $balance = $lockedUser->balances()->create([
                'sum' => $bonusSum,
                'paid_sum' => 0,
                'bonus_sum' => $bonusSum,
                'promo_code_id' => $promo->id,
                'source' => __('Promo standalone credit source', ['code' => $promo->code]),
                'status' => 1,
                'credited_at' => now(),
            ]);

            $lockedUser->increment('balance', $bonusSum);
            $this->recordRedemption($balance->fresh());
            $this->markTriggerDispatchRedeemed($promo, $balance->id);

            return $balance->fresh(['promoCode']);
        });

        return [
            'valid' => true,
            'message' => __('Promo standalone redeem success', [
                'bonus' => FinanceAdminService::formatMoney($bonusSum),
                'balance' => FinanceAdminService::formatMoney((int) $user->fresh()->balance),
            ]),
            'promo_code' => $promo,
            'bonus_sum' => $bonusSum,
            'balance' => $balance,
        ];
    }

    public function calculateStandaloneBonus(PromoCode $promo): int
    {
        if (!$promo->isStandaloneCredit() || !$promo->isFixed()) {
            return 0;
        }

        return max(0, (int) $promo->bonus_value);
    }

    public function calculateBonus(PromoCode $promo, int $paidSum): int
    {
        if ($paidSum < 1) {
            return 0;
        }

        if ($promo->isFixed()) {
            return max(0, (int) $promo->bonus_value);
        }

        $percent = min(100, max(0, (int) $promo->bonus_value));

        return (int) floor($paidSum * $percent / 100);
    }

    public function recordRedemption(Balance $balance): ?PromoCodeRedemption
    {
        if (!$balance->promo_code_id || (int) $balance->bonus_sum <= 0) {
            return null;
        }

        if (PromoCodeRedemption::query()->where('balance_id', $balance->id)->exists()) {
            return PromoCodeRedemption::query()->where('balance_id', $balance->id)->first();
        }

        return DB::transaction(function () use ($balance) {
            /** @var PromoCode $promo */
            $promo = PromoCode::query()->lockForUpdate()->findOrFail((int) $balance->promo_code_id);

            $paidSum = (int) ($balance->paid_sum ?? 0);
            $bonusSum = (int) $balance->bonus_sum;

            $redemption = PromoCodeRedemption::query()->create([
                'promo_code_id' => $promo->id,
                'user_id' => $balance->user_id,
                'balance_id' => $balance->id,
                'paid_sum' => $paidSum,
                'bonus_sum' => $bonusSum,
            ]);

            $promo->increment('uses_count');

            return $redemption;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $admin): PromoCode
    {
        return PromoCode::query()->create($this->normalizePayload($data, $admin));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(PromoCode $promo, array $data): PromoCode
    {
        $payload = $this->normalizePayload($data, null, false);
        unset($payload['created_by']);
        $promo->update($payload);

        return $promo->fresh();
    }

    public function findByCode(string $rawCode): ?PromoCode
    {
        $code = $this->normalizeCode($rawCode);
        if ($code === '') {
            return null;
        }

        return PromoCode::query()->where('code', $code)->first();
    }

    /**
     * @param array{promo_q?: string, promo_status?: string, promo_source?: string} $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 10), 100);

        $query = PromoCode::query()
            ->withCount('redemptions')
            ->orderByDesc('id');

        $q = trim((string) ($filters['promo_q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $query->where(function ($outer) use ($like, $q) {
                $outer->where('code', 'like', $like)
                    ->orWhere('title', 'like', $like);

                if (ctype_digit($q)) {
                    $id = (int) $q;
                    $outer->orWhere('id', $id)
                        ->orWhere('assigned_user_id', $id);
                }
            });
        }

        $status = (string) ($filters['promo_status'] ?? 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $source = (string) ($filters['promo_source'] ?? 'all');
        if ($source === 'trigger') {
            $query->whereNotNull('trigger_campaign_id');
        } elseif ($source === 'manual') {
            $query->whereNull('trigger_campaign_id');
        }

        return $query->paginate($perPage, ['*'], 'promo_page');
    }

    /**
     * @deprecated use paginateForAdmin()
     * @return list<PromoCode>
     */
    public function listForAdmin(): array
    {
        return PromoCode::query()
            ->withCount('redemptions')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    private function validatePromo(PromoCode $promo, User $user, bool $standalone = false): ?string
    {
        if (!$promo->is_active) {
            return __('Promo code inactive');
        }

        if (!$promo->isAssignedToUser((int) $user->id)) {
            return __('Promo code not for user');
        }

        if ($standalone && !$promo->isStandaloneCredit()) {
            return __('Promo code topup only');
        }

        if (!$standalone && $promo->isStandaloneCredit()) {
            return __('Promo code standalone only');
        }

        if ($promo->isStandaloneCredit() && !$promo->isFixed()) {
            return __('Promo code invalid config');
        }

        $now = Carbon::now();

        if ($promo->starts_at !== null && $promo->starts_at->gt($now)) {
            return __('Promo code not started');
        }

        if ($promo->expires_at !== null && $promo->expires_at->lt($now)) {
            return __('Promo code expired');
        }

        if ($promo->max_uses !== null && (int) $promo->uses_count >= (int) $promo->max_uses) {
            return __('Promo code limit reached');
        }

        if ($promo->isOncePerUser()) {
            $alreadyUsed = PromoCodeRedemption::query()
                ->where('promo_code_id', $promo->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyUsed) {
                return __('Promo code already used');
            }
        }

        if ($promo->isFixed() && (int) $promo->bonus_value < 1) {
            return __('Promo code invalid config');
        }

        if ($promo->isPercent() && ((int) $promo->bonus_value < 1 || (int) $promo->bonus_value > 100)) {
            return __('Promo code invalid config');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?User $admin, bool $creating = true): array
    {
        $bonusType = (string) ($data['bonus_type'] ?? PromoCode::BONUS_FIXED);
        if (!in_array($bonusType, [PromoCode::BONUS_FIXED, PromoCode::BONUS_PERCENT], true)) {
            $bonusType = PromoCode::BONUS_FIXED;
        }

        $usageMode = (string) ($data['usage_mode'] ?? PromoCode::USAGE_ONCE);
        if (!in_array($usageMode, [PromoCode::USAGE_ONCE, PromoCode::USAGE_MULTI], true)) {
            $usageMode = PromoCode::USAGE_ONCE;
        }

        $redeemMode = (string) ($data['redeem_mode'] ?? PromoCode::REDEEM_TOPUP_BONUS);
        if (!in_array($redeemMode, [PromoCode::REDEEM_TOPUP_BONUS, PromoCode::REDEEM_STANDALONE], true)) {
            $redeemMode = PromoCode::REDEEM_TOPUP_BONUS;
        }

        $payload = [
            'code' => $this->normalizeCode((string) ($data['code'] ?? '')),
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'bonus_type' => $bonusType,
            'bonus_value' => max(1, (int) ($data['bonus_value'] ?? 0)),
            'usage_mode' => $usageMode,
            'redeem_mode' => $redeemMode,
            'max_uses' => $this->nullableInt($data['max_uses'] ?? null),
            'is_active' => !empty($data['is_active']),
            'starts_at' => $this->nullableDate($data['starts_at'] ?? null),
            'expires_at' => $this->nullableDate($data['expires_at'] ?? null),
        ];

        if ($bonusType === PromoCode::BONUS_PERCENT) {
            $payload['bonus_value'] = min(100, max(1, $payload['bonus_value']));
            $payload['redeem_mode'] = PromoCode::REDEEM_TOPUP_BONUS;
        }

        if ($payload['redeem_mode'] === PromoCode::REDEEM_STANDALONE) {
            $payload['bonus_type'] = PromoCode::BONUS_FIXED;
        }

        if ($creating && $admin !== null) {
            $payload['created_by'] = $admin->id;
        }

        return $payload;
    }

    private function normalizeCode(string $code): string
    {
        $code = mb_strtoupper(trim($code));

        return preg_replace('/\s+/u', '', $code) ?? '';
    }

    /**
     * @param mixed $value
     */
    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @param mixed $value
     */
    private function nullableDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * @return array{valid: bool, message: string, promo_code: null, bonus_sum: int, paid_sum: int, total_sum: int}
     */
    private function fail(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'promo_code' => null,
            'bonus_sum' => 0,
            'paid_sum' => 0,
            'total_sum' => 0,
        ];
    }

    /**
     * @return array{valid: bool, message: string, promo_code: null, bonus_sum: int}
     */
    private function failStandalone(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'promo_code' => null,
            'bonus_sum' => 0,
        ];
    }

    private function markTriggerDispatchRedeemed(PromoCode $promo, int $balanceId): void
    {
        $dispatch = app(TriggerCampaignService::class)->findDispatchForPromo($promo);
        if ($dispatch === null || $dispatch->isRedeemed()) {
            return;
        }

        app(TriggerCampaignService::class)->markDispatchRedeemed($dispatch, $balanceId);
    }
}
