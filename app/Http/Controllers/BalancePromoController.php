<?php

namespace App\Http\Controllers;

use App\Services\Finance\PromoCodeRateLimitService;
use App\Services\Finance\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BalancePromoController extends Controller
{
    public function preview(Request $request, PromoCodeService $promos, PromoCodeRateLimitService $rateLimit): JsonResponse
    {
        $user = Auth::user();
        $lockStatus = $rateLimit->statusForUser($user);

        $data = $request->validate([
            'sum' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'promo_code' => ['required', 'string', 'max:64'],
        ]);

        if ($lockStatus['locked']) {
            return response()->json([
                'valid' => false,
                'mode' => null,
                'locked' => true,
                'message' => __('Promo lock active', ['until' => $lockStatus['locked_until_human']]),
                'paid_sum' => 0,
                'bonus_sum' => 0,
                'total_sum' => 0,
                'promo_code' => null,
                'promo_lock' => $lockStatus,
            ]);
        }

        $code = (string) $data['promo_code'];
        $paidSum = max(0, (int) ($data['sum'] ?? 0));

        $standalone = $promos->previewStandalone($user, $code);
        if ($standalone['valid']) {
            $lockStatus = $rateLimit->statusForUser($user);

            return response()->json([
                'valid' => true,
                'mode' => 'standalone',
                'locked' => $lockStatus['locked'],
                'message' => $standalone['message'],
                'paid_sum' => 0,
                'bonus_sum' => $standalone['bonus_sum'],
                'total_sum' => $standalone['bonus_sum'],
                'promo_code' => $standalone['promo_code']->code,
                'promo_lock' => $lockStatus,
            ]);
        }

        $promo = $promos->findByCode($code);
        if ($promo !== null && $promo->isStandaloneCredit()) {
            $lockStatus = $rateLimit->statusForUser($user);

            return response()->json([
                'valid' => false,
                'mode' => 'standalone',
                'locked' => $lockStatus['locked'],
                'message' => $standalone['message'],
                'paid_sum' => 0,
                'bonus_sum' => 0,
                'total_sum' => 0,
                'promo_code' => $promo->code,
                'promo_lock' => $lockStatus,
            ]);
        }

        if ($paidSum < 1) {
            $lockStatus = $rateLimit->statusForUser($user);

            return response()->json([
                'valid' => false,
                'mode' => 'topup',
                'locked' => $lockStatus['locked'],
                'message' => __('Promo preview need sum'),
                'paid_sum' => 0,
                'bonus_sum' => 0,
                'total_sum' => 0,
                'promo_code' => null,
                'promo_lock' => $lockStatus,
            ]);
        }

        $result = $promos->previewForUser($user, $code, $paidSum);
        $lockStatus = $rateLimit->statusForUser($user);

        return response()->json([
            'valid' => $result['valid'],
            'mode' => 'topup',
            'locked' => $lockStatus['locked'],
            'message' => $result['message'],
            'paid_sum' => $result['paid_sum'],
            'bonus_sum' => $result['bonus_sum'],
            'total_sum' => $result['total_sum'],
            'promo_code' => $result['promo_code'] ? $result['promo_code']->code : null,
            'promo_lock' => $lockStatus,
        ]);
    }

    public function redeem(Request $request, PromoCodeService $promos): RedirectResponse
    {
        $data = $request->validate([
            'standalone_promo_code' => ['nullable', 'string', 'max:64'],
            'promo_code' => ['nullable', 'string', 'max:64'],
        ]);

        $code = trim((string) ($data['standalone_promo_code'] ?? $data['promo_code'] ?? ''));
        if ($code === '') {
            return redirect()
                ->route('balance.index')
                ->withErrors(['standalone_promo_code' => __('Balance promo need code')]);
        }

        $result = $promos->redeemStandalone(Auth::user(), $code);

        if (!$result['valid']) {
            return redirect()
                ->route('balance.index')
                ->withInput()
                ->withErrors(['standalone_promo_code' => $result['message']]);
        }

        flash()->overlay($result['message'], ' ')->success();

        return redirect()->route('balance.index');
    }
}
