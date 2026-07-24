<?php

namespace App\Services\Backlink;

use App\LinkTracking;
use App\ProjectTracking;
use App\Support\BacklinkHtmlMatcher;

/**
 * Проверка одной ссылки. «Проблемная» = донор недоступен или ссылка/анкор не найдены.
 * Нарушения nofollow/noindex — предупреждения в статусе, в счётчик не входят.
 */
class BacklinkChecker
{
    /** @var string|null */
    protected $result;

    /** @var string|null */
    protected $error;

    /** @var string|null */
    protected $noIndex;

    /** @var string|null */
    protected $noFollow;

    /**
     * @return bool broken (жёсткая проблема)
     */
    public function checkAndSave(LinkTracking $link): bool
    {
        $this->result = null;
        $this->error = null;
        $this->noIndex = null;
        $this->noFollow = null;

        $html = BacklinkHtmlMatcher::fetchHtml((string) $link->site_donor);

        if (! $html) {
            $this->error = 'The donor site does not exist';
            $this->persist($link, true);

            return true;
        }

        $hit = BacklinkHtmlMatcher::find(
            (string) $html,
            (string) $link->link,
            (string) $link->anchor
        );

        if (! ($hit['found'] ?? false)) {
            $this->error = 'Link not found.';
            $this->persist($link, true);

            return true;
        }

        $anchorless = ! empty($hit['anchorless']);
        $this->result = $anchorless
            ? 'Link found (anchorless).'
            : 'Link found, anchor matches.';

        if (! empty($hit['in_comment_noindex'])) {
            if ($link->noindex) {
                $this->noIndex = $anchorless
                    ? 'Link found (anchorless), link placed in noindex.'
                    : 'Link found, anchor matches, link placed in noindex.';
            } else {
                $this->noIndex = $this->result;
            }
        } else {
            // Контроль noindex=да: предупреждение, но не «проблемная» для счётчика.
            $this->noIndex = 'Link not placed in noindex.';
        }

        if ($link->nofollow) {
            $this->noFollow = ! empty($hit['has_nofollow'])
                ? 'Link have attribute nofollow.'
                : 'Link not have attribute nofollow.';
        }

        $this->persist($link, false);

        return false;
    }

    /**
     * Пересчитать total_broken_link проекта по факту broken=1.
     */
    public static function recountProject(int $projectTrackingId): int
    {
        $count = (int) LinkTracking::query()
            ->where('project_tracking_id', $projectTrackingId)
            ->where('broken', 1)
            ->count();

        ProjectTracking::query()
            ->where('id', $projectTrackingId)
            ->update(['total_broken_link' => $count]);

        return $count;
    }

    /**
     * Выровнять флаг broken по тексту статуса (после старых cron-прогонов).
     * Успех («ссылка найдена») важнее хвоста «донор не существует» в склеенном статусе.
     */
    public static function repairBrokenFlags(?int $projectTrackingId = null): int
    {
        $query = LinkTracking::query()->orderBy('id');
        if ($projectTrackingId !== null) {
            $query->where('project_tracking_id', $projectTrackingId);
        }

        $fixed = 0;
        $projectIds = [];

        $query->chunkById(200, function ($links) use (&$fixed, &$projectIds) {
            foreach ($links as $link) {
                $shouldBeBroken = self::statusMeansHardBroken((string) $link->status);
                $isBroken = (int) $link->broken === 1;
                if ($shouldBeBroken !== $isBroken) {
                    $link->broken = $shouldBeBroken ? 1 : 0;
                    $link->save();
                    $fixed++;
                }
                $projectIds[(int) $link->project_tracking_id] = true;
            }
        });

        foreach (array_keys($projectIds) as $pid) {
            self::recountProject((int) $pid);
        }

        return $fixed;
    }

    public static function statusMeansHardBroken(string $status): bool
    {
        $s = mb_strtolower(trim($status));
        if ($s === '' || $s === '0' || $s === '1') {
            return false;
        }

        // Склеенные статусы cron: «найдена … Сайт донор не существует» → это успех + мусор.
        if (
            mb_strpos($s, 'link found') !== false
            || mb_strpos($s, 'ссылка найдена') !== false
            || mb_strpos($s, 'anchorless') !== false
            || mb_strpos($s, 'безанкорн') !== false
        ) {
            return false;
        }

        return mb_strpos($s, 'link not found') !== false
            || mb_strpos($s, 'does not exist') !== false
            || mb_strpos($s, 'не найдена') !== false
            || mb_strpos($s, 'не существует') !== false
            || mb_strpos($s, 'anchor does not match') !== false
            || mb_strpos($s, 'анкор не совпадает') !== false;
    }

    protected function persist(LinkTracking $target, bool $broken): void
    {
        if (isset($this->error)) {
            $target->status = $this->error;
        } else {
            $target->status = preg_replace('/\s+/u', ' ', trim("$this->result $this->noIndex $this->noFollow"));
        }

        $target->last_check = date('Y-m-d H:i:s');
        $target->broken = $broken ? 1 : 0;
        $target->save();

        self::recountProject((int) $target->project_tracking_id);
    }
}
