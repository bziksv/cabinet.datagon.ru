<?php

namespace App\Services\SiteAudit;

use Illuminate\Support\Facades\DB;

/**
 * Оценка размера данных краула в БД (payload строк, без HTML-файлов).
 *
 * Важно: не делаем SUM(LENGTH(longtext)) по pages/findings — на remote MySQL
 * это десятки секунд на список краулов. Считаем дешёвую оценку по meta + счётчикам.
 */
class SiteAuditCrawlStorage
{
    /** Примерный вес одной строки site_audit_pages (без HTML). */
    private const BYTES_PER_PAGE = 1600;

    /** Примерный вес одной finding. */
    private const BYTES_PER_FINDING = 200;

    /**
     * @param int[] $crawlIds
     * @return array<int,int> crawl_id => bytes
     */
    public static function payloadBytesByCrawlIds(array $crawlIds): array
    {
        $crawlIds = array_values(array_filter(array_map('intval', $crawlIds)));
        if ($crawlIds === []) {
            return [];
        }

        $out = array_fill_keys($crawlIds, 0);
        $placeholders = implode(',', array_fill(0, count($crawlIds), '?'));

        $crawlRows = DB::select(
            "SELECT id, pages_fetched, counts_json,
                COALESCE(LENGTH(COALESCE(buckets_json,'')),0)
              + COALESCE(LENGTH(COALESCE(counts_json,'')),0)
              + COALESCE(LENGTH(COALESCE(error,'')),0)
              + COALESCE(LENGTH(COALESCE(share_token,'')),0)
              + 200 AS meta_bytes
             FROM site_audit_crawls WHERE id IN ($placeholders)",
            $crawlIds
        );

        foreach ($crawlRows as $row) {
            $id = (int) $row->id;
            $pages = max(0, (int) $row->pages_fetched);
            $findings = 0;
            $counts = json_decode((string) ($row->counts_json ?? ''), true);
            if (is_array($counts)) {
                foreach ($counts as $c) {
                    $findings += (int) $c;
                }
            }

            $out[$id] = (int) $row->meta_bytes
                + $pages * self::BYTES_PER_PAGE
                + $findings * self::BYTES_PER_FINDING;
        }

        return $out;
    }

    /**
     * Точный размер (тяжёлый). Только для админ/отладки, не для списка краулов.
     *
     * @param int[] $crawlIds
     * @return array<int,int>
     */
    public static function payloadBytesExactByCrawlIds(array $crawlIds): array
    {
        $crawlIds = array_values(array_filter(array_map('intval', $crawlIds)));
        if ($crawlIds === []) {
            return [];
        }

        $out = array_fill_keys($crawlIds, 0);
        $placeholders = implode(',', array_fill(0, count($crawlIds), '?'));

        $crawlRows = DB::select(
            "SELECT id,
                COALESCE(LENGTH(COALESCE(buckets_json,'')),0)
              + COALESCE(LENGTH(COALESCE(counts_json,'')),0)
              + COALESCE(LENGTH(COALESCE(progress_json,'')),0)
              + COALESCE(LENGTH(COALESCE(error,'')),0)
              + COALESCE(LENGTH(COALESCE(share_token,'')),0)
              + 200 AS bytes
             FROM site_audit_crawls WHERE id IN ($placeholders)",
            $crawlIds
        );
        foreach ($crawlRows as $row) {
            $out[(int) $row->id] += (int) $row->bytes;
        }

        $pageRows = DB::select(
            "SELECT crawl_id,
                COALESCE(SUM(
                    COALESCE(LENGTH(url),0)+COALESCE(LENGTH(url_hash),0)+COALESCE(LENGTH(final_url),0)
                  + COALESCE(LENGTH(CAST(redirect_chain AS CHAR)),0)+COALESCE(LENGTH(content_type),0)
                  + COALESCE(LENGTH(title),0)+COALESCE(LENGTH(title_hash),0)
                  + COALESCE(LENGTH(description),0)+COALESCE(LENGTH(description_hash),0)
                  + COALESCE(LENGTH(h1),0)+COALESCE(LENGTH(canonical),0)+COALESCE(LENGTH(robots_meta),0)
                  + COALESCE(LENGTH(content_hash),0)+COALESCE(LENGTH(simhash),0)
                  + COALESCE(LENGTH(CAST(out_links_json AS CHAR)),0)+COALESCE(LENGTH(html_storage_key),0)
                  + 80
                ),0) AS bytes
             FROM site_audit_pages WHERE crawl_id IN ($placeholders)
             GROUP BY crawl_id",
            $crawlIds
        );
        foreach ($pageRows as $row) {
            $out[(int) $row->crawl_id] += (int) $row->bytes;
        }

        $findingRows = DB::select(
            "SELECT crawl_id,
                COALESCE(SUM(
                    COALESCE(LENGTH(code),0)+COALESCE(LENGTH(severity),0)
                  + COALESCE(LENGTH(url),0)+COALESCE(LENGTH(url_hash),0)
                  + COALESCE(LENGTH(CAST(meta_json AS CHAR)),0)+40
                ),0) AS bytes
             FROM site_audit_findings WHERE crawl_id IN ($placeholders)
             GROUP BY crawl_id",
            $crawlIds
        );
        foreach ($findingRows as $row) {
            $out[(int) $row->crawl_id] += (int) $row->bytes;
        }

        $statRows = DB::select(
            "SELECT crawl_id,
                COALESCE(SUM(COALESCE(LENGTH(bucket),0)+20),0) AS bytes
             FROM site_audit_crawl_stats WHERE crawl_id IN ($placeholders)
             GROUP BY crawl_id",
            $crawlIds
        );
        foreach ($statRows as $row) {
            $out[(int) $row->crawl_id] += (int) $row->bytes;
        }

        return $out;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 1, '.', ''), '0'), '.') . ' KB';
        }

        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 3, '.', ''), '0'), '.') . ' MB';
    }
}
