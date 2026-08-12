<?php

namespace App\Exports;

use App\Services\SiteAudit\SiteAuditInventory;
use App\SiteAuditCrawl;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SiteAuditInventorySheet implements FromCollection, WithHeadings, WithTitle
{
    /** @var int */
    private $crawlId;

    /** @var string */
    private $source;

    /** @var array<string,string> */
    private $filters;

    /** @var string */
    private $sheetTitle;

    /** @var string|null */
    private $sort;

    /** @var string|null */
    private $dir;

    public function __construct(
        int $crawlId,
        string $source,
        array $filters = [],
        string $sheetTitle = 'Inventory',
        ?string $sort = null,
        ?string $dir = null
    ) {
        $this->crawlId = $crawlId;
        $this->source = $source;
        $this->filters = $filters;
        $this->sheetTitle = $sheetTitle !== '' ? $sheetTitle : 'Inventory';
        $this->sort = $sort;
        $this->dir = $dir;
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    public function headings(): array
    {
        if ($this->source === SiteAuditInventory::SOURCE_IMAGES) {
            return [
                'Image URL', 'Page URL', 'Status', 'OK', 'Size bytes', 'Width', 'Height',
                'Ext', 'HTTPS', 'External', 'Alt', 'Has alt', 'Loading', 'Content-Type', 'URL length', 'File',
            ];
        }
        if ($this->source === SiteAuditInventory::SOURCE_CANONICAL) {
            return ['URL', 'Canonical'];
        }

        return [
            'URL', 'Status', 'Title', 'Description', 'Keywords', 'Meta Robots',
            'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
            'H1 count', 'H2 count', 'Words', 'Internal links', 'External links', 'Images',
            'Canonical', 'Noindex', 'Click depth', 'Size bytes', 'Content type',
        ];
    }

    public function collection()
    {
        $crawl = SiteAuditCrawl::query()->findOrFail($this->crawlId);
        $out = collect();
        $page = 1;
        $perPage = 500;
        do {
            [$total, $rows] = SiteAuditInventory::paginate(
                $crawl,
                $this->source,
                $page,
                $perPage,
                $this->filters,
                $this->sort,
                $this->dir
            );
            foreach ($rows as $row) {
                $m = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                if ($this->source === SiteAuditInventory::SOURCE_IMAGES) {
                    $out->push([
                        $row->url,
                        $m['page_url'] ?? '',
                        $m['status'] ?? '',
                        isset($m['ok']) ? ($m['ok'] ? 'yes' : 'no') : '',
                        $m['size_bytes'] ?? '',
                        $m['width'] ?? '',
                        $m['height'] ?? '',
                        $m['ext'] ?? '',
                        ! empty($m['https']) ? 'yes' : 'no',
                        ! empty($m['external']) ? 'yes' : 'no',
                        $m['alt'] ?? '',
                        isset($m['has_alt']) ? ($m['has_alt'] ? 'yes' : 'no') : '',
                        $m['loading'] ?? '',
                        $m['content_type'] ?? '',
                        $m['url_len'] ?? '',
                        $m['file'] ?? '',
                    ]);
                } elseif ($this->source === SiteAuditInventory::SOURCE_CANONICAL) {
                    $out->push([$row->url, $m['canonical'] ?? '']);
                } else {
                    $headings = is_array($m['headings'] ?? null) ? $m['headings'] : [];
                    $hText = function (string $key) use ($headings, $m) {
                        $list = $headings[$key] ?? [];
                        if (is_array($list) && $list !== []) {
                            return implode(' | ', array_slice($list, 0, 5));
                        }
                        if ($key === 'h1' && ! empty($m['h1'])) {
                            return (string) $m['h1'];
                        }

                        return '';
                    };
                    $out->push([
                        $row->url,
                        $m['status_code'] ?? '',
                        $m['title'] ?? '',
                        $m['description'] ?? '',
                        $m['keywords'] ?? '',
                        $m['robots_meta'] ?? '',
                        $hText('h1'),
                        $hText('h2'),
                        $hText('h3'),
                        $hText('h4'),
                        $hText('h5'),
                        $hText('h6'),
                        $m['h1_count'] ?? '',
                        $m['h2_count'] ?? '',
                        $m['word_count'] ?? '',
                        $m['out_links'] ?? '',
                        $m['ext_links'] ?? '',
                        $m['img_count'] ?? '',
                        $m['canonical'] ?? '',
                        ! empty($m['noindex']) ? 1 : 0,
                        $m['click_depth'] ?? '',
                        $m['size_bytes'] ?? '',
                        $m['content_type'] ?? '',
                    ]);
                }
            }
            $page++;
        } while (($page - 1) * $perPage < $total);

        return $out;
    }
}
