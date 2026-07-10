<?php

namespace App\Support\Esenin;

use App\Support\EseninTextCheckSettingsRegistry;

final class EseninStyleLearning
{
    /**
     * @param array<string, mixed> $localResult
     * @param array<string, mixed> $turgenevData
     */
    public static function recordComparison(array $localResult, array $turgenevData): array
    {
        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        if (empty($cfg['enabled'])) {
            return ['recorded' => 0, 'candidates' => []];
        }

        $newCandidates = self::extractCandidates($localResult, $turgenevData);
        if ($newCandidates === []) {
            return ['recorded' => 0, 'candidates' => []];
        }

        return self::persistCandidates($newCandidates, 'turgenev_diff');
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array{recorded: int, candidates: array<int, array<string, mixed>>}
     */
    public static function recordFromReport(array $candidates): array
    {
        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        if (empty($cfg['enabled'])) {
            return ['recorded' => 0, 'candidates' => []];
        }

        if ($candidates === []) {
            return ['recorded' => 0, 'candidates' => []];
        }

        return self::persistCandidates($candidates, 'turgenev_report');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listCandidates(): array
    {
        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        $path = (string) ($cfg['storage_path'] ?? storage_path('app/esenin/style-candidates.json'));
        $store = self::loadStore($path);

        return is_array($store['items'] ?? null) ? $store['items'] : [];
    }

    /**
     * @return array<int, array{phrase: string, weight: int, hint: string}>
     */
    public static function promotedEntries(int $minHits = 3): array
    {
        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        $path = (string) ($cfg['storage_path'] ?? storage_path('app/esenin/style-candidates.json'));
        $store = self::loadStore($path);
        $entries = [];

        foreach ($store['items'] ?? [] as $item) {
            if ((int) ($item['hits'] ?? 0) < $minHits) {
                continue;
            }

            $entries[] = [
                'phrase' => (string) ($item['phrase'] ?? ''),
                'weight' => max(1, (int) ($item['weight'] ?? 2)),
                'hint' => (string) ($item['hint'] ?? 'Кандидат из сравнения с Тургеневым'),
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array{recorded: int, candidates: array<int, array<string, mixed>>}
     */
    private static function persistCandidates(array $candidates, string $source): array
    {
        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        $path = (string) ($cfg['storage_path'] ?? storage_path('app/esenin/style-candidates.json'));
        $store = self::loadStore($path);

        $recorded = 0;
        foreach ($candidates as $candidate) {
            $phrase = self::normalizeStoredPhrase((string) ($candidate['phrase'] ?? ''));
            if ($phrase === '') {
                continue;
            }

            $key = self::candidateKey($phrase, (string) ($candidate['rule_id'] ?? ''));
            $now = date('c');

            if (! isset($store['items'][$key])) {
                $store['items'][$key] = [
                    'phrase' => $phrase,
                    'hint' => (string) ($candidate['hint'] ?? ''),
                    'source' => $source,
                    'rule_id' => (string) ($candidate['rule_id'] ?? ''),
                    'block' => (string) ($candidate['block'] ?? 'style'),
                    'weight' => max(1, (int) ($candidate['weight'] ?? 1)),
                    'severity' => (string) ($candidate['severity'] ?? ''),
                    'hits' => 0,
                    'first_seen' => $now,
                    'last_seen' => $now,
                    'examples' => [],
                ];
            }

            $store['items'][$key]['hits'] = (int) ($store['items'][$key]['hits'] ?? 0) + 1;
            $store['items'][$key]['last_seen'] = $now;
            $store['items'][$key]['source'] = $source;

            if (! empty($candidate['hint'])) {
                $store['items'][$key]['hint'] = (string) $candidate['hint'];
            }
            if (! empty($candidate['rule_id'])) {
                $store['items'][$key]['rule_id'] = (string) $candidate['rule_id'];
            }
            if (! empty($candidate['block'])) {
                $store['items'][$key]['block'] = (string) $candidate['block'];
            }
            if (! empty($candidate['weight'])) {
                $store['items'][$key]['weight'] = max(
                    (int) ($store['items'][$key]['weight'] ?? 1),
                    (int) $candidate['weight']
                );
            }
            if (! empty($candidate['severity'])) {
                $store['items'][$key]['severity'] = (string) $candidate['severity'];
            }

            $examples = is_array($candidate['examples'] ?? null) ? $candidate['examples'] : [];
            if ($examples !== []) {
                $existing = is_array($store['items'][$key]['examples'] ?? null)
                    ? $store['items'][$key]['examples']
                    : [];
                $store['items'][$key]['examples'] = array_values(array_slice(array_unique(array_merge($existing, $examples)), 0, 10));
            }

            $recorded++;
        }

        $store['updated_at'] = date('c');
        self::saveStore($path, $store);

        return ['recorded' => $recorded, 'candidates' => $candidates];
    }

    /**
     * @param array<string, mixed> $localResult
     * @param array<string, mixed> $turgenevData
     * @return array<int, array{phrase: string, hint: string, block: string}>
     */
    private static function extractCandidates(array $localResult, array $turgenevData): array
    {
        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        $minScore = (int) ($cfg['min_turgenev_param_score'] ?? 1);
        $localBlocks = self::indexBlockScores($localResult);
        $candidates = [];

        foreach ($turgenevData['details'] ?? [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $block = (string) ($detail['block'] ?? '');
            $remoteSum = (int) ($detail['sum'] ?? 0);
            $localSum = (int) ($localBlocks[$block] ?? 0);

            if ($remoteSum <= $localSum) {
                continue;
            }

            foreach ($detail['params'] ?? [] as $param) {
                if (! is_array($param)) {
                    continue;
                }

                $score = (int) ($param['score'] ?? 0);
                if ($score < $minScore) {
                    continue;
                }

                $name = trim((string) ($param['name'] ?? ''));
                $value = trim((string) ($param['value'] ?? ''));
                $phrase = self::guessPhrase($name, $value);
                if ($phrase === null) {
                    continue;
                }

                $candidates[] = [
                    'phrase' => $phrase,
                    'hint' => 'Тургенев: ' . $name . ($value !== '' ? ' (' . $value . ')' : ''),
                    'block' => $block,
                    'weight' => 1,
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, int>
     */
    private static function indexBlockScores(array $result): array
    {
        $map = [];
        foreach ($result['details'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $map[(string) ($row['block'] ?? '')] = (int) ($row['sum'] ?? 0);
        }

        return $map;
    }

    private static function candidateKey(string $phrase, string $ruleId): string
    {
        if ($ruleId !== '') {
            return mb_strtolower($ruleId . ':' . $phrase, 'UTF-8');
        }

        return mb_strtolower($phrase, 'UTF-8');
    }

    private static function normalizeStoredPhrase(string $phrase): string
    {
        $phrase = preg_replace('/\s+/u', ' ', trim($phrase));

        return mb_strtolower((string) $phrase, 'UTF-8');
    }

    private static function guessPhrase(string $name, string $value): ?string
    {
        foreach ([$value, $name] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            if (mb_strlen($candidate, 'UTF-8') < 4 || mb_strlen($candidate, 'UTF-8') > 80) {
                continue;
            }
            if (! preg_match('/[\p{L}]{4,}/u', $candidate)) {
                continue;
            }
            if (preg_match('/^\d+([.,]\d+)?$/', $candidate)) {
                continue;
            }

            return mb_strtolower($candidate, 'UTF-8');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadStore(string $path): array
    {
        if (! is_file($path)) {
            return ['items' => []];
        }

        $json = file_get_contents($path);
        if (! is_string($json) || trim($json) === '') {
            return ['items' => []];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : ['items' => []];
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function saveStore(string $path, array $store): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($store, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
