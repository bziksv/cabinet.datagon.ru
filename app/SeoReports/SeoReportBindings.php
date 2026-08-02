<?php

namespace App\SeoReports;

use App\MonitoringProject;
use App\Support\HomeUserSites;
use App\YandexMetrikaDomainCounter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SeoReportBindings
{
    public static function resolveMetrikaCounterId(int $userId, string $domain): ?int
    {
        if ($userId < 1 || !YandexMetrikaDomainCounter::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        $counterId = (int) YandexMetrikaDomainCounter::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->value('counter_id');

        return $counterId > 0 ? $counterId : null;
    }

    public static function resolveMonitoringProjectId(int $userId, string $domain): ?int
    {
        if ($userId < 1) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        foreach (self::monitoringOptionsForUser($userId) as $option) {
            if (($option['domain'] ?? '') === $domain) {
                return (int) $option['id'];
            }
        }

        return null;
    }

    /**
     * @return list<array{id:int,name:string,domain:string,label:string}>
     */
    public static function monitoringOptionsForUser(int $userId): array
    {
        /** @var \App\User|null $user */
        $user = Auth::user();
        $q = ($user && (int) $user->id === $userId)
            ? $user->monitoringProjects()
            : MonitoringProject::query()->whereHas('users', static function ($uq) use ($userId) {
                $uq->where('users.id', $userId);
            });

        $out = [];
        $q->orderByDesc('monitoring_projects.updated_at')
            ->limit(200)
            ->get([
                'monitoring_projects.id',
                'monitoring_projects.url',
                'monitoring_projects.name',
            ])
            ->each(static function ($row) use (&$out) {
                $domain = HomeUserSites::normalizeDomain((string) $row->url);
                $name = trim((string) $row->name);
                // Человекочитаемо: имя/домен первыми, id только в конце.
                if ($name !== '' && $domain !== '') {
                    $label = $name . ' · ' . $domain;
                } elseif ($name !== '') {
                    $label = $name;
                } elseif ($domain !== '') {
                    $label = $domain;
                } else {
                    $label = 'Проект мониторинга';
                }
                $out[] = [
                    'id' => (int) $row->id,
                    'name' => $name,
                    'domain' => $domain,
                    'label' => $label,
                ];
            });

        return $out;
    }

    /**
     * @return Collection<int, YandexMetrikaDomainCounter>
     */
    public static function metrikaBindingsForUser(int $userId): Collection
    {
        return YandexMetrikaDomainCounter::forUser($userId);
    }

    public static function applyAutoBindings(SeoReportProject $project): void
    {
        $userId = (int) $project->user_id;
        $domain = (string) $project->domain;

        if (!$project->metrika_counter_id) {
            $counterId = self::resolveMetrikaCounterId($userId, $domain);
            if ($counterId) {
                $project->metrika_counter_id = $counterId;
            }
        }

        if (!$project->monitoring_project_id) {
            $monitoringId = self::resolveMonitoringProjectId($userId, $domain);
            if ($monitoringId) {
                $project->monitoring_project_id = $monitoringId;
            }
        }
    }
}
