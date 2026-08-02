<?php

namespace Tests\Unit;

use App\SeoReports\SeoReportBrandColor;
use Tests\TestCase;

class SeoReportBrandColorTest extends TestCase
{
    public function test_light_color_is_darkened_for_contrast(): void
    {
        $normalized = SeoReportBrandColor::normalize('#ffffff');
        $this->assertNotSame('#ffffff', $normalized);
        $this->assertRegExp('/^#[0-9a-f]{6}$/', $normalized);
    }

    public function test_invalid_falls_back(): void
    {
        $this->assertSame('#0f172a', SeoReportBrandColor::normalize('not-a-color'));
    }
}
