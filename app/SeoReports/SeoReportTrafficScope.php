<?php

namespace App\SeoReports;

/**
 * Какие каналы Метрики входят в KPI «Трафик» (визиты, просмотры и т.д.).
 * Прямые/внутренние часто искажают картину SEO — по умолчанию рекомендуем поиск.
 */
final class SeoReportTrafficScope
{
    public const MODE_ALL = 'all';
    public const MODE_SEARCH = 'search_only';
    public const MODE_CUSTOM = 'custom';

    /**
     * @return list<array{
     *   id:string,
     *   label:string,
     *   recommended:bool,
     *   distorts:bool,
     *   hint:string
     * }>
     */
    public static function channels(): array
    {
        return [
            [
                'id' => 'organic',
                'label' => 'Search engine traffic',
                'recommended' => true,
                'distorts' => false,
                'hint' => 'SEO traffic scope hint organic',
            ],
            [
                'id' => 'ad',
                'label' => 'Ad traffic',
                'recommended' => false,
                'distorts' => false,
                'hint' => 'SEO traffic scope hint ad',
            ],
            [
                'id' => 'direct',
                'label' => 'Direct traffic',
                'recommended' => false,
                'distorts' => true,
                'hint' => 'SEO traffic scope hint direct',
            ],
            [
                'id' => 'referral',
                'label' => 'Link traffic',
                'recommended' => false,
                'distorts' => false,
                'hint' => 'SEO traffic scope hint referral',
            ],
            [
                'id' => 'social',
                'label' => 'Social network traffic',
                'recommended' => false,
                'distorts' => false,
                'hint' => 'SEO traffic scope hint social',
            ],
            [
                'id' => 'recommend',
                'label' => 'Recommendation system traffic',
                'recommended' => false,
                'distorts' => false,
                'hint' => 'SEO traffic scope hint recommend',
            ],
            [
                'id' => 'messenger',
                'label' => 'Messenger traffic',
                'recommended' => false,
                'distorts' => false,
                'hint' => 'SEO traffic scope hint messenger',
            ],
            [
                'id' => 'email',
                'label' => 'Mailing traffic',
                'recommended' => false,
                'distorts' => false,
                'hint' => 'SEO traffic scope hint email',
            ],
            [
                'id' => 'internal',
                'label' => 'Internal traffic',
                'recommended' => false,
                'distorts' => true,
                'hint' => 'SEO traffic scope hint internal',
            ],
        ];
    }

    /** @return list<string> */
    public static function allIds(): array
    {
        return array_column(self::channels(), 'id');
    }

    /** @return list<string> */
    public static function recommendedIds(): array
    {
        return ['organic'];
    }

    /**
     * @param array<string,mixed>|null $settings
     * @return array{mode:string,channels:list<string>}
     */
    public static function normalize(?array $settings): array
    {
        $settings = is_array($settings) ? $settings : [];
        $allowed = self::allIds();
        $rawChannels = $settings['traffic_channels'] ?? null;

        if (is_array($rawChannels) && $rawChannels !== []) {
            $channels = [];
            foreach ($rawChannels as $id) {
                $id = strtolower(trim((string) $id));
                if ($id !== '' && in_array($id, $allowed, true) && !in_array($id, $channels, true)) {
                    $channels[] = $id;
                }
            }
            if ($channels === []) {
                return [
                    'mode' => self::MODE_ALL,
                    'channels' => $allowed,
                ];
            }
            sort($channels);
            $allSorted = $allowed;
            sort($allSorted);
            if ($channels === $allSorted) {
                return ['mode' => self::MODE_ALL, 'channels' => $allowed];
            }
            if ($channels === self::recommendedIds()) {
                return ['mode' => self::MODE_SEARCH, 'channels' => self::recommendedIds()];
            }

            return ['mode' => self::MODE_CUSTOM, 'channels' => $channels];
        }

        // Legacy: traffic_mode all | search_only
        if (($settings['traffic_mode'] ?? self::MODE_ALL) === self::MODE_SEARCH) {
            return ['mode' => self::MODE_SEARCH, 'channels' => self::recommendedIds()];
        }

        return ['mode' => self::MODE_ALL, 'channels' => $allowed];
    }

    /**
     * @param mixed $input checkbox/array from request
     * @return array{mode:string,channels:list<string>}
     */
    public static function normalizeInput($mode, $channelsInput): array
    {
        $mode = (string) $mode;
        if ($mode === self::MODE_SEARCH) {
            return ['mode' => self::MODE_SEARCH, 'channels' => self::recommendedIds()];
        }
        if ($mode === self::MODE_ALL) {
            return ['mode' => self::MODE_ALL, 'channels' => self::allIds()];
        }
        // custom / empty → по чекбоксам (или поиск, если пусто)
        $norm = self::normalize(['traffic_channels' => $channelsInput]);
        if ($norm['channels'] === []) {
            return ['mode' => self::MODE_SEARCH, 'channels' => self::recommendedIds()];
        }

        return $norm;
    }

    /**
     * @param array{mode?:string,channels?:list<string>}|array<string,mixed> $scope
     */
    public static function metrikaFilter(array $scope): ?string
    {
        $norm = isset($scope['channels'])
            ? self::normalize(['traffic_channels' => $scope['channels'], 'traffic_mode' => $scope['mode'] ?? null])
            : self::normalize($scope);

        if ($norm['mode'] === self::MODE_ALL) {
            return null;
        }

        $ids = $norm['channels'];
        if ($ids === [] || $ids === self::allIds()) {
            return null;
        }
        if (count($ids) === 1) {
            return "ym:s:lastTrafficSource=='" . $ids[0] . "'";
        }

        $quoted = array_map(static function (string $id) {
            return "'" . $id . "'";
        }, $ids);

        return 'ym:s:lastTrafficSource=.(' . implode(',', $quoted) . ')';
    }

    /**
     * @param array{mode:string,channels:list<string>} $scope
     */
    public static function label(array $scope): string
    {
        if ($scope['mode'] === self::MODE_ALL) {
            return __('Traffic mode all sources');
        }
        if ($scope['mode'] === self::MODE_SEARCH) {
            return __('Traffic mode search only');
        }
        $labels = [];
        foreach (self::channels() as $ch) {
            if (in_array($ch['id'], $scope['channels'], true)) {
                $labels[] = __($ch['label']);
            }
        }

        return $labels !== [] ? implode(', ', $labels) : __('Traffic mode custom');
    }

    public static function hasDistorting(array $scope): bool
    {
        $norm = isset($scope['channels']) ? $scope : self::normalize($scope);
        $channels = $norm['channels'] ?? self::allIds();
        foreach (self::channels() as $ch) {
            if (!empty($ch['distorts']) && in_array($ch['id'], $channels, true)) {
                return true;
            }
        }

        return false;
    }
}
