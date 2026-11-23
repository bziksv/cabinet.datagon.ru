<?php

namespace App\Http\Controllers;

use App\Exports\WordstatPhrasesExport;
use App\Jobs\Wordstat\StartWordstatRun;
use App\Services\Wordstat\WordstatCollector;
use App\Services\Wordstat\WordstatEstimator;
use App\Services\Wordstat\WordstatInputParser;
use App\Services\Wordstat\WordstatSettings;
use App\Support\YandexLrRegions;
use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatPhrase;
use App\Wordstat\WordstatPreset;
use App\Wordstat\WordstatProject;
use App\Wordstat\WordstatRequestLog;
use App\Wordstat\WordstatRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class WordstatKeywordsController extends Controller
{
    public function index()
    {
        $userId = (int) Auth::id();
        $projects = WordstatProject::query()->accessibleBy($userId)->orderByDesc('id')
            ->withCount(['groups', 'phrases' => fn ($q) => $q->where('status', 'active')])
            ->get();

        return view('wordstat-keywords.index', [
            'projects' => $projects,
            'teams' => app(\App\Services\SeoChecklist\SeoChecklistService::class)->teamsForUser($userId),
            'monthRequests' => $this->monthRequests($userId),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'region_ids' => 'nullable|array',
            'team_id' => 'nullable|integer',
        ]);
        $userId = (int) Auth::id();
        $teamId = (int) ($data['team_id'] ?? 0);
        if ($teamId && ! in_array($teamId, WordstatProject::teamIdsForMember($userId), true)) {
            $teamId = 0;
        }
        $domain = preg_replace('#^https?://(www\.)?#i', '', trim($data['domain']));
        $domain = rtrim(mb_strtolower($domain), '/');

        $project = WordstatProject::query()->create([
            'user_id' => $userId,
            'team_id' => $teamId ?: null,
            'domain' => $domain,
            'title' => $data['title'] ?? null,
            'region_ids' => WordstatSettings::normalize(['region_ids' => $data['region_ids'] ?? []])['region_ids'],
            'device' => 'all',
        ]);
        $url = route('pages.wordstat-keywords.show', $project->id);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => __('Проект создан'), 'redirect' => $url])
            : redirect($url);
    }

    public function show(int $id)
    {
        $project = $this->project($id);
        $userId = (int) Auth::id();
        $groups = WordstatGroup::query()->where('project_id', $project->id)->orderBy('position')->orderBy('id')
            ->withCount(['phrases' => fn ($q) => $q->where('status', 'active')])
            ->get();
        $activeRun = WordstatRun::query()->where('project_id', $project->id)->whereIn('status', ['queued', 'running'])->latest('id')->first();

        return view('wordstat-keywords.show', [
            'project' => $project,
            'groups' => $groups,
            'presets' => WordstatPreset::query()->visibleTo($userId)->orderBy('title')->get(),
            'regions' => YandexLrRegions::resolveMany(array_map('strval', (array) $project->region_ids), 100),
            'activeRun' => $activeRun ? $this->runPayload($activeRun) : null,
            'lastRuns' => WordstatRun::query()->where('project_id', $project->id)->latest('id')->limit(5)->get(),
            'monthRequests' => $this->monthRequests($userId),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $project = $this->project($id);
        abort_unless((int) $project->user_id === (int) Auth::id(), 403);
        $project->delete();
        $url = route('pages.wordstat-keywords');

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => __('Проект удалён'), 'redirect' => $url])
            : redirect($url);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $project = $this->project($id);
        [$parsed, $settings] = $this->parseRequest($request, $project);
        $groups = array_values(array_unique(array_column($parsed['seeds'], 'group')));

        return response()->json([
            'ok' => true,
            'groups' => $groups,
            'seeds' => $parsed['seeds'],
            'rejected' => $parsed['rejected'],
            'estimate' => WordstatEstimator::estimate(count($parsed['seeds']), $settings),
        ]);
    }

    public function startRun(Request $request, int $id, WordstatCollector $collector): JsonResponse
    {
        $project = $this->project($id);
        if (WordstatRun::query()->where('project_id', $project->id)->whereIn('status', ['queued', 'running'])->exists()) {
            return response()->json(['ok' => false, 'message' => __('Сбор по проекту уже идёт — дождитесь окончания или остановите его')], 422);
        }
        [$parsed, $settings] = $this->parseRequest($request, $project);
        $max = (int) config('cabinet-wordstat-keywords.max_seeds_per_run', 500);
        if (count($parsed['seeds']) === 0) {
            return response()->json(['ok' => false, 'message' => __('Введите хотя бы одну фразу')], 422);
        }
        if (count($parsed['seeds']) > $max) {
            return response()->json(['ok' => false, 'message' => __('Слишком много фраз за один запуск. Максимум: :max', ['max' => number_format($max, 0, '', ' ')])], 422);
        }

        $run = $collector->createRun($project, (int) Auth::id(), $parsed['seeds'], $settings);
        StartWordstatRun::dispatch($run->id)->onQueue(config('cabinet-wordstat-keywords.queue'));

        return response()->json(['ok' => true, 'message' => __('Сбор запущен'), 'run' => $this->runPayload($run->fresh())]);
    }

    public function runStatus(int $id, int $runId): JsonResponse
    {
        $project = $this->project($id);
        $run = WordstatRun::query()->where('project_id', $project->id)->findOrFail($runId);

        return response()->json(['ok' => true, 'run' => $this->runPayload($run)]);
    }

    public function cancelRun(int $id, int $runId, WordstatCollector $collector): JsonResponse
    {
        $project = $this->project($id);
        $run = WordstatRun::query()->where('project_id', $project->id)->findOrFail($runId);
        $collector->cancelRun($run);

        return response()->json(['ok' => true, 'message' => __('Сбор остановлен'), 'run' => $this->runPayload($run->fresh())]);
    }

    public function phrases(Request $request, int $id): JsonResponse
    {
        $project = $this->project($id);
        $status = in_array($request->query('status'), ['active', 'minus'], true) ? $request->query('status') : 'active';
        $query = WordstatPhrase::query()->where('project_id', $project->id)->where('status', $status);
        if ($request->filled('group_id')) {
            $query->where('group_id', (int) $request->query('group_id'));
        }
        $rows = $query->orderByDesc('freq')->limit(20000)
            ->get(['id', 'phrase', 'freq', 'source', 'depth', 'group_id', 'status']);

        return response()->json(['ok' => true, 'phrases' => $rows]);
    }

    public function bulkPhrases(Request $request, int $id): JsonResponse
    {
        $project = $this->project($id);
        $data = $request->validate([
            'ids' => 'required|array|max:20000',
            'ids.*' => 'integer',
            'action' => 'required|in:move,minus,restore,delete',
            'group_id' => 'nullable|integer',
        ]);
        $query = WordstatPhrase::query()->where('project_id', $project->id)->whereIn('id', $data['ids']);

        switch ($data['action']) {
            case 'move':
                $group = WordstatGroup::query()->where('project_id', $project->id)->findOrFail((int) ($data['group_id'] ?? 0));
                $affected = $query->update(['group_id' => $group->id]);
                break;
            case 'minus':
                $affected = $query->update(['status' => WordstatPhrase::STATUS_MINUS]);
                break;
            case 'restore':
                $affected = $query->update(['status' => WordstatPhrase::STATUS_ACTIVE]);
                break;
            default:
                $affected = $query->delete();
        }

        return response()->json(['ok' => true, 'affected' => (int) $affected]);
    }

    public function updateGroup(Request $request, int $id, int $groupId): JsonResponse
    {
        $project = $this->project($id);
        $group = WordstatGroup::query()->where('project_id', $project->id)->findOrFail($groupId);
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $request->input('name', ''))), 0, 255);
        if ($name === '') {
            return response()->json(['ok' => false, 'message' => __('Введите название группы')], 422);
        }
        $taken = WordstatGroup::query()->where('project_id', $project->id)->where('name', $name)->where('id', '!=', $group->id)->exists();
        if ($taken) {
            return response()->json(['ok' => false, 'message' => __('Группа с таким названием уже есть')], 422);
        }
        $group->update(['name' => $name]);

        return response()->json(['ok' => true, 'group' => ['id' => $group->id, 'name' => $group->name]]);
    }

    public function mergeGroup(Request $request, int $id, int $groupId): JsonResponse
    {
        $project = $this->project($id);
        $source = WordstatGroup::query()->where('project_id', $project->id)->findOrFail($groupId);
        $target = WordstatGroup::query()->where('project_id', $project->id)->findOrFail((int) $request->input('target_id'));
        abort_if($source->id === $target->id, 422);
        WordstatPhrase::query()->where('group_id', $source->id)->update(['group_id' => $target->id]);
        $source->delete();

        return response()->json(['ok' => true, 'message' => __('Группы объединены')]);
    }

    public function destroyGroup(int $id, int $groupId): JsonResponse
    {
        $project = $this->project($id);
        $group = WordstatGroup::query()->where('project_id', $project->id)->findOrFail($groupId);
        WordstatPhrase::query()->where('group_id', $group->id)->delete();
        $group->delete();

        return response()->json(['ok' => true, 'message' => __('Группа удалена')]);
    }

    public function export(Request $request, int $id)
    {
        $project = $this->project($id);
        $groupId = $request->filled('group_id') ? (int) $request->query('group_id') : null;
        $name = 'wordstat-' . preg_replace('/[^a-z0-9.-]+/i', '-', $project->domain) . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new WordstatPhrasesExport($project->id, $groupId), $name);
    }

    public function storePreset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'settings' => 'nullable|array',
            'team_id' => 'nullable|integer',
            'preset_id' => 'nullable|integer',
        ]);
        $userId = (int) Auth::id();
        $teamId = (int) ($data['team_id'] ?? 0);
        if ($teamId && ! in_array($teamId, WordstatProject::teamIdsForMember($userId), true)) {
            $teamId = 0;
        }
        $attrs = [
            'title' => trim($data['title']),
            'settings' => WordstatSettings::normalize((array) ($data['settings'] ?? [])),
            'team_id' => $teamId ?: null,
        ];
        if (! empty($data['preset_id'])) {
            $preset = WordstatPreset::query()->where('user_id', $userId)->findOrFail((int) $data['preset_id']);
            $preset->update($attrs);
        } else {
            $preset = WordstatPreset::query()->create($attrs + ['user_id' => $userId]);
        }

        return response()->json(['ok' => true, 'message' => __('Пресет сохранён'), 'preset' => [
            'id' => $preset->id, 'title' => $preset->title, 'settings' => $preset->settings, 'team_id' => $preset->team_id, 'mine' => true,
        ]]);
    }

    public function destroyPreset(int $presetId): JsonResponse
    {
        $preset = WordstatPreset::query()->where('user_id', (int) Auth::id())->findOrFail($presetId);
        $preset->delete();

        return response()->json(['ok' => true, 'message' => __('Пресет удалён')]);
    }

    private function project(int $id): WordstatProject
    {
        $project = WordstatProject::query()->findOrFail($id);
        abort_unless($project->isAccessibleBy((int) Auth::id()), 404);

        return $project;
    }

    private function parseRequest(Request $request, WordstatProject $project): array
    {
        $settings = (array) $request->input('settings', []);
        if (! isset($settings['region_ids']) || $settings['region_ids'] === []) {
            $settings['region_ids'] = (array) $project->region_ids;
        }
        $settings = WordstatSettings::normalize($settings);
        $parsed = WordstatInputParser::parse(
            (string) $request->input('text', ''),
            (string) $request->input('group', WordstatGroup::DEFAULT_NAME) ?: WordstatGroup::DEFAULT_NAME,
            (int) config('cabinet-wordstat-keywords.max_phrase_length', 400),
            (int) config('cabinet-wordstat-keywords.max_phrase_words', 7)
        );

        return [$parsed, $settings];
    }

    private function runPayload(WordstatRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'seeds_total' => (int) $run->seeds_total,
            'seeds_done' => (int) $run->seeds_done,
            'requests_estimated' => (int) $run->requests_estimated,
            'requests_used' => (int) $run->requests_used,
            'phrases_added' => (int) $run->phrases_added,
            'error' => $run->error ? __('Сбор частотности временно недоступен, попробуйте позже') : null,
            'active' => $run->isActive(),
        ];
    }

    private function monthRequests(int $userId): int
    {
        return WordstatRequestLog::query()->where('user_id', $userId)->where('created_at', '>=', now()->startOfMonth())->count();
    }
}
