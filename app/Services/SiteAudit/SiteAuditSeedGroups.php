<?php

namespace App\Services\SiteAudit;

/**
 * Группировка seed-URL по хосту для pages-only краулов.
 */
class SiteAuditSeedGroups
{
    /**
     * @param string[] $rawUrls
     * @return array<string, string[]> domain => list of normalized absolute URLs
     */
    public static function groupByHost(array $rawUrls): array
    {
        $groups = [];
        foreach ($rawUrls as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $raw)) {
                $raw = 'https://' . ltrim($raw, '/');
            }
            $host = SiteAuditUrlNormalizer::hostOf($raw);
            if (! $host) {
                continue;
            }
            $domain = preg_replace('/^www\./', '', strtolower($host));
            $opts = SiteAuditUrlNormalizer::optionsFromSettings([
                'unify_www' => true,
                'force_https' => true,
                'strip_trailing_slash' => true,
            ], $domain);
            $norm = SiteAuditUrlNormalizer::normalize($raw, $domain, $opts);
            if (! $norm) {
                continue;
            }
            $groups[$domain][$norm] = true;
        }

        $out = [];
        foreach ($groups as $domain => $set) {
            $out[$domain] = array_keys($set);
        }

        return $out;
    }
}
