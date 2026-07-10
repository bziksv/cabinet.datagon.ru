<?php

namespace App\Jobs\Esenin;

use App\Support\Esenin\EseninStyleLearning;
use App\Support\Esenin\Providers\TurgenevReportParser;
use App\Support\EseninTextCheckSettingsRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchTurgenevReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, string> */
    private $tokens;

    /** @var int */
    public $timeout = 120;

    /**
     * @param array<int, string> $tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = array_values(array_unique(array_filter(array_map(static function ($token) {
            return TurgenevReportParser::normalizeToken((string) $token);
        }, $tokens))));
    }

    public function handle(): void
    {
        if ($this->tokens === []) {
            return;
        }

        if (! EseninTextCheckSettingsRegistry::learningEnabled()) {
            return;
        }

        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        if (empty($cfg['report_fetch_enabled'])) {
            return;
        }

        $candidates = [];
        foreach ($this->tokens as $token) {
            try {
                $parsed = TurgenevReportParser::parseToken($token);
                if ($parsed !== []) {
                    $candidates = array_merge($candidates, $parsed);
                }
            } catch (\Throwable $e) {
                Log::warning('esenin.turgenev_report.parse_failed', [
                    'token' => $token,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($candidates === []) {
            return;
        }

        $result = EseninStyleLearning::recordFromReport($candidates);
        Log::info('esenin.turgenev_report.learned', [
            'tokens' => count($this->tokens),
            'recorded' => (int) ($result['recorded'] ?? 0),
        ]);
    }
}
