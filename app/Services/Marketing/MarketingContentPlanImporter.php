<?php

namespace App\Services\Marketing;

use App\MarketingContentItem;
use RuntimeException;

/**
 * Импорт контент-плана из titlo.ru/docs/seo-glossary-plan.md:
 *  - «## Контент-план блога» → статьи (type=article), ★ = топ-10;
 *  - «## CSV (для импорта)» → термины (type=term), «## Топ-20 терминов» → is_top.
 *
 * Идемпотентно: ключ (type, title). У существующих записей заполняются только пустые поля —
 * правки админа (ТЗ, статус, LSI) не перетираются.
 */
class MarketingContentPlanImporter
{
    /**
     * @return array{articles_created:int,articles_updated:int,terms_created:int,terms_updated:int}
     */
    public function importFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Content plan file not found: ' . $path);
        }

        return $this->importMarkdown((string) file_get_contents($path));
    }

    /**
     * @return array{articles_created:int,articles_updated:int,terms_created:int,terms_updated:int}
     */
    public function importMarkdown(string $markdown): array
    {
        $stats = ['articles_created' => 0, 'articles_updated' => 0, 'terms_created' => 0, 'terms_updated' => 0];

        foreach ($this->parseArticles($markdown) as $row) {
            $r = $this->upsert(MarketingContentItem::TYPE_ARTICLE, $row);
            $stats[$r === 'created' ? 'articles_created' : 'articles_updated'] += $r === null ? 0 : 1;
        }

        foreach ($this->parseTerms($markdown) as $row) {
            $r = $this->upsert(MarketingContentItem::TYPE_TERM, $row);
            $stats[$r === 'created' ? 'terms_created' : 'terms_updated'] += $r === null ? 0 : 1;
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseArticles(string $markdown): array
    {
        $section = $this->section($markdown, '## Контент-план блога', '## Полный список терминов');
        $rows = [];
        $category = null;

        foreach (preg_split('/\r\n|\r|\n/u', $section) as $line) {
            if (preg_match('/^###\s+(.+)$/u', $line, $m)) {
                $category = trim($m[1]);
                continue;
            }
            $cells = $this->tableCells($line);
            if ($cells === null || count($cells) < 7 || !ctype_digit($cells[0])) {
                continue;
            }

            $title = trim($cells[1]);
            $isTop = mb_strpos($title, '★') !== false;
            $title = trim(str_replace('★', '', $title));

            $rows[] = [
                'plan_number' => (int) $cells[0],
                'title' => $title,
                'category' => $category,
                'main_query' => $this->nullIfDash($cells[2]),
                'intent' => $this->nullIfDash($cells[3]),
                'modules' => $this->nullIfDash($cells[4]),
                'format' => $this->nullIfDash($cells[5]),
                'priority' => $this->priority($cells[6]),
                'is_top' => $isTop,
                'sort' => (int) $cells[0],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseTerms(string $markdown): array
    {
        if (!preg_match('/## CSV[^\n]*\n+```csv\s*\n(.*?)\n```/su', $markdown, $m)) {
            return [];
        }

        $top = $this->topTerms($markdown);
        $rows = [];
        $n = 0;

        foreach (preg_split('/\r\n|\r|\n/u', trim($m[1])) as $i => $line) {
            if ($i === 0 && mb_stripos($line, 'term;') === 0) {
                continue;
            }
            if (trim($line) === '') {
                continue;
            }
            $cols = array_pad(explode(';', $line), 5, '');
            $title = trim($cols[0]);
            if ($title === '') {
                continue;
            }
            $n++;

            $rows[] = [
                'plan_number' => $n,
                'title' => $title,
                'aliases' => $this->nullIfDash($cols[1]),
                'category' => $this->nullIfDash($cols[2]),
                'priority' => $this->priority($cols[3]),
                'modules' => $this->nullIfDash($cols[4]),
                'is_top' => in_array(mb_strtolower($title), $top, true),
                'sort' => $n,
            ];
        }

        return $rows;
    }

    /** @return array<int, string> lower-case названия топ-20 терминов */
    private function topTerms(string $markdown): array
    {
        $section = $this->section($markdown, '## Топ-20 терминов', '## ');
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/u', $section) as $line) {
            $cells = $this->tableCells($line);
            if ($cells !== null && count($cells) >= 2 && ctype_digit($cells[0])) {
                $out[] = mb_strtolower(trim($cells[1]));
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return string|null created|updated|null (без изменений)
     */
    private function upsert(string $type, array $row): ?string
    {
        $item = MarketingContentItem::query()->where('type', $type)->where('title', $row['title'])->first();

        if ($item === null) {
            MarketingContentItem::query()->create(array_merge($row, [
                'type' => $type,
                'status' => 'idea',
            ]));

            return 'created';
        }

        $dirty = false;
        foreach ($row as $key => $value) {
            if ($key === 'is_top') {
                if ($value && !$item->is_top) {
                    $item->is_top = true;
                    $dirty = true;
                }
                continue;
            }
            $current = $item->getAttribute($key);
            if (($current === null || $current === '') && $value !== null && $value !== '') {
                $item->setAttribute($key, $value);
                $dirty = true;
            }
        }

        if ($dirty) {
            $item->save();

            return 'updated';
        }

        return null;
    }

    /** Текст между заголовком $startHeading и следующим заголовком «## …» ($until — его начало). Байтовые смещения. */
    private function section(string $markdown, string $startHeading, string $until): string
    {
        $start = strpos($markdown, $startHeading);
        if ($start === false) {
            return '';
        }
        $from = $start + strlen($startHeading);
        $rest = substr($markdown, $from);

        if ($until === '## ') {
            return preg_match('/\n## /u', $rest, $m, PREG_OFFSET_CAPTURE) ? substr($rest, 0, $m[0][1]) : $rest;
        }

        $end = strpos($rest, "\n" . $until);

        return $end === false ? $rest : substr($rest, 0, $end);
    }

    /** @return array<int, string>|null */
    private function tableCells(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || $line[0] !== '|') {
            return null;
        }

        return array_map('trim', explode('|', trim($line, '|')));
    }

    private function nullIfDash(string $value): ?string
    {
        $value = trim($value);

        return ($value === '' || $value === '—' || $value === '-') ? null : $value;
    }

    private function priority(string $value): string
    {
        $value = strtoupper(trim($value));

        return in_array($value, (array) config('cabinet-marketing.priorities', []), true) ? $value : 'P2';
    }
}
