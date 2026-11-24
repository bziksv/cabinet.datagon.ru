<?php
namespace App\Services\Wordstat;

use App\Jobs\Wordstat\CollectWordstatSeed;
use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatPhrase;
use App\Wordstat\WordstatProject;
use App\Wordstat\WordstatRequestLog;
use App\Wordstat\WordstatRun;
use App\Wordstat\WordstatRunSeed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WordstatCollector
{
    private WordstatClient $client;
    private bool $throttle;

    public function __construct(WordstatClient $client, bool $throttle = true)
    {
        $this->client = $client;
        $this->throttle = $throttle;
    }

    public function createRun(WordstatProject $project, int $userId, array $seeds, array $settings): WordstatRun
    {
        $max = (int) config('cabinet-wordstat-keywords.max_seeds_per_run', 500);
        if (count($seeds) === 0 || count($seeds) > $max) {
            throw new \InvalidArgumentException('seeds count out of range');
        }
        $settings = WordstatSettings::normalize($settings);
        $estimate = WordstatEstimator::estimate(count($seeds), $settings);

        return DB::transaction(function () use ($project, $userId, $seeds, $settings, $estimate) {
            $run = WordstatRun::query()->create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'settings' => $settings,
                'status' => WordstatRun::STATUS_QUEUED,
                'seeds_total' => count($seeds),
                'requests_estimated' => $estimate['total'],
            ]);

            $groupIds = [];
            $position = (int) WordstatGroup::query()->where('project_id', $project->id)->max('position');
            foreach ($seeds as $seed) {
                $name = (string) $seed['group'];
                if (! isset($groupIds[$name])) {
                    $group = WordstatGroup::query()->firstOrCreate(
                        ['project_id' => $project->id, 'name' => $name],
                        ['source' => 'manual', 'position' => ++$position]
                    );
                    $groupIds[$name] = $group->id;
                }
                WordstatRunSeed::query()->create([
                    'run_id' => $run->id,
                    'group_id' => $groupIds[$name],
                    'phrase' => (string) $seed['phrase'],
                    'depth' => 0,
                ]);
            }

            return $run;
        });
    }

    public function collectSeed(WordstatRunSeed $seed): void
    {
        $run = WordstatRun::query()->findOrFail($seed->run_id);
        if (! $run->isActive()) {
            return;
        }
        // Атомарный захват: повторная доставка джобы не соберёт затравку дважды
        $claimed = WordstatRunSeed::query()->where('id', $seed->id)
            ->where('status', WordstatRunSeed::STATUS_QUEUED)
            ->update(['status' => WordstatRunSeed::STATUS_RUNNING]);
        if ($claimed !== 1) {
            return;
        }
        $seed->status = WordstatRunSeed::STATUS_RUNNING;
        if ($run->status === WordstatRun::STATUS_QUEUED) {
            $run->update(['status' => WordstatRun::STATUS_RUNNING, 'started_at' => now()]);
        }

        $settings = WordstatSettings::normalize((array) $run->settings);
        $query = WordstatPhraseFilter::stripStopWords($seed->phrase, $settings['stop_words']);
        $limit = $seed->depth === 0 ? $settings['left_limit'] : $settings['depth_left_limit'];

        if ($this->throttle) {
            WordstatRateLimiter::acquire();
        }
        $result = $this->client->words($query !== '' ? $query : $seed->phrase, $settings['region_ids'], $settings['device'], $limit);
        WordstatRequestLog::query()->create([
            'user_id' => $run->user_id, 'project_id' => $run->project_id, 'run_id' => $run->id, 'kind' => 'words', 'created_at' => now(),
        ]);
        // Считаем сразу после запроса: повтор задачи после сбоя — это ещё один оплаченный запрос
        WordstatRunSeed::query()->whereKey($seed->id)->increment('requests_used');
        WordstatRun::query()->whereKey($run->id)->increment('requests_used');

        $added = 0;
        $newLeft = [];

        DB::transaction(function () use ($seed, $run, $settings, $result, &$added, &$newLeft) {
            $seedRow = $this->upsertPhrase($run, $seed, $seed->phrase, $result->totalCount, $seed->depth === 0 ? WordstatPhrase::SOURCE_MANUAL : WordstatPhrase::SOURCE_LEFT, null, $added);
            $seedPhraseId = $seedRow ? $seedRow->id : null;

            $candidates = [];
            if (in_array('left', $settings['columns'], true)) {
                foreach ($result->left as $row) {
                    $candidates[] = [$row, WordstatPhrase::SOURCE_LEFT];
                }
            }
            if (in_array('right', $settings['columns'], true)) {
                foreach ($result->right as $row) {
                    $candidates[] = [$row, WordstatPhrase::SOURCE_RIGHT];
                }
            }

            foreach ($candidates as [$row, $source]) {
                if (WordstatPhraseFilter::hasMinusWord($row['phrase'], $settings['minus_words'])) {
                    continue;
                }
                $before = $added;
                $this->upsertPhrase($run, $seed, $row['phrase'], $row['count'], $source, $seedPhraseId, $added);
                if ($added > $before && $source === WordstatPhrase::SOURCE_LEFT) {
                    $newLeft[] = $row['phrase'];
                }
            }
        });

        $this->queueChildren($run, $seed, $settings, $newLeft);
        $seed->update(['status' => WordstatRunSeed::STATUS_DONE]);
        $this->bumpRun($run->id, 0, $added);
    }

    private function upsertPhrase(WordstatRun $run, WordstatRunSeed $seed, string $phrase, int $freq, string $source, ?int $seedPhraseId, int &$added): ?WordstatPhrase
    {
        $key = WordstatPhraseKey::make($phrase);
        if ($key === '') {
            return null;
        }
        $existing = WordstatPhrase::query()->where('project_id', $run->project_id)->where('phrase_hash', WordstatPhraseKey::hash($key))->first();
        if ($existing) {
            if ((int) $existing->group_id === (int) $seed->group_id) {
                $existing->update(['freq' => $freq]);
            }

            return $existing;
        }
        try {
            $created = WordstatPhrase::query()->create([
                'project_id' => $run->project_id,
                'group_id' => $seed->group_id,
                'phrase' => $phrase,
                'phrase_key' => $key,
                'freq' => $freq,
                'source' => $source,
                'seed_phrase_id' => $seedPhraseId,
                'depth' => $seed->depth,
                'status' => WordstatPhrase::STATUS_ACTIVE,
                'run_id' => $run->id,
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            // Параллельная затравка уже добавила эту фразу — используем её
            return WordstatPhrase::query()->where('project_id', $run->project_id)->where('phrase_hash', WordstatPhraseKey::hash($key))->first();
        }
        $added++;

        return $created;
    }

    private function queueChildren(WordstatRun $run, WordstatRunSeed $seed, array $settings, array $phrases): void
    {
        if ($seed->depth >= $settings['depth'] || $phrases === []) {
            return;
        }
        $childIds = DB::transaction(function () use ($run, $seed, $settings, $phrases) {
            WordstatRun::query()->lockForUpdate()->findOrFail($run->id);
            $already = WordstatRunSeed::query()->where('run_id', $run->id)->where('depth', '>', 0)->count();
            $room = $settings['depth_max_seeds'] - $already;
            if ($room <= 0) {
                return [];
            }
            $ids = [];
            foreach (array_slice($phrases, 0, $room) as $phrase) {
                $ids[] = WordstatRunSeed::query()->create([
                    'run_id' => $run->id, 'group_id' => $seed->group_id, 'phrase' => $phrase, 'depth' => $seed->depth + 1,
                ])->id;
            }
            WordstatRun::query()->where('id', $run->id)->increment('seeds_total', count($ids));

            return $ids;
        });

        foreach ($childIds as $childId) {
            CollectWordstatSeed::dispatch($childId)->onQueue(config('cabinet-wordstat-keywords.queue'));
        }
    }

    private function bumpRun(int $runId, int $requests, int $added): void
    {
        DB::transaction(function () use ($runId, $requests, $added) {
            $run = WordstatRun::query()->lockForUpdate()->findOrFail($runId);
            $run->seeds_done = (int) $run->seeds_done + 1;
            $run->requests_used = (int) $run->requests_used + $requests;
            $run->phrases_added = (int) $run->phrases_added + $added;
            if ($run->seeds_done >= $run->seeds_total && $run->isActive()) {
                $run->status = WordstatRun::STATUS_DONE;
                $run->finished_at = now();
            }
            $run->save();
        });
    }

    public function failSeed(WordstatRunSeed $seed, string $error): void
    {
        // Повторный вызов (например, из failed()) не должен учитывать затравку дважды
        $changed = WordstatRunSeed::query()->where('id', $seed->id)
            ->whereIn('status', [WordstatRunSeed::STATUS_QUEUED, WordstatRunSeed::STATUS_RUNNING])
            ->update(['status' => WordstatRunSeed::STATUS_FAILED, 'error' => mb_substr($error, 0, 1000)]);
        if ($changed === 1) {
            $this->bumpRun((int) $seed->run_id, 0, 0);
        }
    }

    /** Вернуть захваченную затравку в очередь перед повтором */
    public function resetSeed(WordstatRunSeed $seed): void
    {
        WordstatRunSeed::query()->where('id', $seed->id)
            ->where('status', WordstatRunSeed::STATUS_RUNNING)
            ->update(['status' => WordstatRunSeed::STATUS_QUEUED]);
    }

    public function failRun(WordstatRun $run, string $error): void
    {
        $run->update(['status' => WordstatRun::STATUS_FAILED, 'error' => mb_substr($error, 0, 1000), 'finished_at' => now()]);
        WordstatRunSeed::query()->where('run_id', $run->id)->where('status', WordstatRunSeed::STATUS_QUEUED)
            ->update(['status' => WordstatRunSeed::STATUS_SKIPPED]);
    }

    public function cancelRun(WordstatRun $run): void
    {
        if (! $run->isActive()) {
            return;
        }
        $run->update(['status' => WordstatRun::STATUS_CANCELLED, 'finished_at' => now()]);
        WordstatRunSeed::query()->where('run_id', $run->id)->where('status', WordstatRunSeed::STATUS_QUEUED)
            ->update(['status' => WordstatRunSeed::STATUS_SKIPPED]);
    }
}
