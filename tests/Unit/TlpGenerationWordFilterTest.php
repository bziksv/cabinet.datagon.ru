<?php

namespace Tests\Unit;

use App\Services\Integration\RelevanceAnalysisService;
use PHPUnit\Framework\TestCase;

class TlpGenerationWordFilterTest extends TestCase
{
    public function testRejectsShortWordsAndStopWords(): void
    {
        $this->assertFalse(RelevanceAnalysisService::isEligibleTlpGenerationWord('р'));
        $this->assertFalse(RelevanceAnalysisService::isEligibleTlpGenerationWord('с'));
        $this->assertFalse(RelevanceAnalysisService::isEligibleTlpGenerationWord('ре'));
        $this->assertFalse(RelevanceAnalysisService::isEligibleTlpGenerationWord('для'));
        $this->assertFalse(RelevanceAnalysisService::isEligibleTlpGenerationWord('и'));
        $this->assertFalse(RelevanceAnalysisService::isEligibleTlpGenerationWord(''));
        $this->assertFalse(RelevanceAnalysisService::isEligibleTlpGenerationWord('  '));
    }

    public function testAcceptsMeaningfulWords(): void
    {
        $this->assertTrue(RelevanceAnalysisService::isEligibleTlpGenerationWord('терапии'));
        $this->assertTrue(RelevanceAnalysisService::isEligibleTlpGenerationWord('коляски'));
        $this->assertTrue(RelevanceAnalysisService::isEligibleTlpGenerationWord('микроскоп'));
        $this->assertTrue(RelevanceAnalysisService::isEligibleTlpGenerationWord('РФР'));
    }
}
