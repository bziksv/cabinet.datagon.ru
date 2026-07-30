<?php

namespace App\Services\Backlink;

use App\Support\BacklinkHtmlMatcher;

/**
 * Разовая проверка ссылки на странице донора (логика модуля backlink).
 */
class LinkAnalyser
{
    /** @var string|null */
    protected $result;

    /** @var string|null */
    protected $error;

    /** @var bool */
    protected $node = false;

    /** @var string|null */
    protected $noIndex;

    /** @var string|null */
    protected $noFollow;

    /**
     * @return array{
     *   ok: bool,
     *   status: string,
     *   phrases: string[],
     *   error_key: string|null
     * }
     */
    public function analyse(string $donorUrl, string $targetLink, string $anchor, bool $checkNofollow, bool $checkNoindex): array
    {
        $this->reset();

        $project = (object) [
            'site_donor' => $donorUrl,
            'link' => $targetLink,
            'anchor' => $anchor,
            'nofollow' => $checkNofollow ? 1 : 0,
            'noindex' => $checkNoindex ? 1 : 0,
        ];

        $html = BacklinkHtmlMatcher::fetchHtml((string) $project->site_donor);

        if (! $html) {
            $this->error = 'The donor page does not exist';
        } else {
            $this->searchLink($html, $project);
        }

        if ($this->error !== null) {
            $phrases = [trim($this->error, '.')];

            return [
                'ok' => false,
                'status' => $this->error,
                'phrases' => $phrases,
                'error_key' => $phrases[0],
            ];
        }

        $status = preg_replace('/\s+/u', ' ', trim("$this->result $this->noIndex $this->noFollow"));
        $phrases = array_values(array_filter(array_map('trim', preg_split('/\./', $status))));

        return [
            'ok' => true,
            'status' => $status,
            'phrases' => $phrases,
            'error_key' => null,
        ];
    }

    protected function reset(): void
    {
        $this->result = null;
        $this->error = null;
        $this->node = false;
        $this->noIndex = null;
        $this->noFollow = null;
    }

    protected function searchLink($html, $project): void
    {
        $hit = BacklinkHtmlMatcher::find(
            (string) $html,
            (string) $project->link,
            (string) $project->anchor
        );

        if (! ($hit['found'] ?? false)) {
            $this->error = 'Link not found.';

            return;
        }

        $this->node = true;
        $anchorless = ! empty($hit['anchorless']);
        $this->result = $anchorless
            ? 'Link found (anchorless).'
            : 'Link found, anchor matches.';

        // Контроль=да: проверяем отсутствие noindex/nofollow. Наличие — предупреждение.
        if ($project->noindex) {
            $this->noIndex = ! empty($hit['in_comment_noindex'])
                ? 'Link placed in noindex.'
                : 'Link not placed in noindex.';
        }

        if ($project->nofollow) {
            $this->noFollow = ! empty($hit['has_nofollow'])
                ? 'Link have attribute nofollow.'
                : 'Link not have attribute nofollow.';
        }
    }
}
