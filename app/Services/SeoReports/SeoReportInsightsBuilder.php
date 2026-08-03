<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReportMetrikaLabels;

class SeoReportInsightsBuilder
{
    /**
     * @param array<string, mixed> $snapshot
     * @return list<string>
     */
    public function bullets(array $snapshot): array
    {
        $out = [];
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : null;
        $positions = is_array($snapshot['positions'] ?? null) ? $snapshot['positions'] : null;

        if ($traffic) {
            $visits = $traffic['kpis']['visits'] ?? null;
            if (is_array($visits) && $visits['value'] !== null) {
                $line = 'Визиты за период: ' . $this->fmtInt($visits['value']);
                if ($visits['delta_pct'] !== null) {
                    $line .= ' (' . $this->fmtDelta((float) $visits['delta_pct']) . ' к прошлому периоду)';
                }
                $out[] = $line;
            }

            $users = $traffic['kpis']['users'] ?? null;
            if (is_array($users) && $users['delta_pct'] !== null) {
                $out[] = 'Пользователи: ' . $this->fmtDelta((float) $users['delta_pct']) . ' к прошлому периоду';
            }

            $channels = is_array($traffic['channels'] ?? null) ? $traffic['channels'] : [];
            if ($channels !== []) {
                $top = $channels[0];
                $out[] = 'Главный канал: ' . SeoReportMetrikaLabels::label($top['name'] ?? '', $top['id'] ?? null)
                    . ' (' . $this->fmtInt($top['visits'] ?? 0) . ' визитов)';
            }

            $search = is_array($traffic['search'] ?? null) ? $traffic['search'] : null;
            if (is_array($search) && isset($search['kpis']['visits']['value'])) {
                $sv = $search['kpis']['visits'];
                $line = 'Поисковый трафик: ' . $this->fmtInt($sv['value']);
                if ($sv['delta_pct'] !== null) {
                    $line .= ' (' . $this->fmtDelta((float) $sv['delta_pct']) . ')';
                }
                $out[] = $line;
            }
        }

        foreach ($this->anomalies($snapshot) as $a) {
            $out[] = sprintf(
                'Аномалия %s: %s до %s',
                $a['date'],
                $a['direction'] === 'up' ? 'рост' : 'спад',
                $this->fmtInt($a['value'])
            );
        }

        if ($positions) {
            $dyn = is_array($positions['dynamics'] ?? null) ? $positions['dynamics'] : [];
            if (!empty($dyn['pairs'])) {
                $out[] = sprintf(
                    'Позиции: ↑%d / →%d / ↓%d запросов',
                    (int) ($dyn['improved'] ?? 0),
                    (int) ($dyn['unchanged'] ?? 0),
                    (int) ($dyn['worsened'] ?? 0)
                );
            }
            $sum = is_array($positions['summary'] ?? null) ? $positions['summary'] : [];
            if (isset($sum['top10']) && $sum['top10'] !== null && $sum['top10'] !== '') {
                $out[] = 'В TOP-10 сейчас: ' . $sum['top10']
                    . (isset($sum['diff_top10']) && $sum['diff_top10'] !== null && $sum['diff_top10'] !== ''
                        ? ' (' . $sum['diff_top10'] . ')'
                        : '');
            }
        }

        $conversions = is_array($snapshot['conversions'] ?? null) ? $snapshot['conversions'] : null;
        if ($conversions && !empty($conversions['goals'][0])) {
            $g = $conversions['goals'][0];
            $line = 'Конверсии «' . ($g['name'] ?? 'цель') . '»: '
                . $this->fmtInt($g['reaches']['value'] ?? 0);
            if (isset($g['reaches']['delta_pct']) && $g['reaches']['delta_pct'] !== null) {
                $line .= ' (' . $this->fmtDelta((float) $g['reaches']['delta_pct']) . ')';
            }
            $out[] = $line;
        }

        foreach ($this->landingTrafficWithoutConversion($snapshot) as $line) {
            $out[] = $line;
        }

        if ($out === []) {
            $out[] = 'Данных для авто-выводов пока мало — заполните тексты работ вручную.';
        }

        return array_slice($out, 0, 8);
    }

    /**
     * Топ посадочные: рост трафика при слабой/падающей конверсии.
     *
     * @param array<string, mixed> $snapshot
     * @return list<string>
     */
    public function landingTrafficWithoutConversion(array $snapshot): array
    {
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : [];
        $landings = is_array($traffic['landings'] ?? null) ? $traffic['landings'] : [];
        $conversions = is_array($snapshot['conversions'] ?? null) ? $snapshot['conversions'] : [];
        $goalDelta = null;
        if (!empty($conversions['goals'][0]['reaches']['delta_pct'])) {
            $goalDelta = (float) $conversions['goals'][0]['reaches']['delta_pct'];
        }
        $visitsDelta = isset($traffic['kpis']['visits']['delta_pct'])
            ? (float) $traffic['kpis']['visits']['delta_pct']
            : null;

        $out = [];
        if ($visitsDelta !== null && $visitsDelta >= 10 && $goalDelta !== null && $goalDelta <= 0) {
            $out[] = 'Трафик вырос (' . $this->fmtDelta($visitsDelta)
                . '), конверсии не растут — проверить посадочные и цели.';
        }

        $grown = [];
        foreach ($landings as $row) {
            $delta = $row['visits_delta_pct'] ?? null;
            if ($delta === null || (float) $delta < 25) {
                continue;
            }
            $grown[] = (string) ($row['name'] ?? '');
            if (count($grown) >= 2) {
                break;
            }
        }
        if ($grown !== [] && ($goalDelta === null || $goalDelta < 5)) {
            $out[] = 'Рост трафика на посадочных без явного роста конверсии: '
                . implode(', ', $grown);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function trafficComment(array $snapshot): ?string
    {
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : null;
        if (!$traffic) {
            return null;
        }
        $visits = $traffic['kpis']['visits']['delta_pct'] ?? null;
        $bounce = $traffic['kpis']['bounce_rate']['delta_pct'] ?? null;
        $parts = [];
        if ($visits !== null) {
            $parts[] = ((float) $visits >= 0 ? 'Трафик вырос' : 'Трафик снизился')
                . ' на ' . abs((float) $visits) . '%';
        }
        if ($bounce !== null) {
            $parts[] = ((float) $bounce <= 0 ? 'отказы улучшились' : 'отказы выросли')
                . ' (' . $this->fmtDelta((float) $bounce) . ')';
        }

        return $parts !== [] ? implode('; ', $parts) . '.' : null;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return list<array{date:string,value:float,z:float,direction:string}>
     */
    public function anomalies(array $snapshot): array
    {
        $series = [];
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : [];
        if (!empty($traffic['series_users']) && is_array($traffic['series_users'])) {
            $series = $traffic['series_users'];
        } elseif (!empty($traffic['search']['series_visits']) && is_array($traffic['search']['series_visits'])) {
            $series = $traffic['search']['series_visits'];
        }
        if (count($series) < 7) {
            return [];
        }

        $values = array_map('floatval', array_values($series));
        $mean = array_sum($values) / count($values);
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) * ($v - $mean);
        }
        $std = sqrt($variance / max(1, count($values)));
        if ($std < 1) {
            return [];
        }

        $dates = array_keys($series);
        $out = [];
        foreach ($values as $i => $v) {
            $z = ($v - $mean) / $std;
            if (abs($z) < 2.2) {
                continue;
            }
            $out[] = [
                'date' => (string) $dates[$i],
                'value' => $v,
                'z' => round($z, 2),
                'direction' => $z > 0 ? 'up' : 'down',
            ];
        }

        usort($out, static function ($a, $b) {
            return abs($b['z']) <=> abs($a['z']);
        });

        return array_slice($out, 0, 3);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return list<array{priority:string,text:string}>
     */
    public function recommendations(array $snapshot): array
    {
        $out = [];
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : [];
        $positions = is_array($snapshot['positions'] ?? null) ? $snapshot['positions'] : [];
        $visitsDelta = $traffic['kpis']['visits']['delta_pct'] ?? null;
        $bounceDelta = $traffic['kpis']['bounce_rate']['delta_pct'] ?? null;
        $dyn = is_array($positions['dynamics'] ?? null) ? $positions['dynamics'] : [];
        $worsened = (int) ($dyn['worsened'] ?? 0);
        $improved = (int) ($dyn['improved'] ?? 0);
        $risk = is_array($positions['risk'] ?? null) ? $positions['risk'] : [];
        $quick = is_array($positions['quick_wins'] ?? null) ? $positions['quick_wins'] : [];

        if ($visitsDelta !== null && (float) $visitsDelta <= -15) {
            $out[] = [
                'priority' => 'P1',
                'text' => 'Трафик упал более чем на 15% — проверить индексацию, сниппеты и крупные посадочные.',
            ];
        }
        if ($worsened > $improved && $worsened >= 5) {
            $out[] = [
                'priority' => 'P1',
                'text' => 'Позиции: больше падений (' . $worsened . '), чем роста — усилить упавшие запросы и их URL.',
            ];
        }
        if ($risk !== []) {
            $q = $risk[0]['query'] ?? '';
            $out[] = [
                'priority' => 'P1',
                'text' => 'Risk: сильное падение «' . $q . '» — приоритетно разобрать посадочную и выдачу.',
            ];
        }
        if ($bounceDelta !== null && (float) $bounceDelta >= 10) {
            $out[] = [
                'priority' => 'P2',
                'text' => 'Отказы выросли — проверить скорость, мобильную версию и соответствие интенту.',
            ];
        }
        if ($quick !== []) {
            $out[] = [
                'priority' => 'P2',
                'text' => 'Быстрые победы: ' . count($quick)
                    . ' запросов на 8–20 позициях — усилить title/контент для роста CTR.',
            ];
        }
        if ($improved > 0 && $visitsDelta !== null && (float) $visitsDelta < 5) {
            $out[] = [
                'priority' => 'P3',
                'text' => 'Позиции растут, трафик почти не изменился — проверить сниппеты и расширение семантики.',
            ];
        }
        foreach ($this->landingTrafficWithoutConversion($snapshot) as $text) {
            $out[] = [
                'priority' => 'P2',
                'text' => $text,
            ];
        }
        $direct = is_array($snapshot['direct'] ?? null) ? $snapshot['direct'] : [];
        foreach (is_array($direct['fix'] ?? null) ? $direct['fix'] : [] as $hint) {
            $out[] = [
                'priority' => 'P2',
                'text' => '[Реклама] ' . $hint,
            ];
        }
        if ($out === []) {
            $out[] = [
                'priority' => 'P3',
                'text' => 'Критичных аномалий нет — продолжайте план работ и контроль TOP-10.',
            ];
        }

        return array_slice($out, 0, 6);
    }

    /**
     * Optional Russian executive narrative (rule-based; LLM can replace later).
     *
     * @param array<string, mixed> $snapshot
     */
    public function aiNarrative(array $snapshot): string
    {
        $parts = [];
        $cover = is_array($snapshot['cover'] ?? null) ? $snapshot['cover'] : [];
        $domain = (string) ($cover['domain'] ?? $cover['title'] ?? 'сайт');
        $period = (string) ($cover['period_label'] ?? 'отчётный период');
        $parts[] = 'Краткое резюме по проекту «' . $domain . '» за ' . $period . '.';

        $score = is_array($snapshot['scorecard'] ?? null) ? $snapshot['scorecard'] : [];
        if ($score !== []) {
            $tones = [];
            foreach ($score as $row) {
                $tones[] = ($row['label'] ?? '') . ': ' . ($row['tone'] ?? '—');
            }
            $parts[] = 'Светофор KPI — ' . implode('; ', array_slice($tones, 0, 5)) . '.';
        }

        $bullets = $this->bullets($snapshot);
        if ($bullets !== []) {
            $parts[] = 'Главные цифры: ' . implode('; ', array_slice($bullets, 0, 4)) . '.';
        }

        $anomalies = $this->anomalies($snapshot);
        if ($anomalies !== []) {
            $a = $anomalies[0];
            $parts[] = sprintf(
                'Зафиксирована аномалия %s (%s до %s) — стоит отдельно пояснить клиенту причину.',
                $a['date'],
                $a['direction'] === 'up' ? 'рост' : 'спад',
                $this->fmtInt($a['value'])
            );
        }

        $recs = $this->recommendations($snapshot);
        $p1 = [];
        foreach ($recs as $r) {
            if (($r['priority'] ?? '') === 'P1') {
                $p1[] = $r['text'] ?? '';
            }
        }
        if ($p1 !== []) {
            $parts[] = 'Приоритет на следующий месяц: ' . implode(' ', array_slice($p1, 0, 2));
        } elseif ($recs !== []) {
            $parts[] = 'Рекомендации: ' . ($recs[0]['text'] ?? '');
        }

        $parts[] = 'Резюме сформировано автоматически (режим AI-резюме). При необходимости отредактируйте текст перед публикацией.';

        return implode("\n\n", array_filter($parts));
    }

    private function fmtInt($value): string
    {
        return number_format((float) $value, 0, ',', ' ');
    }

    private function fmtDelta(float $delta): string
    {
        $sign = $delta > 0 ? '+' : '';

        return $sign . number_format($delta, 1, ',', ' ') . '%';
    }
}
