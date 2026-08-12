<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;

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
            // Ручной запуск с вкладки «Антиплагиат» (не автопроба при агрегации).
            'plagiarism_external' => [
                'codes' => ['landing_plagiarism_external'],
                'title' => 'Антиплагиат (внешний)',
                'progress_key' => 'plagiarism_external',
                'config_key' => 'site_audit.plagiarism_external_manual',
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
        $deep = is_array($block['deep'] ?? null) ? $block['deep'] : null;
        $hasDeepResult = is_array($deep) && (
            isset($deep['serp_count'])
            || ($deep['source'] ?? '') === 'webmaster'
            || ($deep['mode'] ?? '') === 'webmaster_list'
            || isset($deep['matched'])
        );

        $findingCount = (int) SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', $meta['codes'])
            ->count();

        // Внешний антиплагиат: автовыборка ~3 URL после обхода + ручной добор.
        if ($probeId === 'plagiarism_external') {
            $st = is_array($block) ? (string) ($block['status'] ?? '') : '';
            if (is_array($block) && ! empty($block['skipped']) && ! in_array($st, ['queued', 'running', 'done'], true)) {
                return [
                    'probe' => $probeId,
                    'title' => $meta['title'],
                    'status' => 'skipped',
                    'label' => 'не было',
                    'reason' => (string) ($block['reason'] ?? 'manual'),
                    'can_run' => false,
                ];
            }
            $ran = $findingCount > 0
                || in_array($st, ['queued', 'running', 'done'], true)
                || (is_array($block) && (
                    array_key_exists('rows', $block)
                    || ! empty($block['finished_at'])
                ));
            if ($ran) {
                $live = in_array($st, ['queued', 'running'], true);

                return [
                    'probe' => $probeId,
                    'title' => $meta['title'],
                    'status' => $live ? 'pending' : 'ran',
                    'label' => $live ? 'идёт' : 'готово',
                    'reason' => null,
                    'can_run' => false,
                ];
            }

            return [
                'probe' => $probeId,
                'title' => $meta['title'],
                'status' => 'skipped',
                'label' => 'не было',
                'reason' => 'manual',
                'can_run' => false,
            ];
        }

        // Явный skip (выкл. / квота API / нет URL) — важнее «есть rows в progress».
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

        // Есть findings или сохранённый результат — это «готово», не «не было».
        if ($findingCount > 0 || (is_array($block) && (
            $hasDeepResult
            || ! empty($block['ran'])
            || ! empty($block['ok'])
            || isset($block['urls'])
            || isset($block['checked'])
            || isset($block['rows'])
            || isset($block['engines'])
        ))) {
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

    public static function reasonLabel(?string $reason, ?string $probeId = null): string
    {
        $reason = (string) $reason;

        // Старые краулы: PSI был выкл. по умолчанию — в progress остался skipped/disabled.
        if ($reason === 'disabled' && $probeId === 'psi' && (bool) config('site_audit.psi_enabled', true)) {
            return 'не запускалась в этой проверке (раньше PSI был выкл.; новые аудиты гоняют сами)';
        }

        $map = [
            'disabled' => 'отключена в настройках сервера',
            'manual' => 'после обхода ещё не успели / не смогли запустить автопроверку — сделайте вручную на вкладке «Антиплагиат»',
            'no_user' => 'нет пользователя для списания лимита уникальности',
            'no_urls' => 'не нашлось подходящих страниц для автопроверки',
            'no_key' => 'нет API-ключа',
            'api_quota' => 'Google PageSpeed отклонил все запросы (дневной лимит API без ключа или квота исчерпана)',
            'error' => 'ошибка при запуске',
            'no_pages' => 'нет страниц в проверке',
            'no_domain' => 'нет домена проекта',
            'no_webmaster' => 'не подключён / не привязан Яндекс.Вебмастер',
            'webmaster_error' => 'ошибка API Яндекс.Вебмастера',
            'exception' => 'ошибка выполнения',
        ];

        return $map[$reason] ?? ($reason !== '' ? $reason : 'не запускалась');
    }
}
