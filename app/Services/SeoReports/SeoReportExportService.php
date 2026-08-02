<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use ZipArchive;

class SeoReportExportService
{
    /**
     * @return \Barryvdh\DomPDF\PDF
     */
    public function makePdf(SeoReportProject $project, SeoReport $report)
    {
        $snapshot = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $states = is_array($report->section_states) ? $report->section_states : [];
        $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
        $catalog = SeoReportSectionRegistry::all();
        $sections = [];
        foreach (SeoReportSectionRegistry::orderedKeys($settings) as $key) {
            $meta = $catalog[$key] ?? null;
            if (!$meta) {
                continue;
            }
            $state = isset($states[$key]) && is_array($states[$key]) ? $states[$key] : [];
            $enabled = array_key_exists('enabled', $state) ? (bool) $state['enabled'] : false;
            $sourceStatus = (string) ($state['source_status'] ?? SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED);
            if ($key !== 'cover' && !SeoReportSectionRegistry::visibleForClient($enabled, $sourceStatus)) {
                continue;
            }
            if ($key !== 'cover' && !$enabled) {
                continue;
            }
            $sections[] = [
                'key' => $key,
                'title' => $meta['title'],
                'enabled' => true,
                'client_visible' => true,
            ];
        }

        return \PDF::loadView('pages.seo-reports-pdf', [
            'project' => $project,
            'report' => $report,
            'snapshot' => $snapshot,
            'sections' => $sections,
            'generatedAt' => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'portrait');
    }

    public function pdfFilename(SeoReportProject $project, SeoReport $report): string
    {
        return 'seo-report-' . preg_replace('/[^a-z0-9\-\.]+/i', '-', $project->domain)
            . '-' . optional($report->period_to)->format('Y-m') . '.pdf';
    }

    public function packFilename(SeoReportProject $project, SeoReport $report): string
    {
        return 'seo-report-pack-' . preg_replace('/[^a-z0-9\-\.]+/i', '-', $project->domain)
            . '-' . optional($report->period_to)->format('Y-m') . '.zip';
    }

    /**
     * @return array{path:string,filename:string}
     */
    public function buildPack(SeoReportProject $project, SeoReport $report): array
    {
        $tmpDir = storage_path('app/tmp/seo-reports');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = $tmpDir . '/pack-' . $report->id . '-' . time() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create zip');
        }

        $pdf = $this->makePdf($project, $report);
        $zip->addFromString($this->pdfFilename($project, $report), $pdf->output());

        $snapshot = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : [];
        $positions = is_array($snapshot['positions'] ?? null) ? $snapshot['positions'] : [];
        $conversions = is_array($snapshot['conversions'] ?? null) ? $snapshot['conversions'] : [];

        if (!empty($traffic['channels'])) {
            $zip->addFromString('channels.csv', $this->csvFromRows(
                ['channel', 'visits', 'users', 'bounce_rate', 'page_depth', 'avg_visit_duration'],
                $traffic['channels'],
                static function ($row) {
                    return [
                        $row['name'] ?? '',
                        $row['visits'] ?? '',
                        $row['users'] ?? '',
                        $row['bounce_rate'] ?? '',
                        $row['page_depth'] ?? '',
                        $row['avg_visit_duration'] ?? '',
                    ];
                }
            ));
        }

        if (!empty($traffic['landings'])) {
            $zip->addFromString('landings.csv', $this->csvFromRows(
                ['url', 'visits', 'visits_prev', 'visits_delta_pct'],
                $traffic['landings'],
                static function ($row) {
                    return [
                        $row['name'] ?? '',
                        $row['visits'] ?? '',
                        $row['visits_prev'] ?? '',
                        $row['visits_delta_pct'] ?? '',
                    ];
                }
            ));
        }

        if (!empty($traffic['search']['engines'])) {
            $zip->addFromString('search-engines.csv', $this->csvFromRows(
                ['engine', 'visits', 'bounce_rate', 'visits_delta_pct'],
                $traffic['search']['engines'],
                static function ($row) {
                    return [
                        $row['name'] ?? '',
                        $row['visits'] ?? '',
                        $row['bounce_rate'] ?? '',
                        $row['visits_delta_pct'] ?? '',
                    ];
                }
            ));
        }

        $phrases = array_merge(
            $positions['phrases']['improved'] ?? [],
            $positions['phrases']['worsened'] ?? []
        );
        if ($phrases !== []) {
            $zip->addFromString('positions-phrases.csv', $this->csvFromRows(
                ['query', 'engine', 'pos_from', 'pos_to', 'delta', 'url'],
                $phrases,
                static function ($row) {
                    return [
                        $row['query'] ?? '',
                        $row['engine'] ?? '',
                        $row['pos_from'] ?? '',
                        $row['pos_to'] ?? '',
                        $row['delta'] ?? '',
                        $row['url'] ?? '',
                    ];
                }
            ));
        }

        if (!empty($conversions['goals'])) {
            $zip->addFromString('conversions.csv', $this->csvFromRows(
                ['goal', 'reaches', 'reaches_prev', 'conversion_rate', 'delta_pct', 'cost_per_conversion'],
                $conversions['goals'],
                static function ($row) {
                    return [
                        $row['name'] ?? '',
                        $row['reaches']['value'] ?? '',
                        $row['reaches']['prev'] ?? '',
                        $row['conversion_rate']['value'] ?? '',
                        $row['reaches']['delta_pct'] ?? '',
                        $row['cost_per_conversion'] ?? '',
                    ];
                }
            ));
        }

        $direct = is_array($snapshot['direct'] ?? null) ? $snapshot['direct'] : [];
        if (!empty($direct['landings'])) {
            $zip->addFromString('direct-landings.csv', $this->csvFromRows(
                ['url', 'visits', 'bounce_rate'],
                $direct['landings'],
                static function ($row) {
                    return [
                        $row['name'] ?? '',
                        $row['visits'] ?? '',
                        $row['bounce_rate'] ?? '',
                    ];
                }
            ));
        }

        $ecommerce = is_array($snapshot['ecommerce'] ?? null) ? $snapshot['ecommerce'] : [];
        if (!empty($ecommerce['products'])) {
            $zip->addFromString('ecommerce-products.csv', $this->csvFromRows(
                ['product', 'purchases', 'revenue'],
                $ecommerce['products'],
                static function ($row) {
                    return [
                        $row['name'] ?? '',
                        $row['purchases'] ?? '',
                        $row['revenue'] ?? '',
                    ];
                }
            ));
        }

        try {
            $docxPath = (new SeoReportDocxBuilder())->buildToTemp($project, $report);
            $zip->addFile($docxPath, $this->docxFilename($project, $report));
        } catch (\Throwable $e) {
            // pack without docx
        }

        $zip->close();
        if (!empty($docxPath) && is_file($docxPath)) {
            @unlink($docxPath);
        }

        return [
            'path' => $zipPath,
            'filename' => $this->packFilename($project, $report),
        ];
    }

    public function makeDocx(SeoReportProject $project, SeoReport $report): string
    {
        return (new SeoReportDocxBuilder())->buildToTemp($project, $report);
    }

    public function docxFilename(SeoReportProject $project, SeoReport $report): string
    {
        return 'seo-report-' . preg_replace('/[^a-z0-9\-\.]+/i', '-', $project->domain)
            . '-' . optional($report->period_to)->format('Y-m') . '.docx';
    }

    /**
     * @param list<string> $headers
     * @param list<array<string,mixed>> $rows
     * @param callable(array):list<scalar> $mapper
     */
    private function csvFromRows(array $headers, array $rows, callable $mapper): string
    {
        $fh = fopen('php://temp', 'r+');
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fh, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($fh, $mapper($row), ';');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv !== false ? $csv : '';
    }
}
