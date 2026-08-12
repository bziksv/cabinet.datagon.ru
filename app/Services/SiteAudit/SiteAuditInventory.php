<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Support\Collection;

/**
 * Инвентарь проверки: все страницы / все картинки (не findings).
 */
class SiteAuditInventory
{
    public const SOURCE_PAGES = 'pages_all';

    public const SOURCE_IMAGES = 'pages_images';

    public const SOURCE_CANONICAL = 'pages_canonical';

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    public const PER_PAGE_DEFAULT = 50;

    public static function isInventorySource(?string $source): bool
    {
        return in_array((string) $source, [
            self::SOURCE_PAGES,
            self::SOURCE_IMAGES,
            self::SOURCE_CANONICAL,
        ], true);
    }

    /**
     * @param  mixed  $value
     */
    public static function normalizePerPage($value): int
    {
        $n = (int) $value;

        return in_array($n, self::PER_PAGE_OPTIONS, true) ? $n : self::PER_PAGE_DEFAULT;
    }

    /**
     * @param  array<string,string>  $filters
     * @return array{0:int,1:Collection<int,object>}
     */
    public static function paginate(
        SiteAuditCrawl $crawl,
        string $source,
        int $page,
        int $perPage,
        array $filters = [],
        ?string $sort = null,
        ?string $dir = null
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        if ($source === self::SOURCE_IMAGES) {
            return self::paginateImages($crawl, $page, $perPage, $filters, $sort, $dir);
        }

        $query = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id);

        if ($source === self::SOURCE_CANONICAL) {
            $query->whereNotNull('canonical')->where('canonical', '!=', '');
        }

        SiteAuditReportFilter::applyToPages($query, $filters);
        self::applySort($query, $source, $sort, $dir);
        $total = (clone $query)->count();
        $code = $source === self::SOURCE_CANONICAL ? 'pages_with_canonical' : 'crawl_pages';
        $severity = (string) config('site_audit.findings.' . $code . '.severity', 'info');

        $rows = $query->forPage($page, $perPage)->get()->map(function (SiteAuditPage $p) use ($source, $code, $severity) {
            return self::pageToRow($p, $source, $code, $severity);
        });

        return [$total, $rows];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applySort($query, string $source, ?string $sort, ?string $dir): void
    {
        if ($source === self::SOURCE_CANONICAL) {
            [$sortKey, $sortDir] = SiteAuditCrawlPagesColumns::normalizeSort(
                in_array((string) $sort, ['url', 'canonical'], true) ? $sort : 'url',
                $dir
            );
            if ($sortKey === 'canonical') {
                $query->orderBy('site_audit_pages.canonical', $sortDir);
            } else {
                $query->orderBy('site_audit_pages.url', $sortDir);
            }
            $query->orderBy('site_audit_pages.id');

            return;
        }

        [$sortKey, $sortDir] = SiteAuditCrawlPagesColumns::normalizeSort($sort, $dir);
        $sql = SiteAuditCrawlPagesColumns::sortableSql()[$sortKey] ?? 'site_audit_pages.url';
        $query->orderByRaw($sql . ' ' . ($sortDir === 'desc' ? 'DESC' : 'ASC'));
        $query->orderBy('site_audit_pages.id');
    }

    /**
     * Группы по URL картинки (одна картинка → список страниц).
     *
     * @param  array<string,string>  $filters
     * @return array{0:int,1:list<array<string,mixed>>,2:int,3:?array} [вхождения, groups, уникальных, sitewide]
     */
    public static function paginateImageGroups(
        SiteAuditCrawl $crawl,
        int $page,
        int $perPage,
        array $filters = [],
        ?string $sort = null,
        ?string $dir = null
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $matched = self::collectMatchedImageRows($crawl, $filters);
        $occurrenceTotal = count($matched);

        $pagesInCrawl = (int) SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->count();

        $buckets = [];
        foreach ($matched as $row) {
            $src = (string) ($row->url ?? '');
            if ($src === '') {
                continue;
            }
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $pageUrl = (string) ($meta['page_url'] ?? '');
            if (! isset($buckets[$src])) {
                $host = SiteAuditImageItem::hostOf($src);
                $hintParts = [];
                $ext = (string) ($meta['ext'] ?? '');
                if ($ext !== '') {
                    $hintParts[] = $ext;
                }
                if (! empty($meta['external'])) {
                    $hintParts[] = 'внешняя';
                }
                if (! empty($meta['https'])) {
                    $hintParts[] = 'https';
                } elseif (isset($meta['https'])) {
                    $hintParts[] = 'http';
                }
                if (isset($meta['size_bytes']) && $meta['size_bytes'] !== null) {
                    $hintParts[] = SiteAuditImageItem::formatSizeBytes((int) $meta['size_bytes']);
                }
                $dims = SiteAuditImageItem::formatDimensions(
                    isset($meta['width']) ? (int) $meta['width'] : null,
                    isset($meta['height']) ? (int) $meta['height'] : null
                );
                if ($dims !== '—') {
                    $hintParts[] = $dims;
                }
                if (array_key_exists('has_alt', $meta) && $meta['has_alt'] === false) {
                    $hintParts[] = 'без alt';
                } elseif (! empty($meta['alt'])) {
                    $hintParts[] = 'alt: ' . mb_substr((string) $meta['alt'], 0, 40);
                }
                $buckets[$src] = [
                    'hash' => 'img:' . md5($src),
                    'size' => 0,
                    'label' => $src,
                    'severity' => (string) ($row->severity ?? 'info'),
                    'urls' => [],
                    'hint' => $hintParts !== [] ? implode(' · ', $hintParts) : null,
                    'likely_template' => false,
                    'href' => $src,
                    'host' => $host,
                    'status' => $meta['status'] ?? null,
                    'scope' => ! empty($meta['external']) ? 'external' : 'internal',
                    '_urls' => [],
                    '_meta' => $meta,
                ];
            }
            if ($pageUrl !== '' && ! isset($buckets[$src]['_urls'][$pageUrl])) {
                $buckets[$src]['_urls'][$pageUrl] = true;
                $buckets[$src]['urls'][] = [
                    'url' => $pageUrl,
                    'severity' => (string) ($row->severity ?? 'info'),
                ];
                $buckets[$src]['size'] = count($buckets[$src]['urls']);
            }
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            unset($bucket['_urls'], $bucket['_meta']);
            $bucket['likely_template'] = self::imageLikelySitewide((int) $bucket['size'], $pagesInCrawl);
            $groups[] = $bucket;
        }

        [$sortKey, $sortDir] = SiteAuditCrawlImagesColumns::normalizeSort($sort, $dir);
        // В группах по умолчанию — сначала самые частые (сквозные).
        if ($sort === null || $sort === '' || $sortKey === 'url') {
            usort($groups, static function (array $a, array $b) {
                if ($a['size'] === $b['size']) {
                    return strcmp((string) $a['href'], (string) $b['href']);
                }

                return $b['size'] <=> $a['size'];
            });
        } else {
            usort($groups, static function (array $a, array $b) use ($sortKey, $sortDir) {
                $ma = [
                    'page_url' => '',
                    'ext' => '',
                    'https' => false,
                    'external' => ($a['scope'] ?? '') === 'external',
                    'status' => $a['status'] ?? null,
                    'size_bytes' => null,
                    'has_alt' => null,
                    'alt' => '',
                    'url_len' => mb_strlen((string) ($a['href'] ?? '')),
                    'file' => '',
                    'width' => null,
                    'height' => null,
                    'loading' => null,
                    'content_type' => null,
                ];
                $mb = $ma;
                // size column in groups = page count when sorting by size? map 'size' sort to page count
                if ($sortKey === 'size') {
                    $va = (int) $a['size'];
                    $vb = (int) $b['size'];
                } elseif ($sortKey === 'page_url') {
                    $va = (int) $a['size'];
                    $vb = (int) $b['size'];
                } else {
                    $va = SiteAuditCrawlImagesColumns::sortValue($sortKey, (string) $a['href'], $ma);
                    $vb = SiteAuditCrawlImagesColumns::sortValue($sortKey, (string) $b['href'], $mb);
                    if ($sortKey === 'status') {
                        $va = isset($a['status']) ? (int) $a['status'] : null;
                        $vb = isset($b['status']) ? (int) $b['status'] : null;
                    }
                    if ($sortKey === 'external') {
                        $va = ($a['scope'] ?? '') === 'external' ? 1 : 0;
                        $vb = ($b['scope'] ?? '') === 'external' ? 1 : 0;
                    }
                }
                $cmp = self::compareSortValues($va, $vb);
                if ($cmp === 0) {
                    $cmp = $b['size'] <=> $a['size'];
                }

                return $sortDir === 'desc' ? -$cmp : $cmp;
            });
        }

        $groupTotal = count($groups);
        $sitewide = SiteAuditDuplicateGrouper::sitewideSummary($groups, $pagesInCrawl);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($groups, $offset, $perPage);

        return [$occurrenceTotal, $slice, $groupTotal, $sitewide];
    }

    private static function imageLikelySitewide(int $pagesWithImage, int $pagesInCrawl): bool
    {
        if ($pagesWithImage < 5) {
            return false;
        }
        if ($pagesInCrawl <= 0) {
            return $pagesWithImage >= 10;
        }

        return ($pagesWithImage / $pagesInCrawl) >= 0.4;
    }

    /**
     * @param  array<string,string>  $filters
     * @return array{0:int,1:Collection<int,object>}
     */
    private static function paginateImages(
        SiteAuditCrawl $crawl,
        int $page,
        int $perPage,
        array $filters,
        ?string $sort = null,
        ?string $dir = null
    ): array {
        [$sortKey, $sortDir] = SiteAuditCrawlImagesColumns::normalizeSort($sort, $dir);
        $matched = self::collectMatchedImageRows($crawl, $filters);

        usort($matched, static function ($a, $b) use ($sortKey, $sortDir) {
            $va = SiteAuditCrawlImagesColumns::sortValue($sortKey, (string) $a->url, (array) $a->meta_json);
            $vb = SiteAuditCrawlImagesColumns::sortValue($sortKey, (string) $b->url, (array) $b->meta_json);
            $cmp = self::compareSortValues($va, $vb);
            if ($cmp === 0) {
                $cmp = strcmp((string) $a->url, (string) $b->url);
            }

            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

        $total = count($matched);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($matched, $offset, $perPage);

        return [$total, collect($slice)];
    }

    /**
     * @param  array<string,string>  $filters
     * @return list<object>
     */
    private static function collectMatchedImageRows(SiteAuditCrawl $crawl, array $filters): array
    {
        $urlFilter = trim((string) ($filters['url'] ?? ''));
        $pageFilter = trim((string) ($filters['page'] ?? ($filters['details'] ?? '')));
        $extFilter = SiteAuditReportFilter::multiFilterTokensPublic($filters['ext'] ?? '');
        $statusFilter = SiteAuditReportFilter::multiFilterTokensPublic($filters['status'] ?? '');
        $httpsFilter = (string) ($filters['https'] ?? '');
        $externalFilter = (string) ($filters['external'] ?? '');
        $hasAltFilter = (string) ($filters['has_alt'] ?? '');
        $brokenFilter = (string) ($filters['broken'] ?? '');
        $loadingFilter = (string) ($filters['loading'] ?? '');
        $urlLenMin = isset($filters['url_len_min']) ? (int) $filters['url_len_min'] : null;
        $urlLenMax = isset($filters['url_len_max']) ? (int) $filters['url_len_max'] : null;
        $sizeKbMin = isset($filters['size_kb_min']) ? (int) $filters['size_kb_min'] : null;
        $sizeKbMax = isset($filters['size_kb_max']) ? (int) $filters['size_kb_max'] : null;
        $widthMin = isset($filters['width_min']) ? (int) $filters['width_min'] : null;
        $widthMax = isset($filters['width_max']) ? (int) $filters['width_max'] : null;
        $heightMin = isset($filters['height_min']) ? (int) $filters['height_min'] : null;
        $heightMax = isset($filters['height_max']) ? (int) $filters['height_max'] : null;
        $altFilter = trim((string) ($filters['alt'] ?? ''));

        $probeHints = self::imageProbeHintsFromFindings((int) $crawl->id);
        $severity = (string) config('site_audit.findings.crawl_images.severity', 'info');

        $matched = [];
        $query = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where('img_count', '>', 0)
            ->orderBy('id')
            ->select(['id', 'url', 'img_srcs_json', 'img_count']);

        foreach ($query->cursor() as $p) {
            $pageUrl = (string) $p->url;
            $pageHost = SiteAuditImageItem::hostOf($pageUrl);
            $items = SiteAuditImageItem::normalizeList($p->img_srcs_json);
            if ($items === []) {
                continue;
            }
            foreach ($items as $item) {
                $src = $item['src'];
                $hint = $probeHints[$src] ?? null;
                $status = $item['status'];
                $sizeBytes = $item['size_bytes'];
                $ok = $item['ok'];
                $contentType = $item['content_type'];
                if ($hint !== null) {
                    if ($status === null && isset($hint['status'])) {
                        $status = $hint['status'];
                    }
                    if ($sizeBytes === null && isset($hint['size_bytes'])) {
                        $sizeBytes = $hint['size_bytes'];
                    }
                    if ($ok === null && array_key_exists('ok', $hint)) {
                        $ok = $hint['ok'];
                    }
                    if ($contentType === null && ! empty($hint['content_type'])) {
                        $contentType = $hint['content_type'];
                    }
                }
                if ($ok === null && $status !== null) {
                    $ok = $status >= 200 && $status < 400;
                }

                $ext = SiteAuditImageItem::extensionOf($src);
                $https = stripos($src, 'https://') === 0;
                $imgHost = SiteAuditImageItem::hostOf($src);
                $external = $imgHost !== '' && $pageHost !== '' && $imgHost !== $pageHost;
                $urlLen = mb_strlen($src);
                $alt = $item['alt'];
                $hasAlt = $item['has_alt'];
                $width = $item['width'];
                $height = $item['height'];
                $loading = $item['loading'];
                $broken = $ok === false;

                if ($urlFilter !== ''
                    && ! SiteAuditReportFilter::smartContains($src, $urlFilter)
                    && ! SiteAuditReportFilter::smartContains($pageUrl, $urlFilter)) {
                    continue;
                }
                if ($pageFilter !== '' && ! SiteAuditReportFilter::smartContains($pageUrl, $pageFilter)) {
                    continue;
                }
                if ($extFilter !== [] && ! in_array($ext, $extFilter, true)) {
                    continue;
                }
                if ($statusFilter !== [] && ! self::imageStatusMatches($status, $ok, $statusFilter)) {
                    continue;
                }
                if ($httpsFilter === '1' && ! $https) {
                    continue;
                }
                if ($httpsFilter === '0' && $https) {
                    continue;
                }
                if ($externalFilter === '1' && ! $external) {
                    continue;
                }
                if ($externalFilter === '0' && $external) {
                    continue;
                }
                if ($hasAltFilter === '1' && $hasAlt !== true) {
                    continue;
                }
                if ($hasAltFilter === '0' && $hasAlt !== false) {
                    continue;
                }
                if ($brokenFilter === '1' && ! $broken) {
                    continue;
                }
                if ($brokenFilter === '0' && ($ok !== true)) {
                    continue;
                }
                if ($loadingFilter === 'none' && $loading !== null) {
                    continue;
                }
                if ($loadingFilter !== '' && $loadingFilter !== 'none' && $loading !== $loadingFilter) {
                    continue;
                }
                if ($urlLenMin !== null && $urlLen < $urlLenMin) {
                    continue;
                }
                if ($urlLenMax !== null && $urlLen > $urlLenMax) {
                    continue;
                }
                if ($sizeKbMin !== null) {
                    if ($sizeBytes === null || $sizeBytes < ($sizeKbMin * 1024)) {
                        continue;
                    }
                }
                if ($sizeKbMax !== null) {
                    if ($sizeBytes === null || $sizeBytes > ($sizeKbMax * 1024)) {
                        continue;
                    }
                }
                if ($widthMin !== null && ($width === null || $width < $widthMin)) {
                    continue;
                }
                if ($widthMax !== null && ($width === null || $width > $widthMax)) {
                    continue;
                }
                if ($heightMin !== null && ($height === null || $height < $heightMin)) {
                    continue;
                }
                if ($heightMax !== null && ($height === null || $height > $heightMax)) {
                    continue;
                }
                if ($altFilter !== '') {
                    if ($alt === null || $alt === '') {
                        continue;
                    }
                    if (! SiteAuditReportFilter::smartContains($alt, $altFilter)) {
                        continue;
                    }
                }

                $path = (string) (parse_url($src, PHP_URL_PATH) ?: '');
                $file = $path !== '' ? basename($path) : '';
                $meta = [
                    'page_url' => $pageUrl,
                    'img_src' => $src,
                    'ext' => $ext,
                    'https' => $https,
                    'external' => $external,
                    'alt' => $alt,
                    'has_alt' => $hasAlt,
                    'url_len' => $urlLen,
                    'file' => $file !== '' ? $file : null,
                    'status' => $status,
                    'size_bytes' => $sizeBytes,
                    'ok' => $ok,
                    'broken' => $broken,
                    'width' => $width,
                    'height' => $height,
                    'loading' => $loading,
                    'content_type' => $contentType,
                ];
                $matched[] = (object) [
                    'id' => null,
                    'url' => $src,
                    'url_hash' => null,
                    'severity' => $severity,
                    'code' => 'crawl_images',
                    'meta_json' => $meta,
                ];
            }
        }

        return $matched;
    }

    /**
     * Подсказки status/size из findings (старые проверки без записи в img_srcs_json).
     *
     * @return array<string,array{status:?int,size_bytes:?int,ok:?bool,content_type:?string}>
     */
    private static function imageProbeHintsFromFindings(int $crawlId): array
    {
        $map = [];
        $rows = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->whereIn('code', ['broken_image', 'heavy_image'])
            ->get(['code', 'meta_json']);
        foreach ($rows as $row) {
            $meta = is_array($row->meta_json) ? $row->meta_json : [];
            $samples = is_array($meta['samples'] ?? null) ? $meta['samples'] : [];
            foreach ($samples as $sample) {
                if (! is_array($sample)) {
                    continue;
                }
                $img = trim((string) ($sample['img'] ?? ''));
                if ($img === '') {
                    continue;
                }
                if (! isset($map[$img])) {
                    $map[$img] = [
                        'status' => null,
                        'size_bytes' => null,
                        'ok' => null,
                        'content_type' => null,
                    ];
                }
                if ($row->code === 'broken_image') {
                    $map[$img]['ok'] = false;
                    if (isset($sample['status']) && $sample['status'] !== null && $sample['status'] !== '') {
                        $map[$img]['status'] = (int) $sample['status'];
                    }
                } elseif ($row->code === 'heavy_image') {
                    if ($map[$img]['ok'] === null) {
                        $map[$img]['ok'] = true;
                    }
                    if ($map[$img]['status'] === null) {
                        $map[$img]['status'] = 200;
                    }
                    if (isset($sample['size_bytes'])) {
                        $map[$img]['size_bytes'] = (int) $sample['size_bytes'];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $tokens
     */
    private static function imageStatusMatches(?int $status, ?bool $ok, array $tokens): bool
    {
        foreach ($tokens as $token) {
            $token = strtolower(trim((string) $token));
            if ($token === '') {
                continue;
            }
            if ($token === 'err' || $token === 'error') {
                if ($ok === false) {
                    return true;
                }
                continue;
            }
            if ($token === 'unknown' || $token === 'na') {
                if ($status === null && $ok === null) {
                    return true;
                }
                continue;
            }
            if (preg_match('/^([1-5])xx$/', $token, $m)) {
                $base = ((int) $m[1]) * 100;
                if ($status !== null && $status >= $base && $status <= $base + 99) {
                    return true;
                }
                continue;
            }
            if (ctype_digit($token) && $status !== null && $status === (int) $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $a
     * @param  mixed  $b
     */
    private static function compareSortValues($a, $b): int
    {
        $aNull = $a === null || $a === '';
        $bNull = $b === null || $b === '';
        if ($aNull && $bNull) {
            return 0;
        }
        if ($aNull) {
            return 1;
        }
        if ($bNull) {
            return -1;
        }
        if (is_numeric($a) && is_numeric($b)) {
            if ((float) $a == (float) $b) {
                return 0;
            }

            return ((float) $a < (float) $b) ? -1 : 1;
        }

        return strcmp((string) $a, (string) $b);
    }

    /**
     * Публичная сборка строки инвентаря (для пакетного поиска и экспорта).
     */
    public static function pageToRowPublic(SiteAuditPage $p, string $source, string $code, string $severity): object
    {
        return self::pageToRow($p, $source, $code, $severity);
    }

    private static function pageToRow(SiteAuditPage $p, string $source, string $code, string $severity): object
    {
        if ($source === self::SOURCE_CANONICAL) {
            $meta = ['canonical' => $p->canonical];
        } else {
            $out = is_array($p->out_links_json) ? $p->out_links_json : [];
            $ext = is_array($p->ext_links_json) ? $p->ext_links_json : [];
            $headings = is_array($p->headings_json) ? $p->headings_json : null;
            $title = trim((string) ($p->title ?? ''));
            $desc = trim((string) ($p->description ?? ''));
            $keywords = trim((string) ($p->keywords_meta ?? ''));
            $url = (string) $p->url;
            $meta = [
                'status_code' => $p->status_code,
                'title' => $title !== '' ? $title : null,
                'title_len' => $title !== '' ? mb_strlen($title) : 0,
                'description' => $desc !== '' ? $desc : null,
                'desc_len' => $desc !== '' ? mb_strlen($desc) : 0,
                'keywords' => $keywords !== '' ? $keywords : null,
                'keywords_len' => $keywords !== '' ? mb_strlen($keywords) : 0,
                'h1' => $p->h1,
                'h1_count' => (int) ($p->h1_count ?? 0),
                'h2_count' => (int) ($p->h2_count ?? 0),
                'h3_count' => null,
                'h4_count' => null,
                'h5_count' => null,
                'h6_count' => null,
                'headings' => $headings,
                'word_count' => (int) ($p->word_count ?? 0),
                'text_len' => (int) ($p->text_len ?? 0),
                'out_links' => count($out),
                'ext_links' => count($ext),
                'img_count' => (int) ($p->img_count ?? 0),
                'img_without_alt' => (int) ($p->img_without_alt ?? 0),
                'canonical' => $p->canonical,
                'noindex' => (bool) ($p->noindex ?? false),
                'robots_meta' => $p->robots_meta,
                'size_bytes' => $p->size_bytes,
                'content_type' => $p->content_type,
                'charset' => $p->charset ?? null,
                'final_url' => $p->final_url,
                'click_depth' => $p->click_depth,
                'discovered_via' => $p->discovered_via,
                'url_len' => mb_strlen($url),
                'https' => stripos($url, 'https://') === 0,
                'headings_complete' => $headings !== null,
            ];
            // Fallback текст H1, если headings_json ещё нет (старые проверки).
            if ($headings === null) {
                $meta['headings'] = [
                    'h1' => $p->h1 ? [(string) $p->h1] : [],
                    'h2' => [],
                    'h3' => [],
                    'h4' => [],
                    'h5' => [],
                    'h6' => [],
                ];
            } else {
                foreach (['h3', 'h4', 'h5', 'h6'] as $hk) {
                    $list = is_array($headings[$hk] ?? null) ? $headings[$hk] : [];
                    $meta[$hk . '_count'] = count($list);
                }
            }
        }

        return (object) [
            'id' => null,
            'url' => $p->url,
            'url_hash' => $p->url_hash,
            'severity' => $severity,
            'code' => $code,
            'meta_json' => $meta,
        ];
    }
}
