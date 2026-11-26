<?php

namespace App\Services\SeoReports;

use App\SiteAuditFinding;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Страницы по задачам, созданным автосинком Site Audit → SEO-чеклист.
 *
 * В снимке отчёта хранится только ссылка на отчёт аудита (crawl_id, code, report_path).
 * Список URL подтягивается из site_audit_findings при показе и выгрузке,
 * поэтому галочка «приложить список страниц» работает и для уже готовых отчётов.
 */
class SeoReportAuditPages
{
    public const CODE_PREFIX = 'sa_auto_';

    public const URLS_LIMIT_VIEW = 200;

    public const URLS_LIMIT_PDF = 100;

    public const URLS_LIMIT_EXPORT = 200000;

    private const STATUS_LABELS = [
        'todo' => 'Новая',
        'doing' => 'В работе',
        'rework' => 'На доработке',
        'clarify' => 'Уточняется',
        'review' => 'На проверке',
        'done' => 'Выполнено',
        'skip' => 'Пропущено',
        'blocked' => 'Заблокировано',
    ];

    /**
     * Ссылка на отчёт аудита из links_json задачи; null для обычных (ручных) задач.
     *
     * @param  array<int, mixed>|string|null  $links
     * @return array{crawl_id:int,code:string,report_path:string}|null
     */
    public static function refFromItem(string $itemCode, $links): ?array
    {
        if (strpos($itemCode, self::CODE_PREFIX) !== 0) {
            return null;
        }
        if (is_string($links)) {
            $links = json_decode($links, true);
        }
        if (!is_array($links)) {
            return null;
        }
        foreach ($links as $link) {
            $path = is_array($link) ? (string) ($link['path'] ?? '') : '';
            if ($path !== '' && preg_match('#/site-audit/crawl/(\d+)/report/([a-z0-9_]+)#i', $path, $m)) {
                return [
                    'crawl_id' => (int) $m[1],
                    'code' => (string) $m[2],
                    'report_path' => $path,
                ];
            }
        }

        return null;
    }

    /**
     * Группы «задача → страницы» по задачам аудита из снимков «Выполненные работы» / «План работ».
     *
     * @param  list<array<int, array<string, mixed>>>  $lists
     * @return list<array{id:int,title:string,status:string,date:?string,report_path:string,code:string,total:int,urls:list<string>}>
     */
    public static function groups(array $lists, int $limit): array
    {
        $seen = [];
        $cache = [];
        $out = [];
        foreach ($lists as $items) {
            foreach ($items as $item) {
                $ref = $item['audit'] ?? null;
                if (!is_array($ref) || empty($ref['crawl_id']) || empty($ref['code'])) {
                    continue;
                }
                $id = (int) ($item['id'] ?? 0);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                $cacheKey = $ref['crawl_id'] . '|' . $ref['code'] . '|' . $limit;
                if (!isset($cache[$cacheKey])) {
                    $cache[$cacheKey] = self::urlsFor((int) $ref['crawl_id'], (string) $ref['code'], $limit);
                }

                $out[] = [
                    'id' => $id,
                    'title' => (string) ($item['title'] ?? ''),
                    'status' => (string) ($item['status'] ?? ''),
                    'date' => $item['done_at'] ?? $item['due_at'] ?? null,
                    'report_path' => (string) ($ref['report_path'] ?? ''),
                    'code' => (string) $ref['code'],
                    'total' => $cache[$cacheKey]['total'],
                    'urls' => $cache[$cacheKey]['urls'],
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{total:int,urls:list<string>}
     */
    public static function urlsFor(int $crawlId, string $code, int $limit): array
    {
        $base = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->where('code', $code)
            ->whereNotNull('url')
            ->where('url', '!=', '');

        $total = (int) (clone $base)->distinct()->count('url');
        $urls = (clone $base)
            ->distinct()
            ->orderBy('url')
            ->limit($limit)
            ->pluck('url')
            ->map(static fn ($u) => (string) $u)
            ->values()
            ->all();

        return ['total' => $total, 'urls' => $urls];
    }

    /**
     * Excel: лист «Задачи» (сводка) + отдельный лист с URL на каждую задачу аудита.
     * Возвращает путь к временному файлу или null, если страниц нет.
     *
     * @param  list<array{id:int,title:string,status:string,date:?string,report_path:string,code:string,total:int,urls:list<string>}>  $groups
     */
    public static function workbookPath(string $domain, array $groups): ?string
    {
        $groups = array_values(array_filter($groups, static fn ($g) => $g['total'] > 0));
        if ($groups === []) {
            return null;
        }

        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Задачи');
        $summary->fromArray(['Задача', 'Статус', 'Срок / дата', 'Страниц', 'Отчёт аудита'], null, 'A1');
        $summary->getStyle('A1:E1')->getFont()->setBold(true);

        $row = 2;
        foreach ($groups as $g) {
            $summary->setCellValueExplicit('A' . $row, $g['title'], DataType::TYPE_STRING);
            $summary->setCellValueExplicit('B' . $row, self::STATUS_LABELS[$g['status']] ?? $g['status'], DataType::TYPE_STRING);
            $summary->setCellValueExplicit('C' . $row, (string) ($g['date'] ?? ''), DataType::TYPE_STRING);
            $summary->setCellValueExplicit('D' . $row, $g['total'], DataType::TYPE_NUMERIC);
            $summary->setCellValueExplicit('E' . $row, $g['report_path'] !== '' ? url($g['report_path']) : '', DataType::TYPE_STRING);
            $row++;
        }
        $summary->getColumnDimension('A')->setWidth(60);
        $summary->getColumnDimension('B')->setWidth(16);
        $summary->getColumnDimension('C')->setWidth(14);
        $summary->getColumnDimension('D')->setWidth(12);
        $summary->getColumnDimension('E')->setWidth(70);

        $usedTitles = ['Задачи' => true];
        foreach ($groups as $g) {
            $sheet = new Worksheet($spreadsheet, self::uniqueSheetTitle($g['title'], $usedTitles));
            $spreadsheet->addSheet($sheet);
            $sheet->fromArray(['№', 'URL'], null, 'A1');
            $sheet->getStyle('A1:B1')->getFont()->setBold(true);
            $r = 2;
            foreach ($g['urls'] as $i => $u) {
                $sheet->setCellValueExplicit('A' . $r, $i + 1, DataType::TYPE_NUMERIC);
                $sheet->setCellValueExplicit('B' . $r, $u, DataType::TYPE_STRING);
                $r++;
            }
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(100);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $dir = storage_path('app/tmp/seo-reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/audit-pages-' . preg_replace('/[^a-z0-9\-]+/i', '-', $domain) . '-' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * @param  array<string, bool>  $used
     */
    private static function uniqueSheetTitle(string $title, array &$used): string
    {
        $base = trim((string) preg_replace('~[\\\\/?*:\[\]]+~u', ' ', $title));
        $base = $base !== '' ? mb_substr($base, 0, 31) : 'Задача';

        $name = $base;
        $n = 2;
        while (isset($used[$name])) {
            $suffix = ' (' . $n . ')';
            $name = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $n++;
        }
        $used[$name] = true;

        return $name;
    }
}
