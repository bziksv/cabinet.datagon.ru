<?php

namespace App\Jobs\Backlink;

use App\LinkTracking;
use App\Services\Backlink\BacklinkChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Полная проверка всех ссылок проекта (кнопка «Проверить все»).
 */
class CheckProjectBacklinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200;

    public $tries = 1;

    /** @var int */
    public $projectId;

    public function __construct(int $projectId)
    {
        $this->projectId = $projectId;
        $this->onQueue((string) config('cabinet-backlink.check_queue', 'default'));
    }

    public static function cacheKey(int $projectId): string
    {
        return 'backlink.project_check.' . $projectId;
    }

    /**
     * @return array{status: string, total: int, done: int, started_at?: string, finished_at?: string}|null
     */
    public static function progress(int $projectId): ?array
    {
        $data = Cache::get(self::cacheKey($projectId));

        return is_array($data) ? $data : null;
    }

    public function handle(BacklinkChecker $checker): void
    {
        $key = self::cacheKey($this->projectId);
        $links = LinkTracking::query()
            ->where('project_tracking_id', $this->projectId)
            ->orderBy('id')
            ->get();

        $total = $links->count();
        $startedAt = date('Y-m-d H:i:s');
        Cache::put($key, [
            'status' => 'running',
            'total' => $total,
            'done' => 0,
            'started_at' => $startedAt,
        ], 7200);

        set_time_limit(0);

        $done = 0;
        foreach ($links as $link) {
            try {
                $checker->checkAndSave($link);
            } catch (\Throwable $e) {
                Log::warning('backlink.project_check.link_failed', [
                    'project_id' => $this->projectId,
                    'link_id' => $link->id,
                    'message' => $e->getMessage(),
                ]);
            }
            $done++;
            Cache::put($key, [
                'status' => 'running',
                'total' => $total,
                'done' => $done,
                'started_at' => $startedAt,
            ], 7200);
        }

        Cache::put($key, [
            'status' => 'done',
            'total' => $total,
            'done' => $done,
            'started_at' => $startedAt,
            'finished_at' => date('Y-m-d H:i:s'),
        ], 7200);

        BacklinkChecker::recountProject($this->projectId);
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::cacheKey($this->projectId), [
            'status' => 'failed',
            'total' => 0,
            'done' => 0,
            'error' => $e->getMessage(),
            'finished_at' => date('Y-m-d H:i:s'),
        ], 7200);
    }
}
