<?php

namespace App\Services\SiteAudit;

/**
 * Lite: исходящие ссылки на партнёрские/аффилиат-сети.
 */
class SiteAuditAffiliateDetector
{
    /**
     * @param string[] $externalUrls
     * @return array{count:int,samples:list<array{url:string,network:string}>}|null
     */
    public static function fromExternalUrls(array $externalUrls): ?array
    {
        $networks = config('site_audit.affiliate_networks', []);
        if (! is_array($networks) || $networks === [] || $externalUrls === []) {
            return null;
        }

        $samples = [];
        foreach ($externalUrls as $url) {
            $url = (string) $url;
            if ($url === '') {
                continue;
            }
            $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
            $hay = $host . ' ' . strtolower($url);
            foreach ($networks as $net => $needles) {
                if (! is_array($needles)) {
                    continue;
                }
                foreach ($needles as $needle) {
                    $needle = strtolower(trim((string) $needle));
                    if ($needle === '') {
                        continue;
                    }
                    if (strpos($hay, $needle) !== false) {
                        $samples[] = [
                            'url' => mb_substr($url, 0, 200),
                            'network' => (string) $net,
                        ];
                        break 2;
                    }
                }
            }
            if (count($samples) >= 12) {
                break;
            }
        }

        if ($samples === []) {
            return null;
        }

        return [
            'count' => count($samples),
            'samples' => array_slice($samples, 0, 8),
        ];
    }
}
