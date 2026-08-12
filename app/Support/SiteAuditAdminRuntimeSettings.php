<?php

namespace App\Support;

use App\Services\SiteAudit\SiteAuditGlobalCap;
use App\SiteAuditCrawl;
use Illuminate\Support\Facades\Log;

/**
 * Админ-override слотов/батчей Site Audit (storage JSON).
 * Не трогает .env — переживает config:cache и работает на local/prod.
 */
class SiteAuditAdminRuntimeSettings
{
    public const STORAGE_FILE = 'site-audit-admin-settings.json';

    /** @var array<string, array{type:string,min:int|float,max:int|float,label:string,help:string}> */
    public const FIELDS = [
        'global_max_active_crawls' => [
            'type' => 'int',
            'min' => 1,
            'max' => 20,
            'label' => 'Глобальные слоты',
            'help' => 'Сколько проверок одновременно на весь сервер (queued / discovering / fetching / aggregating). Остальные — «Ждёт слот».',
        ],
        'max_active_crawls_per_user' => [
            'type' => 'int',
            'min' => 1,
            'max' => 10,
            'label' => 'Слоты на пользователя',
            'help' => 'Сколько своих проверок один пользователь может гонять параллельно (в т.ч. пока предыдущая в PSI).',
        ],
        'max_concurrency' => [
            'type' => 'int',
            'min' => 1,
            'max' => 16,
            'label' => 'HTTP-потоки в крауле',
            'help' => 'Параллельные запросы внутри одной проверки (верхний потолок; тариф может резать ниже).',
        ],
        'batch_max_pages' => [
            'type' => 'int',
            'min' => 10,
            'max' => 500,
            'label' => 'Страниц за job',
            'help' => 'Порция обхода в одном Continue-job, дальше пауза и новый job.',
        ],
        'batch_max_seconds' => [
            'type' => 'int',
            'min' => 30,
            'max' => 900,
            'label' => 'Секунд за job',
            'help' => 'Таймбокс одной порции обхода (сек).',
        ],
        'stale_active_minutes' => [
            'type' => 'int',
            'min' => 15,
            'max' => 1440,
            'label' => 'Stale (мин)',
            'help' => 'Активная проверка без updated_at дольше N минут → failed (иначе держит слот).',
        ],
        'kick_idle_minutes' => [
            'type' => 'int',
            'min' => 1,
            'max' => 120,
            'label' => 'Kick idle (мин)',
            'help' => 'Если цепочка Discover/Continue оборвалась — пнуть job снова через N минут.',
        ],
    ];

    /** @var array<string, mixed>|null */
    private static $memo;

    /** @var array<string, int>|null Конфиг до admin-override (для подсказки в UI). */
    private static $baseline;

    public static function path(): string
    {
        return storage_path('app/' . self::STORAGE_FILE);
    }

    /**
     * Значения из config/.env до override админки.
     *
     * @return array<string, int>
     */
    public static function configDefaults(): array
    {
        if (self::$baseline !== null) {
            return self::$baseline;
        }

        $out = [];
        foreach (self::FIELDS as $key => $meta) {
            $out[$key] = (int) config('site_audit.' . $key, $meta['min']);
        }

        return $out;
    }

    /**
     * Только то, что сохранено в админке (может быть []).
     *
     * @return array<string, int>
     */
    public static function stored(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $path = self::path();
        if (! is_file($path)) {
            return self::$memo = [];
        }

        try {
            $raw = json_decode((string) file_get_contents($path), true);
        } catch (\Throwable $e) {
            Log::warning('SiteAudit admin settings read failed: ' . $e->getMessage());

            return self::$memo = [];
        }

        if (! is_array($raw)) {
            return self::$memo = [];
        }

        $clean = [];
        foreach (self::FIELDS as $key => $meta) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $clean[$key] = self::clamp($key, $raw[$key]);
        }

        return self::$memo = $clean;
    }

    /**
     * Эффективные значения: override → config/env.
     *
     * @return array<string, int>
     */
    public static function effective(): array
    {
        return array_merge(self::configDefaults(), self::stored());
    }

    public static function getInt(string $key): int
    {
        $stored = self::stored();
        if (array_key_exists($key, $stored)) {
            return (int) $stored[$key];
        }

        $defaults = self::configDefaults();

        return (int) ($defaults[$key] ?? 0);
    }

    public static function flushMemo(): void
    {
        self::$memo = null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, int>
     */
    public static function put(array $input): array
    {
        self::captureBaseline();

        $clean = [];
        foreach (self::FIELDS as $key => $meta) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $clean[$key] = self::clamp($key, $input[$key]);
        }

        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $toWrite = array_merge($clean, [
            '_meta' => ['updated_at' => now()->toDateTimeString()],
        ]);
        file_put_contents(
            self::path(),
            json_encode($toWrite, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$memo = $clean;
        self::applyToConfig();

        return $clean;
    }

    public static function applyToConfig(): void
    {
        self::captureBaseline();
        foreach (self::stored() as $key => $val) {
            config(['site_audit.' . $key => $val]);
        }
    }

    private static function captureBaseline(): void
    {
        if (self::$baseline !== null) {
            return;
        }
        $out = [];
        foreach (self::FIELDS as $key => $meta) {
            $out[$key] = (int) config('site_audit.' . $key, $meta['min']);
        }
        self::$baseline = $out;
    }

    public static function updatedAt(): ?string
    {
        $path = self::path();
        if (! is_file($path)) {
            return null;
        }
        try {
            $raw = json_decode((string) file_get_contents($path), true);
            if (is_array($raw) && ! empty($raw['_meta']['updated_at'])) {
                return (string) $raw['_meta']['updated_at'];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return date('Y-m-d H:i:s', (int) filemtime($path));
    }

    /**
     * Живая картина очереди для админки.
     *
     * @return array<string, mixed>
     */
    public static function capacitySnapshot(): array
    {
        $activeStatuses = SiteAuditGlobalCap::activeStatuses();
        $waiting = (int) SiteAuditCrawl::query()
            ->where('status', SiteAuditCrawl::STATUS_QUEUED_WAIT)
            ->count();
        $active = SiteAuditGlobalCap::countActive();
        $byStatus = [];
        foreach (array_merge($activeStatuses, [SiteAuditCrawl::STATUS_QUEUED_WAIT]) as $st) {
            $byStatus[$st] = (int) SiteAuditCrawl::query()->where('status', $st)->count();
        }

        $activeRows = SiteAuditCrawl::query()
            ->whereIn('status', $activeStatuses)
            ->with('project:id,domain')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'user_id', 'project_id', 'status', 'pages_fetched', 'pages_limit', 'updated_at']);

        $list = [];
        foreach ($activeRows as $c) {
            $list[] = [
                'id' => (int) $c->id,
                'user_id' => (int) $c->user_id,
                'domain' => $c->project ? (string) $c->project->domain : '—',
                'status' => (string) $c->status,
                'status_label' => SiteAuditCrawl::statusLabel($c->status),
                'progress' => number_format((int) $c->pages_fetched, 0, '', ' ')
                    . ' / ' . number_format((int) $c->pages_limit, 0, '', ' '),
                'updated_at' => $c->updated_at ? $c->updated_at->format('d.m H:i') : null,
                'url' => route('pages.site-audit.crawl.show', $c->id),
            ];
        }

        $waitingRows = SiteAuditCrawl::query()
            ->where('status', SiteAuditCrawl::STATUS_QUEUED_WAIT)
            ->with('project:id,domain')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'user_id', 'project_id', 'created_at']);

        $waitList = [];
        foreach ($waitingRows as $c) {
            $waitList[] = [
                'id' => (int) $c->id,
                'user_id' => (int) $c->user_id,
                'domain' => $c->project ? (string) $c->project->domain : '—',
                'created_at' => $c->created_at ? $c->created_at->format('d.m H:i') : null,
                'block' => SiteAuditGlobalCap::blockingActiveSummary((int) $c->user_id, (int) $c->id),
                'url' => route('pages.site-audit.crawl.show', $c->id),
            ];
        }

        $eff = self::effective();
        $defaults = self::configDefaults();
        $stored = self::stored();

        return [
            'active' => $active,
            'waiting' => $waiting,
            'free_slots' => max(0, (int) $eff['global_max_active_crawls'] - $active),
            'by_status' => $byStatus,
            'active_list' => $list,
            'waiting_list' => $waitList,
            'effective' => $eff,
            'defaults' => $defaults,
            'stored' => $stored,
            'updated_at' => self::updatedAt(),
            'source' => $stored !== [] ? 'admin' : 'config',
            'workers_hint' => (int) config(
                'cabinet-supervisor-admin.program_capacity.cabinet-titlo-site-audit.numprocs_lk',
                3
            ),
        ];
    }

    /**
     * @param  mixed  $value
     */
    private static function clamp(string $key, $value): int
    {
        $meta = self::FIELDS[$key];
        $n = (int) $value;
        $min = (int) $meta['min'];
        $max = (int) $meta['max'];

        return max($min, min($max, $n));
    }
}
