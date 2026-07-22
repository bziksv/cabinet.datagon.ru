<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditFindingNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteAuditFindingNoteService
{
    public function tableReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = Schema::hasTable('site_audit_finding_notes');
            } catch (\Throwable $e) {
                $ready = false;
            }
        }

        return $ready;
    }

    public function upsert(
        int $projectId,
        int $userId,
        string $code,
        string $urlHash,
        ?string $url = null,
        string $status = SiteAuditFindingNote::STATUS_OPEN,
        ?string $comment = null
    ): SiteAuditFindingNote {
        if (! $this->tableReady()) {
            throw new \RuntimeException('Таблица site_audit_finding_notes не создана. Выполните php artisan migrate.');
        }

        $status = $status === SiteAuditFindingNote::STATUS_FIXED
            ? SiteAuditFindingNote::STATUS_FIXED
            : SiteAuditFindingNote::STATUS_OPEN;
        $comment = is_string($comment) ? mb_substr(trim($comment), 0, 1000) : null;
        if ($comment === '') {
            $comment = null;
        }

        return SiteAuditFindingNote::query()->updateOrCreate(
            [
                'project_id' => $projectId,
                'code' => $code,
                'url_hash' => $urlHash,
            ],
            [
                'user_id' => $userId,
                'url' => $url,
                'status' => $status,
                'comment' => $comment,
            ]
        );
    }

    public function upsertForFinding(
        SiteAuditFinding $finding,
        int $projectId,
        int $userId,
        string $status,
        ?string $comment
    ): SiteAuditFindingNote {
        return $this->upsert(
            $projectId,
            $userId,
            $finding->code,
            (string) ($finding->url_hash ?: ''),
            $finding->url,
            $status,
            $comment
        );
    }

    public function delete(int $projectId, string $code, string $urlHash): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        return SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->where('url_hash', $urlHash)
            ->delete();
    }

    public function projectHasFixed(int $projectId): bool
    {
        if (! $this->tableReady()) {
            return false;
        }

        return SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->where('status', SiteAuditFindingNote::STATUS_FIXED)
            ->exists();
    }

    public function excludeFixed(Builder $query, int $projectId, string $findingsTable = 'site_audit_findings'): Builder
    {
        if (! $this->tableReady()) {
            return $query;
        }

        return $query->whereNotExists(function ($q) use ($projectId, $findingsTable) {
            $q->select(DB::raw(1))
                ->from('site_audit_finding_notes as san')
                ->whereColumn('san.code', $findingsTable . '.code')
                ->whereColumn('san.url_hash', $findingsTable . '.url_hash')
                ->where('san.project_id', $projectId)
                ->where('san.status', SiteAuditFindingNote::STATUS_FIXED);
        });
    }

    /**
     * @param array<string,int|float> $rawCounts
     * @return array<string,int|float>
     */
    public function applyFixedToCounts(array $rawCounts, SiteAuditCrawl $crawl): array
    {
        $projectId = (int) $crawl->project_id;
        if ($projectId < 1 || ! $this->projectHasFixed($projectId)) {
            return $rawCounts;
        }

        $fixedByCode = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereExists(function ($q) use ($projectId) {
                $q->select(DB::raw(1))
                    ->from('site_audit_finding_notes as san')
                    ->whereColumn('san.code', 'site_audit_findings.code')
                    ->whereColumn('san.url_hash', 'site_audit_findings.url_hash')
                    ->where('san.project_id', $projectId)
                    ->where('san.status', SiteAuditFindingNote::STATUS_FIXED);
            })
            ->select('code', DB::raw('count(*) as c'))
            ->groupBy('code')
            ->pluck('c', 'code')
            ->all();

        if ($fixedByCode === []) {
            return $rawCounts;
        }

        $out = $rawCounts;
        foreach ($fixedByCode as $code => $c) {
            if (! isset($out[$code])) {
                continue;
            }
            $out[$code] = max(0, (int) $out[$code] - (int) $c);
        }

        return $out;
    }

    /**
     * @param iterable $rows
     * @return array<int, array{status:string,comment:?string}>
     */
    public function mapForFindings(int $projectId, $rows): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $keys = [];
        foreach ($rows as $row) {
            if (! isset($row->id)) {
                continue;
            }
            $code = (string) ($row->code ?? '');
            $hash = (string) ($row->url_hash ?? '');
            if ($code === '' || $hash === '') {
                continue;
            }
            $keys[$code][$hash] = true;
        }
        if ($keys === []) {
            return [];
        }

        $notes = SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->whereIn('code', array_keys($keys))
            ->get(['code', 'url_hash', 'status', 'comment']);

        $byKey = [];
        foreach ($notes as $n) {
            $byKey[$n->code . "\0" . $n->url_hash] = [
                'status' => (string) $n->status,
                'comment' => $n->comment,
            ];
        }

        $map = [];
        foreach ($rows as $row) {
            if (! isset($row->id)) {
                continue;
            }
            $key = ((string) ($row->code ?? '')) . "\0" . ((string) ($row->url_hash ?? ''));
            if (isset($byKey[$key])) {
                $map[(int) $row->id] = $byKey[$key];
            }
        }

        return $map;
    }
}
