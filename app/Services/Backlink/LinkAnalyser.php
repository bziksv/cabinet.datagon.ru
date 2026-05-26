<?php

namespace App\Services\Backlink;

use App\Classes\SimpleHtmlDom\HtmlDocument;

/**
 * Разовая проверка ссылки на странице донора (логика модуля backlink).
 */
class LinkAnalyser
{
    /** @var string|null */
    protected $result;

    /** @var string|null */
    protected $error;

    /** @var mixed */
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

        $html = $this->fetchHtml($project->site_donor);

        if (!$html) {
            $this->error = 'The donor site does not exist';
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

    protected function fetchHtml(string $pageUrl)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $pageUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $html = curl_exec($curl);
        curl_close($curl);
        if (!$html) {
            return false;
        }

        return preg_replace('//i', '', $html);
    }

    protected function searchLink($html, $project): void
    {
        $document = new HtmlDocument();
        $document->load(mb_strtolower($html));

        $this->searchNoIndex($document, $project);

        if ($this->node === false) {
            $elem = $document->find('a[href="' . $project->link . '"]');

            if ($elem !== []) {
                foreach ($elem as $node) {
                    foreach ($node->_ as $text) {
                        if ($text === mb_strtolower($project->anchor)) {
                            $this->result = 'Link found, anchor matches.';
                            $this->node = $node;
                            break 2;
                        }
                    }

                    foreach ($node->children as $child) {
                        foreach ($child->_ as $text) {
                            if ($text === mb_strtolower($project->anchor)) {
                                $this->node = $child;
                                $this->result = 'Link found, anchor matches.';
                                break 3;
                            }
                        }
                    }
                }
            } else {
                $this->error = 'Link not found.';
            }
        }

        if ($project->nofollow && $this->node !== false) {
            $issetNofollow = false;
            foreach ($this->node->attr as $attribute => $value) {
                if ($attribute === 'rel' && $value === 'nofollow') {
                    $issetNofollow = true;
                    break;
                }
            }

            if ($issetNofollow) {
                $this->noFollow = 'Link have attribute nofollow.';
            } else {
                $this->noFollow = 'Link not have attribute nofollow.';
            }
        }
    }

    protected function searchNoIndex($document, $project): void
    {
        $elem = $document->find('noindex');

        if ($elem !== []) {
            foreach ($elem as $node) {
                $this->searchNoIndexLink($node->children[0], $project);
            }
        }

        if ($this->noIndex === null) {
            $this->noIndex = 'Link not placed in noindex.';
        }
    }

    protected function searchNoIndexLink($node, $project): void
    {
        if ($node->tag === 'a') {
            foreach ($node->_ as $text) {
                if ($text === mb_strtolower($project->anchor)) {
                    $this->noIndex = $project->noindex
                        ? 'Link found, anchor matches, link placed in noindex.'
                        : 'Link found, anchor matches.';
                    $this->node = $node;

                    return;
                }
            }
        } else {
            $this->searchNoIndexLink($node->children[0], $project);
        }
    }
}
