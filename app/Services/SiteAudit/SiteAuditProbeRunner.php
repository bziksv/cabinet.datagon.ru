<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use Illuminate\Support\Facades\DB;

/**
 * Точечный запуск опциональной пробы по уже скачанной проверке.
 */
class SiteAuditProbeRunner
{
    public function run(SiteAuditCrawl $crawl, string $probeId, bool $force = true, string $mode = '', ?string $engine = null): array
    {
        $meta = SiteAuditProbeStatus::catalog()[$probeId] ?? null;
        if ($meta === null) {
            return ['ok' => false, 'message' => 'Неизвестная проверка'];
        }

        $mode = trim($mode);
        switch ($probeId) {
            case 'psi':
                (new SiteAuditPsiProbe())->run($crawl, $force);
                break;
            case 'serp_snippets':
                (new SiteAuditSerpSnippetsProbe())->run($crawl, $force);
                break;
            case 'serp_cannibalization':
                (new SiteAuditSerpCannibalizationProbe())->run($crawl, $force);
                break;
            case 'serp_index':
                // Полная сверка списка — тот же контур, что и при аудите.
                (new SiteAuditSerpIndexProbe())->run($crawl, true);
                break;
            default:
                return ['ok' => false, 'message' => 'Неизвестная проверка'];
        }

        $crawl->refresh();
        $this->refreshCounts($crawl, $meta['codes']);

        $status = SiteAuditProbeStatus::forProbe($crawl, $probeId);
        if ($status && $status['status'] === 'skipped') {
            return [
                'ok' => false,
                'message' => $meta['title'] . ': снова пропущена ('
                    . SiteAuditProbeStatus::reasonLabel($status['reason'] ?? null, $probeId) . ')',
            ];
        }

        $n = (int) SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', $meta['codes'])
            ->count();

        return [
            'ok' => true,
            'message' => $meta['title'] . ' запущена. Находок: ' . $n,
            'count' => $n,
        ];
    }

    /**
     * @param  string[]  $codes
     */
    private function refreshCounts(SiteAuditCrawl $crawl, array $codes): void
    {
        $counts = is_array($crawl->counts_json) ? $crawl->counts_json : [];
        foreach ($codes as $code) {
            $counts[$code] = (int) SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', $code)
                ->count();
        }

        $buckets = [
            'critical' => 0,
            'other' => 0,
            'warning' => 0,
            'info' => 0,
        ];
        $sevRows = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->select('severity', DB::raw('count(*) as c'))
            ->groupBy('severity')
            ->pluck('c', 'severity')
            ->all();
        foreach ($buckets as $k => $_) {
            $buckets[$k] = (int) ($sevRows[$k] ?? 0);
        }

        $crawl->counts_json = $counts;
        $crawl->buckets_json = $buckets;
        $crawl->save();
    }
}
