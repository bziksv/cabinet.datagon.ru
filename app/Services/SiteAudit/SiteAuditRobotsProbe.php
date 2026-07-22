<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;

/**
 * Прогон robots.txt в начале краула: findings + rules в progress_json.
 * По умолчанию — живой /robots.txt; если задан virtual_robots — он вместо файла на сайте.
 */
class SiteAuditRobotsProbe
{
    public function run(SiteAuditCrawl $crawl, string $domain): void
    {
        $settings = array_merge(
            is_array(optional($crawl->project)->settings_json) ? $crawl->project->settings_json : [],
            is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : []
        );
        $virtual = trim((string) ($settings['virtual_robots'] ?? ''));

        $robots = new SiteAuditRobotsTxt();
        $analysis = $virtual !== ''
            ? $robots->analyzeBody($virtual, $domain)
            : $robots->analyze($domain);

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['robots'] = [
            'url' => $analysis['url'],
            'status_code' => $analysis['status_code'],
            'closed' => $analysis['closed'],
            'sitemaps' => $analysis['sitemaps'],
            'groups' => $analysis['groups'],
            'error' => $analysis['error'],
            'virtual' => ! empty($analysis['virtual']) || $virtual !== '',
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        // crawl-level findings (привязаны к URL robots.txt)
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', ['robots_txt_error', 'robots_txt_closed'])
            ->delete();

        $url = $analysis['url'];
        $hash = SiteAuditUrlNormalizer::hash($url);

        foreach ($analysis['findings'] as $f) {
            $cfg = config('site_audit.findings.' . $f['code'], []);
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => $f['code'],
                'severity' => $cfg['severity'] ?? 'warning',
                'url' => $url,
                'url_hash' => $hash,
                'meta_json' => $f['meta'] ?? null,
            ]);
        }
    }
}
