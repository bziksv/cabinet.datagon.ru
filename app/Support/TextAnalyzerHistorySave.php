<?php

namespace App\Support;

use App\TextAnalyzer;
use App\TextUniquenessHistory;
use App\User;

/**
 * Сохранение результата анализа текста в историю (лимит TextUniquenessHistory).
 */
class TextAnalyzerHistorySave
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     * @return array{history_id: int|null, warning: string|null}
     */
    public static function maybeSave(array $request, array $response, string $plain, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if (! $user || ! TextAnalyzer::shouldSaveUniquenessHistory($request)) {
            return ['history_id' => null, 'warning' => null];
        }
        if (! TextUniquenessLimits::canSaveHistory($user)) {
            return ['history_id' => null, 'warning' => null];
        }
        if (! TextUniquenessLimits::canSaveAnother($user)) {
            return [
                'history_id' => null,
                'warning' => TextUniquenessLimits::historyLimitMessage($user)
                    ?: __('Text uniqueness history limit exhausted'),
            ];
        }

        $uniq = $response['uniqueness'] ?? null;
        $esenin = $response['esenin'] ?? null;
        $general = $response['general'] ?? [];

        $uniquenessPct = null;
        $noSignificant = false;
        if (is_array($uniq) && empty($uniq['error'])) {
            $uniquenessPct = $uniq['uniqueness_pct'] ?? null;
            $noSignificant = ! empty($uniq['no_significant_matches']);
        }

        $eseninRisk = null;
        $eseninLevel = null;
        if (is_array($esenin) && empty($esenin['error'])) {
            $eseninRisk = isset($esenin['risk']) ? (int) $esenin['risk'] : null;
            $eseninLevel = (string) ($esenin['level'] ?? '');
        }

        $chars = (int) ($general['textLength'] ?? ($uniq['chars'] ?? mb_strlen($plain)));
        $words = (int) ($general['countWordsAll'] ?? ($general['countWords'] ?? 0));

        $title = TextAnalyzerUniqueness::historyTitle($request, $plain);
        $results = [];
        if (is_array($uniq) && empty($uniq['error'])) {
            $results['uniqueness'] = $uniq;
        }
        if (is_array($esenin) && empty($esenin['error'])) {
            // компактный снимок без огромных highlights
            $results['esenin'] = [
                'risk' => $eseninRisk,
                'level' => $eseninLevel,
                'metrics' => $esenin['metrics'] ?? [],
                'details' => $esenin['details'] ?? [],
            ];
        }

        $history = TextUniquenessHistory::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'mode' => 'internet',
            'params' => [
                'source' => 'text-analyzer',
                'type' => $request['type'] ?? 'text',
                'url' => $request['url'] ?? null,
                'chars' => $chars,
                'words' => $words,
                'uniqueness_pct' => $uniquenessPct,
                'no_significant_matches' => $noSignificant,
                'esenin_risk' => $eseninRisk,
                'esenin_level' => $eseninLevel,
                'had_uniqueness' => $uniquenessPct !== null,
                'had_esenin' => $eseninRisk !== null,
                'force_compare_urls' => TextAnalyzer::uniquenessForceCompareUrls($request),
                'exclude_hosts' => TextAnalyzer::uniquenessExcludeHosts($request),
                'general' => [
                    'countWordsAll' => $general['countWordsAll'] ?? null,
                    'countStopWords' => $general['countStopWords'] ?? null,
                    'countWordsWithoutStopWords' => $general['countWordsWithoutStopWords'] ?? null,
                    'textLength' => $general['textLength'] ?? null,
                    'lengthWithOutSpaces' => $general['lengthWithOutSpaces'] ?? null,
                ],
            ],
            'results' => $results,
            'uniqueness_pct' => $uniquenessPct ?? 0,
            'cost' => (int) (($uniq['cost'] ?? 0) + ($esenin['cost'] ?? 0)),
        ]);

        return ['history_id' => $history->id, 'warning' => null];
    }
}
