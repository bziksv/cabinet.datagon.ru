<?php

namespace App\Support;

use App\User;
use App\ViewComposers\LimitsComposer;

/**
 * Остаток тарифного лимита по коду модуля (один запрос used + настройки тарифа).
 */
class ModuleTariffLimit
{
    public const UNLIMITED_CAP = 1000000;

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     used: int,
     *     limit: int|null,
     *     left: int|null,
     *     unlimited: bool,
     *     applies: bool,
     *     exhausted: bool
     * }
     */
    public static function forUser(User $user, string $code): array
    {
        $code = trim($code);
        $usedInfo = LimitsComposer::getUsedLimit($code, $user);
        $used = is_int($usedInfo['count']) ? $usedInfo['count'] : 0;

        $name = '';
        $limit = null;
        $applies = false;

        $tariff = $user->tariff();
        if ($tariff !== null) {
            $settings = $tariff->getAsArray()['settings'] ?? [];
            if (isset($settings[$code])) {
                $name = (string) ($settings[$code]['name'] ?? '');
                $rawLimit = (int) ($settings[$code]['value'] ?? 0);
                if ($rawLimit >= self::UNLIMITED_CAP) {
                    $limit = null;
                } elseif ($code === 'CompetitorAnalysisPhrases') {
                    if ($rawLimit > 0) {
                        $limit = $rawLimit;
                        $applies = true;
                    }
                } elseif ($rawLimit > 0) {
                    $limit = $rawLimit;
                    $applies = true;
                }
            }
        }

        if ($name === '') {
            $registry = TariffLimitRegistry::byCode();
            $name = isset($registry[$code]) ? (string) $registry[$code]['module'] : $code;
        }

        $unlimited = !$applies || $limit === null;
        $left = $unlimited ? null : max(0, $limit - $used);
        $exhausted = $applies && $left !== null && $left <= 0;

        return [
            'code' => $code,
            'name' => $name,
            'used' => $used,
            'limit' => $limit,
            'left' => $left,
            'unlimited' => $unlimited,
            'applies' => $applies,
            'exhausted' => $exhausted,
        ];
    }
}
