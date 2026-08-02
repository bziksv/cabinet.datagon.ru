<?php

namespace Tests\Unit;

use App\Services\SeoReports\SeoReportExternalAdsCollector;
use Tests\TestCase;

class SeoReportExternalAdsCollectorTest extends TestCase
{
    public function test_parse_ads_csv_campaigns_and_kpis(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'srads');
        file_put_contents($path, implode("\n", [
            'campaign,impressions,clicks,spend,age,gender',
            'Brand,1000,50,2500,25-34,male',
            'Brand,500,20,800,35-44,female',
            'Generic,2000,40,4000,25-34,male',
        ]));

        $parsed = (new SeoReportExternalAdsCollector())->parseCsv($path, 'vk_ads');
        @unlink($path);

        $this->assertNotNull($parsed);
        $this->assertGreaterThan(0, $parsed['kpis']['impressions']);
        $this->assertCount(2, $parsed['campaigns']);
        $this->assertNotEmpty($parsed['demography']);
    }

    public function test_ai_narrative_is_russian_paragraphs(): void
    {
        $builder = new \App\Services\SeoReports\SeoReportInsightsBuilder();
        $text = $builder->aiNarrative([
            'cover' => ['domain' => 'example.com', 'period_label' => 'июль 2026'],
            'scorecard' => [
                ['label' => 'Трафик', 'tone' => 'green'],
            ],
            'traffic' => [
                'kpis' => [
                    'visits' => ['value' => 1000, 'delta_pct' => 12.5],
                    'users' => ['value' => 800, 'delta_pct' => 10],
                ],
                'channels' => [['name' => 'organic', 'visits' => 600]],
            ],
            'positions' => [
                'dynamics' => ['improved' => 5, 'unchanged' => 2, 'worsened' => 1, 'pairs' => [1]],
                'summary' => ['top10' => 10],
                'risk' => [],
                'quick_wins' => [],
            ],
        ]);

        $this->assertStringContainsString('example.com', $text);
        $this->assertStringContainsString('резюме', mb_strtolower($text));
    }
}
