<?php

namespace App\Support;

use App\ClusterResults;
use App\DomainInformation;
use App\DomainMonitoring;
use App\DomainRecordsHistory;
use App\EseninTextCheckSession;
use App\IndexCheckHistory;
use App\MetaTag;
use App\MonitoringProject;
use App\PhraseCommerceHistory;
use App\Project;
use App\ProjectRelevanceHistory;
use App\ProjectTracking;
use App\SearchSuggestionsHistory;
use App\SeoReports\SeoReportProject;
use App\SiteAuditProject;
use App\SiteTypesHistory;
use App\TextUniquenessHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Счётчики «проектов / сохранений» для карточек главной (вариант cards v2).
 */
class HomeModuleItemCounts
{
    /**
     * @param  array<int, array<string, mixed>>  $modules
     * @return array<int, array<string, mixed>>
     */
    public static function enrich(array $modules): array
    {
        if (!Auth::check()) {
            return $modules;
        }

        $counts = self::countsForUser((int) Auth::id());

        foreach ($modules as &$module) {
            $key = self::pathKey((string) ($module['link'] ?? ''));
            if ($key !== null && array_key_exists($key, $counts)) {
                $module['items_count'] = (int) $counts[$key]['count'];
                $module['items_kind'] = $counts[$key]['kind'];
            } else {
                $module['items_count'] = null;
                $module['items_kind'] = null;
            }
        }
        unset($module);

        return $modules;
    }

    /**
     * @return array<string, array{count:int, kind:string}>
     */
    public static function countsForUser(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $out = [];

        $add = static function (string $key, string $kind, callable $fn) use (&$out): void {
            try {
                $out[$key] = [
                    'count' => (int) $fn(),
                    'kind' => $kind,
                ];
            } catch (Throwable $e) {
                $out[$key] = ['count' => 0, 'kind' => $kind];
            }
        };

        $add('analyze-relevance', 'projects', static function () use ($userId) {
            return ProjectRelevanceHistory::query()->where('user_id', $userId)->count();
        });

        $add('cluster', 'projects', static function () use ($userId) {
            return ClusterResults::query()
                ->where('user_id', $userId)
                ->where('show', 1)
                ->count();
        });

        $monitoringCount = static function () use ($userId) {
            /** @var \App\User|null $user */
            $user = Auth::user();
            if ($user && (int) $user->id === $userId) {
                return $user->monitoringProjects()->count();
            }

            return MonitoringProject::query()
                ->whereHas('users', static function ($q) use ($userId) {
                    $q->where('users.id', $userId);
                })
                ->count();
        };
        $add('monitoring', 'projects', $monitoringCount);
        $add('monitoring-v2', 'projects', $monitoringCount);

        $add('site-monitoring', 'projects', static function () use ($userId) {
            return DomainMonitoring::query()->where('user_id', $userId)->count();
        });

        $add('domain-information', 'projects', static function () use ($userId) {
            return DomainInformation::query()->where('user_id', $userId)->count();
        });

        $add('backlink', 'projects', static function () use ($userId) {
            return ProjectTracking::query()->where('user_id', $userId)->count();
        });

        $add('meta-tags', 'projects', static function () use ($userId) {
            return MetaTag::query()->where('user_id', $userId)->count();
        });

        $add('html-editor', 'projects', static function () use ($userId) {
            return Project::query()->where('user_id', $userId)->count();
        });

        $add('site-audit', 'projects', static function () use ($userId) {
            return SiteAuditProject::query()->where('user_id', $userId)->count();
        });

        $add('reports', 'projects', static function () use ($userId) {
            if (!Schema::hasTable('seo_report_projects')) {
                return 0;
            }

            return SeoReportProject::query()
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->count();
        });

        $add('text-analyzer', 'saved', static function () use ($userId) {
            return TextUniquenessHistory::query()->where('user_id', $userId)->count();
        });

        $add('esenin-text-check', 'saved', static function () use ($userId) {
            if (!Schema::hasTable('esenin_text_check_sessions')) {
                return 0;
            }

            return EseninTextCheckSession::query()->where('user_id', $userId)->count();
        });

        $add('domain-records', 'saved', static function () use ($userId) {
            return DomainRecordsHistory::query()->where('user_id', $userId)->count();
        });

        $add('index-check', 'saved', static function () use ($userId) {
            return IndexCheckHistory::query()->where('user_id', $userId)->count();
        });

        $add('search-suggestions', 'saved', static function () use ($userId) {
            return SearchSuggestionsHistory::query()->where('user_id', $userId)->count();
        });

        $add('site-types', 'saved', static function () use ($userId) {
            return SiteTypesHistory::query()->where('user_id', $userId)->count();
        });

        $add('phrase-commerce', 'saved', static function () use ($userId) {
            return PhraseCommerceHistory::query()->where('user_id', $userId)->count();
        });

        return $out;
    }

    public static function pathKey(string $link): ?string
    {
        $path = parse_url($link, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $link;
        }

        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }

        $segment = explode('/', $path)[0] ?? '';
        $segment = strtolower($segment);

        static $known = [
            'analyze-relevance' => true,
            'cluster' => true,
            'monitoring' => true,
            'monitoring-v2' => true,
            'site-monitoring' => true,
            'domain-information' => true,
            'backlink' => true,
            'meta-tags' => true,
            'html-editor' => true,
            'site-audit' => true,
            'reports' => true,
            'seo-reports' => true,
            'text-analyzer' => true,
            'esenin-text-check' => true,
            'domain-records' => true,
            'index-check' => true,
            'search-suggestions' => true,
            'site-types' => true,
            'phrase-commerce' => true,
        ];

        if (!isset($known[$segment])) {
            return null;
        }

        // Старый slug меню → тот же счётчик, что у /reports
        if ($segment === 'seo-reports') {
            return 'reports';
        }

        return $segment;
    }
}
