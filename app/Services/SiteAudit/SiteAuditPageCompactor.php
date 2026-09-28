<?php

namespace App\Services\SiteAudit;

use App\SiteAuditPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * После агрегации тяжёлые JSON в site_audit_pages — рабочий мусор (ссылки, шинглы, img src).
 * Findings уже записаны; инвентарь держит скаляры + counts. Обнуляем JSON, чтобы не раздувать БД.
 */
class SiteAuditPageCompactor
{
    /**
     * Колонки, нужные только на время crawl/aggregate.
     *
     * @return list<string>
     */
    public static function heavyJsonColumns(): array
    {
        return [
            'shingles_json',
            'token_top_json',
            'noindex_links_json',
            'asset_srcs_json',
            'out_links_json',
            'ext_links_json',
            'img_srcs_json',
        ];
    }

    /**
     * @return list<string> существующие heavy-колонки
     */
    public static function presentHeavyColumns(): array
    {
        $cols = [];
        foreach (self::heavyJsonColumns() as $col) {
            if (Schema::hasColumn('site_audit_pages', $col)) {
                $cols[] = $col;
            }
        }

        return $cols;
    }

    /**
     * Обнулить heavy JSON у страниц краула (пачками).
     *
     * @return array{updated:int, columns:list<string>}
     */
    public function compactCrawl(int $crawlId, int $chunkSize = 2000): array
    {
        $cols = self::presentHeavyColumns();
        if ($cols === []) {
            return ['updated' => 0, 'columns' => []];
        }

        $chunkSize = max(100, min(10000, $chunkSize));
        $hasOutCount = Schema::hasColumn('site_audit_pages', 'out_links_count');
        $hasExtCount = Schema::hasColumn('site_audit_pages', 'ext_links_count');

        $updated = 0;
        $lastId = 0;

        while (true) {
            $ids = SiteAuditPage::query()
                ->where('crawl_id', $crawlId)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $lastId = (int) $ids->last();
            $idList = $ids->all();

            $sets = [];
            if ($hasOutCount && in_array('out_links_json', $cols, true)) {
                $sets[] = 'out_links_count = GREATEST(COALESCE(out_links_count, 0), COALESCE(JSON_LENGTH(out_links_json), 0))';
            }
            if ($hasExtCount && in_array('ext_links_json', $cols, true)) {
                $sets[] = 'ext_links_count = GREATEST(COALESCE(ext_links_count, 0), COALESCE(JSON_LENGTH(ext_links_json), 0))';
            }
            foreach ($cols as $col) {
                $sets[] = '`' . str_replace('`', '``', $col) . '` = NULL';
            }

            $whereHeavy = [];
            foreach ($cols as $col) {
                $whereHeavy[] = '`' . str_replace('`', '``', $col) . '` IS NOT NULL';
            }

            $sql = 'UPDATE site_audit_pages SET ' . implode(', ', $sets)
                . ' WHERE crawl_id = ? AND id IN (' . implode(',', array_map('intval', $idList)) . ')'
                . ' AND (' . implode(' OR ', $whereHeavy) . ')';

            $n = DB::update($sql, [$crawlId]);
            $updated += (int) $n;
        }

        return ['updated' => $updated, 'columns' => $cols];
    }

    /**
     * Все завершённые краулы (done/failed/cancelled), у которых ещё есть heavy JSON.
     *
     * @return list<int>
     */
    public function crawlIdsNeedingCompact(?int $onlyCrawlId = null): array
    {
        $cols = self::presentHeavyColumns();
        if ($cols === []) {
            return [];
        }

        if ($onlyCrawlId !== null) {
            return [$onlyCrawlId];
        }

        $or = [];
        foreach ($cols as $col) {
            $or[] = '`' . str_replace('`', '``', $col) . '` IS NOT NULL';
        }

        $rows = DB::select(
            'SELECT DISTINCT p.crawl_id AS id
             FROM site_audit_pages p
             INNER JOIN site_audit_crawls c ON c.id = p.crawl_id
             WHERE c.status IN (?, ?, ?)
               AND (' . implode(' OR ', $or) . ')
             ORDER BY p.crawl_id ASC',
            [
                \App\SiteAuditCrawl::STATUS_DONE,
                \App\SiteAuditCrawl::STATUS_FAILED,
                \App\SiteAuditCrawl::STATUS_CANCELLED,
            ]
        );

        return array_map(static function ($r) {
            return (int) $r->id;
        }, $rows);
    }
}
