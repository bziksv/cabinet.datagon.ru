<?php

namespace App\Http\Controllers;

use App\Events\MonitoringProjectCreated;
use App\Classes\Monitoring\MonitoringGoogleDepth;
use App\Classes\Monitoring\MonitoringLocationLabel;
use App\Classes\Monitoring\ProjectFaviconService;
use App\MonitoringKeywordPrice;
use App\Support\MonitoringPositionsSchedule;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MonitoringProjectCreatorController extends Controller
{
    protected $user;
    protected $project;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            return $next($request);
        });
    }

    public function createProject(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:255',
        ]);

        /** @var User $user */
        $user = $this->user;

        $project = $user->monitoringProjects()->create([
            'creator' => $user['id'],
            'status' => 1,
            'name' => trim((string) $request->input('name')),
            'url' => trim((string) $request->input('url')),
        ]);

        if (!$project) {
            return response()->json(['message' => __('Could not create project')], 422);
        }

        event(new MonitoringProjectCreated($user, $project));

        return response()->json(['id' => (int) $project->id]);
    }

    public function updateProject(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:255',
        ]);

        $id = (int) $request->input('id');

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project) {
            return response()->json(['message' => __('Project not found')], 404);
        }

        $oldUrl = $project->url;
        $project->update([
            'name' => trim((string) $request->input('name')),
            'url' => trim((string) $request->input('url')),
        ]);

        if ($oldUrl !== $project->url) {
            try {
                app(ProjectFaviconService::class)->refresh($project, true);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['id' => (int) $project->id]);
    }

    public function editProject(Request $request)
    {
        $id = (int) $request->input('id');

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project) {
            return response()->json(null, 404);
        }

        return response()->json([
            'id' => (int) $project->id,
            'name' => $project->name,
            'url' => $project->url,
        ]);
    }

    public function actionQueries(Request $request)
    {
        /** @var User $user */
        $user = $this->user;
        $this->project = $user->monitoringProjects()->find((int) $request->input('id'));
        if (!$this->project) {
            return response()->json(['message' => __('Project not found')], 404);
        }

        switch ($request->input('action')) {
            case 'create':
                return $this->createQueries($request);
            case 'bulk_create':
                return $this->bulkCreateQueries($request);
            case 'edit':
                return $this->editQueries($request);
            case 'remove':
                return $this->removeQueries($request);
            case 'clear':
                return $this->clearQueries();
        }

        return response()->json(['message' => 'Unknown action'], 422);
    }

    public function getQueries(Request $request)
    {
        $collections = collect([]);
        $id = $request->input('id');

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);

        if(!$project)
            return collect([
                'data' => $collections,
                'recordsFiltered' => 0,
                'recordsTotal' => 0,
            ]);

        $length = max(1, (int) $request->input('length', 10));
        $start = max(0, (int) $request->input('start', 0));
        $page = (int) floor($start / $length) + 1;
        $keywords = $project->keywords()->paginate($length, ['*'], 'page', $page);

        foreach ($keywords as $q){

            $collections->push([
                'DT_RowId' => "row_" . $q->id,
                'query' => $q->query,
                'page' => $q->page,
                'group' => $q->group->name,
                'target' => $q->target,
            ]);
        }

        $data = collect([
            'data' => $collections,
            'draw' => $request->input('draw'),
            'recordsFiltered' => $keywords->total(),
            'recordsTotal' => $keywords->total(),
        ]);

        return $data;
    }

    public function getCompetitors(Request $request)
    {
        $id = (int) $request->input('id');

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project) {
            return response()->json('', 404);
        }

        return response(implode(PHP_EOL, $project->competitors->pluck('url')->toArray()));
    }

    public function createCompetitors(Request $request)
    {
        $id = (int) $request->input('id');
        $competitors = preg_split("/\r\n|\n|\r/", (string) $request->input('domains'));

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project) {
            return response()->json(['message' => __('Project not found')], 404);
        }

        foreach ($competitors as $competitor) {
            $competitor = trim($competitor);
            if ($competitor === '') {
                continue;
            }
            $project->competitors()->firstOrCreate([
                'url' => $competitor,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function actionRegion(Request $request)
    {
        /** @var User $user */
        $user = $this->user;
        $this->project = $user->monitoringProjects()->find((int) $request->input('id'));
        if (!$this->project) {
            return response()->json(['message' => __('Project not found')], 404);
        }

        switch ($request->input('action')) {
            case 'get':
                return $this->getRegions($request);
                break;
            case 'create':
                return $this->createRegion($request);
                break;
            case 'update':
                return $this->updateRegion($request);
                break;
            case 'remove':
                return $this->removeRegion($request);
                break;
        }
    }

    private function getRegions(Request $request)
    {
        $this->project->load(['searchengines.location']);

        return $this->project->searchengines->map(function ($item) {
            return [
                'id' => $item->id,
                'engine' => $item->engine,
                'lr' => $item->lr,
                'google_depth' => (int) ($item->google_depth ?? MonitoringGoogleDepth::MIN),
                'name' => MonitoringLocationLabel::displayName(
                    (string) $item->engine,
                    (string) $item->lr,
                    $item->location ? (string) $item->location->name : null
                ),
                'time' => $item->time,
                'weekdays' => $item->weekdays,
                'monthday' => $item->monthday,
                'day' => $item->day,
            ];
        })->values();
    }

    private function createRegion(Request $request)
    {
        $attrs = [
            'engine' => $request->input('engine'),
            'lr' => $request->input('lr'),
        ];

        $values = [];
        if ($request->input('engine') === 'google') {
            $values['google_depth'] = MonitoringGoogleDepth::normalize((int) $request->input('google_depth', MonitoringGoogleDepth::MIN));
        }

        $engine = $this->project->searchengines()->updateOrCreate($attrs, $values);
        $this->applyPendingImportPrices((int) $engine->id);

        return response()->json([
            'ok' => true,
            'id' => $engine->id,
            'engine' => $engine->engine,
            'lr' => $engine->lr,
            'google_depth' => (int) ($engine->google_depth ?? MonitoringGoogleDepth::MIN),
        ]);
    }

    private function updateRegion(Request $request)
    {
        /** @var User $user */
        $user = $this->user;

        $this->project->searchengines()->update([
            'auto_update' => false,
            'time' => null,
            'weekdays' => null,
            'monthday' => null,
            'day' => null,
        ]);

        $data = $request->input('data');
        if (!is_array($data)) {
            return response()->json(['ok' => true]);
        }

        foreach ($data as $item) {
            $engine = $this->project->searchengines()->find($item['id'] ?? null);
            if (!$engine) {
                continue;
            }

            $update = [
                $item['name'] => $item['val'],
            ];

            if (!$user->onFreeTariff()) {
                $update['auto_update'] = true;
            }

            $engine->update($update);
        }

        if ($user->onFreeTariff()) {
            MonitoringPositionsSchedule::enforceForFreeUser($user);
        }

        return response()->json(['ok' => true]);
    }

    private function removeRegion(Request $request)
    {
        $searchengines = $this->project->searchengines()
            ->where(['engine' => $request->input('engine'), 'lr' => $request->input('lr')])
            ->first();

        if ($this->project) {
            foreach ($this->project->keywords as $keys) {
                $keys->positions()->where('monitoring_searchengine_id', $searchengines['id'])->delete();
            }

            $searchengines->delete();
        }
    }

    private function createQueries(Request $request)
    {
        $data = $request->input('data');
        if (! is_array($data)) {
            return $this->emptyDataCollection();
        }
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }
            $query = trim((string) ($item['query'] ?? ''));
            if ($query === '') {
                continue;
            }
            $item['query'] = $query;
            $item['group'] = $this->firstOrCreateGroup($item['group'] ?? null);
            $this->createKeywords($item);
        }

        return $this->emptyDataCollection();
    }

    /**
     * Массовое добавление запросов (CSV/textarea) — JSON-чанками, без DataTables Editor.
     */
    private function bulkCreateQueries(Request $request)
    {
        $items = $request->input('queries');
        if (! is_array($items) || $items === []) {
            return response()->json(['created' => 0, 'skipped' => 0]);
        }

        if (count($items) > 500) {
            $items = array_slice($items, 0, 500);
        }

        $prepared = [];
        $seenInChunk = [];
        $skipped = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;
                continue;
            }
            $query = trim((string) ($item['query'] ?? ''));
            if ($query === '') {
                $skipped++;
                continue;
            }
            $key = mb_strtolower($query);
            if (isset($seenInChunk[$key])) {
                $skipped++;
                continue;
            }
            $seenInChunk[$key] = true;
            $prepared[] = [
                'query' => $query,
                'page' => isset($item['page']) ? (string) $item['page'] : '',
                'group' => isset($item['group']) ? (string) $item['group'] : 'Основная',
                'target' => (int) ($item['target'] ?? 10) ?: 10,
                'key' => $key,
                'prices' => $this->extractPriceFields($item),
            ];
        }

        if ($prepared === []) {
            return response()->json([
                'created' => 0,
                'skipped' => $skipped,
                'prices_saved' => 0,
                'prices_deferred' => 0,
            ]);
        }

        $queries = array_column($prepared, 'query');
        $existing = $this->project->keywords()
            ->whereIn('query', $queries)
            ->pluck('query')
            ->map(static function ($q) {
                return mb_strtolower(trim((string) $q));
            })
            ->flip()
            ->all();

        $groupCache = [];
        $rows = [];
        $pricesByKey = [];
        $now = now();

        foreach ($prepared as $item) {
            if (isset($existing[$item['key']])) {
                $skipped++;
                continue;
            }
            $groupName = $item['group'] !== '' ? $item['group'] : 'Основная';
            if (! isset($groupCache[$groupName])) {
                $groupCache[$groupName] = $this->firstOrCreateGroup($groupName);
            }
            $rows[] = [
                'monitoring_project_id' => (int) $this->project->id,
                'monitoring_group_id' => (int) $groupCache[$groupName],
                'query' => $item['query'],
                'page' => $item['page'],
                'target' => $item['target'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (! empty($item['prices'])) {
                $pricesByKey[$item['key']] = $item['prices'];
            }
            $existing[$item['key']] = true;
        }

        $created = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            \DB::table('monitoring_keywords')->insert($chunk);
            $created += count($chunk);
        }

        $priceStat = ['saved' => 0, 'deferred' => 0];
        if ($pricesByKey !== [] && $created > 0) {
            $priceStat = $this->saveImportPrices(
                array_column($rows, 'query'),
                $pricesByKey
            );
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'prices_saved' => $priceStat['saved'],
            'prices_deferred' => $priceStat['deferred'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, float>
     */
    private function extractPriceFields(array $item): array
    {
        $out = [];
        foreach (['top1', 'top3', 'top5', 'top10', 'top20', 'top50', 'top100'] as $field) {
            if (! array_key_exists($field, $item)) {
                continue;
            }
            $raw = $item[$field];
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_string($raw)) {
                $raw = str_replace([' ', ','], ['', '.'], $raw);
            }
            if (! is_numeric($raw)) {
                continue;
            }
            $out[$field] = round((float) $raw, 2);
        }

        // одна колонка «цена» / price → TOP 10 (самый частый кейс)
        if ($out === [] && array_key_exists('price', $item)) {
            $raw = $item['price'];
            if (is_string($raw)) {
                $raw = str_replace([' ', ','], ['', '.'], $raw);
            }
            if (is_numeric($raw)) {
                $out['top10'] = round((float) $raw, 2);
            }
        }

        return $out;
    }

    /**
     * @param  string[]  $createdQueries
     * @param  array<string, array<string, float>>  $pricesByKey
     * @return array{saved: int, deferred: int}
     */
    private function saveImportPrices(array $createdQueries, array $pricesByKey): array
    {
        $keywords = $this->project->keywords()
            ->whereIn('query', $createdQueries)
            ->get(['id', 'query']);

        $pending = [];
        foreach ($keywords as $kw) {
            $key = mb_strtolower(trim((string) $kw->query));
            if (! isset($pricesByKey[$key])) {
                continue;
            }
            $pending[] = [
                'keyword_id' => (int) $kw->id,
                'prices' => $pricesByKey[$key],
            ];
        }

        if ($pending === []) {
            return ['saved' => 0, 'deferred' => 0];
        }

        $this->mergePendingImportPrices($pending);

        $engineIds = $this->project->searchengines()->pluck('id')->all();
        if ($engineIds === []) {
            return ['saved' => 0, 'deferred' => count($pending)];
        }

        $saved = 0;
        foreach ($pending as $row) {
            foreach ($engineIds as $engineId) {
                MonitoringKeywordPrice::updateOrCreate(
                    [
                        'monitoring_keyword_id' => $row['keyword_id'],
                        'monitoring_searchengine_id' => (int) $engineId,
                    ],
                    $row['prices']
                );
                $saved++;
            }
        }

        return ['saved' => $saved, 'deferred' => 0];
    }

    private function pendingImportPricesCacheKey(): string
    {
        return 'mon_import_prices_' . (int) $this->project->id;
    }

    /**
     * @param  array<int, array{keyword_id: int, prices: array<string, float>}>  $rows
     */
    private function mergePendingImportPrices(array $rows): void
    {
        $key = $this->pendingImportPricesCacheKey();
        $existing = Cache::get($key, []);
        if (! is_array($existing)) {
            $existing = [];
        }
        $byId = [];
        foreach ($existing as $row) {
            if (! is_array($row) || empty($row['keyword_id'])) {
                continue;
            }
            $byId[(int) $row['keyword_id']] = $row;
        }
        foreach ($rows as $row) {
            $byId[(int) $row['keyword_id']] = $row;
        }
        Cache::put($key, array_values($byId), now()->addDays(14));
    }

    private function applyPendingImportPrices(int $engineId): void
    {
        $pending = Cache::get($this->pendingImportPricesCacheKey(), []);
        if (! is_array($pending) || $pending === []) {
            return;
        }

        foreach ($pending as $row) {
            if (! is_array($row) || empty($row['keyword_id']) || empty($row['prices']) || ! is_array($row['prices'])) {
                continue;
            }
            MonitoringKeywordPrice::updateOrCreate(
                [
                    'monitoring_keyword_id' => (int) $row['keyword_id'],
                    'monitoring_searchengine_id' => $engineId,
                ],
                $row['prices']
            );
        }
    }

    private function forgetPendingImportPrices(): void
    {
        Cache::forget($this->pendingImportPricesCacheKey());
    }

    private function editQueries(Request $request)
    {
        $data = $request->input('data');
        foreach ($data as $row => $item){
            $id = $this->stringToInt($row);
            $item['group'] = $this->firstOrCreateGroup($item['group']);
            $this->updateKeywords($id, $item);
        }

        return $this->emptyDataCollection();
    }

    private function removeQueries(Request $request)
    {
        $data = $request->input('data');
        foreach ($data as $item){
            $queryId = $this->stringToInt($item['DT_RowId']);
            $this->project->keywords()->find($queryId)->delete();
        }

        $this->deleteEmptyGroups();

        return $this->emptyDataCollection();
    }

    /**
     * Очистить все запросы проекта (мастер создания / ошибка разметки CSV).
     */
    private function clearQueries()
    {
        $deleted = 0;
        while (true) {
            $ids = $this->project->keywords()->orderBy('id')->limit(500)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $this->project->keywords()->whereIn('id', $ids)->delete();
            $deleted += $ids->count();
        }

        $this->deleteEmptyGroups();
        $this->forgetPendingImportPrices();

        return response()->json(['deleted' => $deleted]);
    }

    private function updateKeywords(int $id, array $data)
    {
        if(!$this->project)
            throw new ModelNotFoundException("Not exist MonitoringProject model");

        $this->project->keywords()->find($id)->update([
            'monitoring_group_id' => $data['group'],
            'query' => $data['query'],
            'page' => $data['page'],
            'target' => $data['target'],
        ]);
    }

    private function createKeywords($data)
    {
        if(!$this->project)
            throw new ModelNotFoundException("Not exist MonitoringProject model");

        $this->project->keywords()->create([
            'monitoring_group_id' => $data['group'] ?? "",
            'query' => $data['query'] ?? "",
            'page' => $data['page'] ?? "",
            'target' => $data['target'],
        ]);
    }

    private function firstOrCreateGroup($name = null)
    {
        if(!$this->project)
            throw new ModelNotFoundException("Not exist MonitoringProject model");

        if(!trim(strip_tags($name)))
            $name = 'Основная';

        $group = $this->project->groups()->firstOrCreate([
            'type' => 'keyword',
            'name' => $name
        ]);

        return $group['id'];
    }

    private function deleteEmptyGroups()
    {
        if(!$this->project)
            throw new ModelNotFoundException("Not exist MonitoringProject model");

        foreach ($this->project->groups as $group){
            if(!$group->keywords->count())
                $group->delete();
        }
    }

    public function getGroups(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json([['name' => __('Main')]]);
        }

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project) {
            return response()->json([['name' => __('Main')]]);
        }

        $groups = $project->groups;
        if ($groups->isEmpty()) {
            return response()->json([['name' => __('Main')]]);
        }

        return response()->json($groups);
    }

    private function stringToInt(string $str)
    {
        return intval(filter_var($str, FILTER_SANITIZE_NUMBER_INT));
    }

    private function emptyDataCollection()
    {
        return collect([
            'data' => []
        ]);
    }
}
