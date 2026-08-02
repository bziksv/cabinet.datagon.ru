<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportProject;
use ZipArchive;

/**
 * Минимальный DOCX (OOXML) без PhpWord — текстовая выжимка отчёта.
 */
class SeoReportDocxBuilder
{
    /**
     * @return string absolute path to temp .docx
     */
    public function buildToTemp(SeoReportProject $project, SeoReport $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sr-docx-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create temp file for DOCX');
        }
        $docx = $path . '.docx';
        @unlink($path);

        $zip = new ZipArchive();
        if ($zip->open($docx, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot open DOCX zip');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('word/_rels/document.xml.rels', $this->docRels());
        $zip->addFromString('word/document.xml', $this->documentXml($project, $report));
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->close();

        return $docx;
    }

    private function documentXml(SeoReportProject $project, SeoReport $report): string
    {
        $snapshot = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $cover = is_array($snapshot['cover'] ?? null) ? $snapshot['cover'] : [];
        $paras = [];
        $paras[] = $this->p((string) ($cover['title'] ?? ('SEO-отчёт · ' . $project->domain)), true, 28);
        $paras[] = $this->p($project->domain . ' · ' . ($cover['period_label'] ?? ''));
        $paras[] = $this->p('');

        if ($report->summary_text) {
            $paras[] = $this->p('Резюме', true, 22);
            foreach (preg_split("/\r\n|\n|\r/", (string) $report->summary_text) ?: [] as $line) {
                if (trim($line) !== '') {
                    $paras[] = $this->p($line);
                }
            }
            $paras[] = $this->p('');
        }

        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : null;
        if ($traffic) {
            $paras[] = $this->p('Трафик', true, 22);
            $visits = $traffic['kpis']['visits']['value'] ?? null;
            $delta = $traffic['kpis']['visits']['delta_pct'] ?? null;
            $line = 'Визиты: ' . ($visits !== null ? number_format((float) $visits, 0, ',', ' ') : '—');
            if ($delta !== null) {
                $line .= ' (' . ($delta > 0 ? '+' : '') . number_format((float) $delta, 1, ',', ' ') . '%)';
            }
            $paras[] = $this->p($line);
            $paras[] = $this->p('');
        }

        $positions = is_array($snapshot['positions'] ?? null) ? $snapshot['positions'] : null;
        if ($positions) {
            $sum = is_array($positions['summary'] ?? null) ? $positions['summary'] : [];
            $paras[] = $this->p('Позиции', true, 22);
            $paras[] = $this->p('TOP-10: ' . ($sum['top10'] ?? '—') . ' · TOP-100: ' . ($sum['top100'] ?? '—'));
            $paras[] = $this->p('');
        }

        if ($report->work_done_text) {
            $paras[] = $this->p('Выполненные работы', true, 22);
            foreach (preg_split("/\r\n|\n|\r/", (string) $report->work_done_text) ?: [] as $line) {
                if (trim($line) !== '') {
                    $paras[] = $this->p($line);
                }
            }
            $paras[] = $this->p('');
        }

        if ($report->work_plan_text) {
            $paras[] = $this->p('План работ', true, 22);
            foreach (preg_split("/\r\n|\n|\r/", (string) $report->work_plan_text) ?: [] as $line) {
                if (trim($line) !== '') {
                    $paras[] = $this->p($line);
                }
            }
        }

        $body = implode('', $paras);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
            . '</w:body></w:document>';
    }

    private function p(string $text, bool $bold = false, int $sz = 20): string
    {
        $t = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $rPr = $bold
            ? '<w:rPr><w:b/><w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/></w:rPr>'
            : '<w:rPr><w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/></w:rPr>';

        return '<w:p><w:r>' . $rPr . '<w:t xml:space="preserve">' . $t . '</w:t></w:r></w:p>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private function docRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/><w:qFormat/>'
            . '</w:style></w:styles>';
    }
}
