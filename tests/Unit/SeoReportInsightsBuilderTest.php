<?php

namespace Tests\Unit;

use App\Services\SeoReports\SeoReportInsightsBuilder;
use Tests\TestCase;

class SeoReportInsightsBuilderTest extends TestCase
{
    public function test_landing_traffic_without_conversion_flags_mismatch(): void
    {
        $builder = new SeoReportInsightsBuilder();
        $lines = $builder->landingTrafficWithoutConversion([
            'traffic' => [
                'kpis' => [
                    'visits' => ['delta_pct' => 18.0],
                ],
                'landings' => [
                    ['name' => '/services', 'visits' => 1000, 'visits_delta_pct' => 40],
                ],
            ],
            'conversions' => [
                'goals' => [
                    ['reaches' => ['delta_pct' => -5.0]],
                ],
            ],
        ]);

        $this->assertNotEmpty($lines);
        $this->assertStringContainsString('Трафик вырос', $lines[0]);
    }

    public function test_recommendations_include_traffic_conversion_insight(): void
    {
        $builder = new SeoReportInsightsBuilder();
        $recs = $builder->recommendations([
            'traffic' => [
                'kpis' => [
                    'visits' => ['delta_pct' => 20.0],
                    'bounce_rate' => ['delta_pct' => -1.0],
                ],
                'landings' => [],
            ],
            'conversions' => [
                'goals' => [
                    ['reaches' => ['delta_pct' => -3.0]],
                ],
            ],
            'positions' => [
                'dynamics' => ['improved' => 1, 'worsened' => 0],
                'risk' => [],
                'quick_wins' => [],
            ],
        ]);

        $texts = array_column($recs, 'text');
        $joined = implode(' ', $texts);
        $this->assertStringContainsString('конверсии', mb_strtolower($joined));
    }
}
