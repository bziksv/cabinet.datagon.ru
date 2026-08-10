<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;

/**
 * Опциональные пробы (PSI / SERP…): отличить «0 находок» от «проверку не запускали».
 */
class SiteAuditProbeStatus
{
    /**
     * @return array<string, array{codes:string[],title:string,progress_key:string,config_key:string}>
     */
    public static function catalog(): array
    {
        return [
            'psi' => [
                'codes' => ['psi_mobile', 'psi_desktop'],
                'title' => 'PageSpeed Insights',
                'progress_key' => 'psi',
                'config_key' => 'site_audit.psi_enabled',
            ],
            'serp_snippets' => [
                'codes' => [
                    'serp_snippets',
                    'serp_title_mismatch',
                    'serp_not_indexed',
                    'serp_snippet_source',
                ],
                'title' => 'Сниппеты Яндекс / Google',
                'progress_key' => 'serp_snippets',
                'config_key' => 'site_audit.serp_snippets_enabled',
            ],
            'serp_cannibalization' => [
                'codes' => ['serp_snippet_cannibalization'],
                'title' => 'Каннибализация по сниппетам',
                'progress_key' => 'serp_cannibalization',
                'config_key' => 'site_audit.serp_cannibalization_enabled',
            ],
            'serp_index' => [
                'codes' => ['index_count_mismatch'],
                'title' => 'Страницы на сайте не в индексе поисковых',
                'progress_key' => 'serp_index',
                'config_key' => 'site_audit.serp_index_enabled',
            ],
        ];
    }

    public static function probeIdForCode(string $code): ?string
    {
        foreach (self::catalog() as $id => $meta) {
            if (in_array($code, $meta['codes'], true)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return array{probe:string,title:string,status:string,label:string,reason:?string,can_run:bool}|null
     */
    public static function forCode(SiteAuditCrawl $crawl, string $code): ?array
    {
        $probeId = self::probeIdForCode($code);
        if ($probeId === null) {
            return null;
        }

        return self::forProbe($crawl, $probeId);
    }

    /**
     * @return array{probe:string,title:string,status:string,label:string,reason:?string,can_run:bool}|null
     */
    public static function forProbe(SiteAuditCrawl $crawl, string $probeId): ?array
    {
        $meta = self::catalog()[$probeId] ?? null;
        if ($meta === null) {
            return null;
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $block = is_array($progress[$meta['progress_key']] ?? null)
            ? $progress[$meta['progress_key']]
            : null;
        $enabled = (bool) config($meta['config_key'], false);
        $reason = is_array($block) ? (string) ($block['reason'] ?? '') : '';

        if (is_array($block) && ! empty($block['skipped'])) {
            return [
                'probe' => $probeId,
                'title' => $meta['title'],
                'status' => 'skipped',
                'label' => 'не было',
                'reason' => $reason !== '' ? $reason : 'disabled',
                'can_run' => true,
            ];
        }

        if (is_array($block) && (
            ! empty($block['ran'])
            || ! empty($block['ok'])
            || isset($block['urls'])
            || isset($block['checked'])
            || isset($block['rows'])
            || isset($block['engines'])
        )) {
            return [
                'probe' => $probeId,
                'title' => $meta['title'],
                'status' => 'ran',
                'label' => 'готово',
                'reason' => null,
                'can_run' => true,
            ];
        }

        // Старые проверки / нет блока в progress — смотрим конфиг.
        if (! $enabled) {
            return [
                'probe' => $probeId,
                'title' => $meta['title'],
                'status' => 'skipped',
                'label' => 'не было',
                'reason' => 'disabled',
                'can_run' => true,
            ];
        }

        return [
            'probe' => $probeId,
            'title' => $meta['title'],
            'status' => 'pending',
            'label' => 'ожидает',
            'reason' => null,
            'can_run' => true,
        ];
    }

    public static function reasonLabel(?string $reason): string
    {
        $map = [
            'disabled' => 'отключена на сервере (по умолчанию выкл.)',
            'no_urls' => 'не нашлось URL для проверки',
            'no_key' => 'нет API-ключа',
            'error' => 'ошибка при запуске',
            'no_pages' => 'нет страниц в проверке',
            'no_domain' => 'нет домена проекта',
            'no_webmaster' => 'не подключён / не привязан Яндекс.Вебмастер',
            'webmaster_error' => 'ошибка API Яндекс.Вебмастера',
            'exception' => 'ошибка выполнения',
        ];

        $reason = (string) $reason;

        return $map[$reason] ?? ($reason !== '' ? $reason : 'не запускалась');
    }
}
