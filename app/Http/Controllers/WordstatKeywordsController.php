<?php

namespace App\Http\Controllers;

use App\Exports\WordstatPhrasesExport;
use App\Jobs\Wordstat\StartWordstatRun;
use App\Services\Wordstat\WordstatCollector;
use App\Services\Wordstat\WordstatEstimator;
use App\Services\Wordstat\WordstatInputParser;
use App\Services\Wordstat\WordstatSettings;
use App\Support\WordstatKeywordsLimits;
use App\Support\YandexLrRegions;
use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatPhrase;
use App\Wordstat\WordstatPreset;
use App\Wordstat\WordstatProject;
use App\Wordstat\WordstatRequestLog;
use App\Wordstat\WordstatRun;
use App\Wordstat\WordstatRunSeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $groups = $this->groupsWithCounts($project->id);
        $activeRun = WordstatRun::query()->where('project_id', $project->id)->whereIn('status', ['queued', 'running'])->latest('id')->first();

        return view('wordstat-keywords.show', [
            'project' => $project,
            'groups' => $groups,
            'presets' => WordstatPreset::query()->visibleTo($userId)->orderBy('title')->get(),
            'teams' => app(\App\Services\SeoChecklist\SeoChecklistService::class)->teamsForUser($userId),
            'regions' => YandexLrRegions::resolveMany(array_map('strval', (array) $project->region_ids), 100),
            'activeRun' => $activeRun ? $this->runPayload($activeRun) : null,
            'lastRuns' => WordstatRun::query()->where('project_id', $project->id)->latest('id')->limit(5)->get(),
            'monthRequests' => WordstatKeywordsLimits::usedForUserId($userId),
            'monthLimit' => WordstatKeywordsLimits::limitForUser(Auth::user()),
        ]);
    }

    public function destroy(Request $request, int $id, WordstatCollector $collector)
    {
        $project = $this->project($id);
        abort_unless((int) $project->user_id === (int) Auth::id(), 403);
        // Сначала останавливаем сбор, иначе фоновые задачи продолжат тратить запросы
        WordstatRun::query()->where('project_id', $project->id)->whereIn('status', ['queued', 'running'])->get()
            ->each(fn (WordstatRun $run) => $collector->cancelRun($run));
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
        [$parsed, $settings] = $this->parseRequest($request, $project);
        $max = (int) config('cabinet-wordstat-keywords.max_seeds_per_run', 500);
        if (count($parsed['seeds']) === 0) {
            return response()->json(['ok' => false, 'message' => __('Введите хотя бы одну фразу')], 422);
        }
        if (count($parsed['seeds']) > $max) {
            return response()->json(['ok' => false, 'message' => __('Слишком много фраз за один запуск. Максимум: :max', ['max' => number_format($max, 0, '', ' ')])], 422);
        }

        $user = Auth::user();
        $monthLimit = WordstatKeywordsLimits::limitForUser($user);
        if ($monthLimit !== null) {
            $remaining = WordstatKeywordsLimits::remainingForUser($user);
            $estimate = WordstatEstimator::estimate(count($parsed['seeds']), $settings)['total'];
            if ($remaining <= 0) {
                return response()->json(['ok' => false, 'message' => WordstatKeywordsLimits::limitMessage($user)], 422);
            }
            if ($estimate > $remaining) {
                return response()->json([
                    'ok' => false,
                    'message' => __('Недостаточно лимита на этот запуск: нужно до :need запросов, осталось :left из :limit в этом месяце. Уменьшите число фраз или расширение, либо повысьте тариф.', [
                        'need' => number_format($estimate, 0, '', ' '),
                        'left' => number_format($remaining, 0, '', ' '),
                        'limit' => number_format($monthLimit, 0, '', ' '),
                    ]),
                ], 422);
            }
        }

        // Проверка и создание запуска под блокировкой проекта: два участника не запустят сбор одновременно
        $run = DB::transaction(function () use ($project, $parsed, $settings, $collector) {
            WordstatProject::query()->whereKey($project->id)->lockForUpdate()->first();
            if ($this->hasActiveRun($project->id)) {
                return null;
            }

            return $collector->createRun($project, (int) Auth::id(), $parsed['seeds'], $settings);
        });
        if ($run === null) {
            return response()->json(['ok' => false, 'message' => __('Сбор по проекту уже идёт — дождитесь окончания или остановите его')], 422);
        }
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

    public function groups(int $id): JsonResponse
    {
        $project = $this->project($id);
        $groups = $this->groupsWithCounts($project->id)
            ->map(fn ($g) => ['id' => (int) $g->id, 'name' => $g->name, 'count' => (int) $g->phrases_count])->values();

        return response()->json(['ok' => true, 'groups' => $groups]);
    }

    public function phrases(Request $request, int $id): JsonResponse
    {
        $project = $this->project($id);
        $status = in_array($request->query('status'), ['active', 'minus'], true) ? $request->query('status') : 'active';
        $query = WordstatPhrase::query()
            ->where('wordstat_phrases.project_id', $project->id)
            ->where('wordstat_phrases.status', $status);

        $groupIds = $this->requestedGroupIds($request);
        if ($groupIds !== []) {
            $query->whereIn('wordstat_phrases.group_id', $groupIds);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where('wordstat_phrases.phrase', 'like', '%' . $escaped . '%');
        }

        $total = (clone $query)->count();
        $perPage = max(1, min(500, (int) $request->query('per_page', 200)));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($lastPage, (int) $request->query('page', 1)));

        $sortKey = in_array($request->query('sort'), ['freq', 'phrase', 'group'], true) ? $request->query('sort') : 'freq';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        if ($sortKey === 'phrase') {
            $query->orderBy('wordstat_phrases.phrase', $dir)->orderBy('wordstat_phrases.id');
        } elseif ($sortKey === 'group') {
            $query->leftJoin('wordstat_groups', 'wordstat_groups.id', '=', 'wordstat_phrases.group_id')
                ->orderBy('wordstat_groups.name', $dir)
                ->orderByDesc('wordstat_phrases.freq')
                ->orderBy('wordstat_phrases.id');
        } else {
            $query->orderBy('wordstat_phrases.freq', $dir)->orderBy('wordstat_phrases.id');
        }

        $rows = $query->forPage($page, $perPage)
            ->get([
                'wordstat_phrases.id',
                'wordstat_phrases.phrase',
                'wordstat_phrases.freq',
                'wordstat_phrases.source',
                'wordstat_phrases.depth',
                'wordstat_phrases.group_id',
                'wordstat_phrases.status',
            ]);

        return response()->json([
            'ok' => true,
            'phrases' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ]);
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
                // Удаляем полностью, чтобы можно было очистить и пересобрать; для «не возвращать» есть «Исключить из списка» (minus)
                $affected = $query->delete();
        }

        return response()->json(['ok' => true, 'affected' => (int) $affected]);
    }

    public function storeGroup(Request $request, int $id): JsonResponse
    {
        $project = $this->project($id);
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $request->input('name', ''))), 0, 255);
        if ($name === '') {
            return response()->json(['ok' => false, 'message' => __('Введите название группы')], 422);
        }
        if (WordstatGroup::query()->where('project_id', $project->id)->where('name', $name)->exists()) {
            return response()->json(['ok' => false, 'message' => __('Группа с таким названием уже есть')], 422);
        }
        $group = WordstatGroup::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'position' => (int) WordstatGroup::query()->where('project_id', $project->id)->max('position') + 1,
        ]);

        return response()->json(['ok' => true, 'message' => __('Группа создана'), 'group' => ['id' => (int) $group->id, 'name' => $group->name, 'count' => 0]]);
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
        if ($this->hasActiveRun($project->id)) {
            return $this->groupsLockedResponse();
        }
        $source = WordstatGroup::query()->where('project_id', $project->id)->findOrFail($groupId);
        $target = WordstatGroup::query()->where('project_id', $project->id)->findOrFail((int) $request->input('target_id'));
        if ($source->id === $target->id) {
            return response()->json(['ok' => false, 'message' => __('Выберите другую группу для объединения')], 422);
        }
        WordstatPhrase::query()->where('group_id', $source->id)->update(['group_id' => $target->id]);
        $source->delete();

        return response()->json(['ok' => true, 'message' => __('Группы объединены')]);
    }

    public function destroyGroup(int $id, int $groupId): JsonResponse
    {
        $project = $this->project($id);
        if ($this->hasActiveRun($project->id)) {
            return $this->groupsLockedResponse();
        }
        $group = WordstatGroup::query()->where('project_id', $project->id)->findOrFail($groupId);
        // Удаляем фразы группы полностью, чтобы после удаления неудачной группы её можно было собрать заново
        WordstatPhrase::query()->where('group_id', $group->id)->delete();
        $group->delete();

        return response()->json(['ok' => true, 'message' => __('Группа удалена')]);
    }

    public function export(Request $request, int $id)
    {
        $project = $this->project($id);
        $groupIds = $this->requestedGroupIds($request);
        $name = 'wordstat-' . preg_replace('/[^a-z0-9.-]+/i', '-', $project->domain) . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new WordstatPhrasesExport($project->id, $groupIds), $name);
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

    private function hasActiveRun(int $projectId): bool
    {
        return WordstatRun::query()->where('project_id', $projectId)->whereIn('status', ['queued', 'running'])->exists();
    }

    private function groupsLockedResponse(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => __('Дождитесь окончания сбора или остановите его: во время сбора группы менять нельзя')], 422);
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
            is_string($request->input('text')) ? $request->input('text') : '',
            is_string($request->input('group')) && trim($request->input('group')) !== '' ? trim($request->input('group')) : WordstatGroup::DEFAULT_NAME,
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
            'seeds_failed' => WordstatRunSeed::query()->where('run_id', $run->id)->where('status', WordstatRunSeed::STATUS_FAILED)->count(),
            'error' => $this->runErrorText($run),
            'active' => $run->isActive(),
        ];
    }

    private function runErrorText(WordstatRun $run): ?string
    {
        if (! $run->error) {
            return null;
        }
        $error = mb_strtolower((string) $run->error);
        if (preg_match('/лимит запросов wordstat/u', $error)) {
            return (string) $run->error;
        }
        if (preg_match('/баланс|средств|оплат/u', $error)) {
            return __('Закончился оплаченный лимит проверок частотности. Напишите в поддержку — после пополнения сбор можно повторить');
        }
        if (preg_match('/ключ|id пользователя|заблокир|доступ/u', $error)) {
            return __('Проверка частотности сейчас не настроена. Напишите в поддержку');
        }

        return __('Сбор частотности временно недоступен, попробуйте позже');
    }

    private function requestedGroupIds(Request $request): array
    {
        $raw = (string) ($request->query('group_ids') ?? $request->query('group_id') ?? '');
        $ids = array_filter(array_map('intval', explode(',', $raw)), fn (int $v) => $v > 0);

        return array_values(array_unique(array_slice($ids, 0, 500)));
    }

    private function groupsWithCounts(int $projectId)
    {
        return WordstatGroup::query()->where('project_id', $projectId)->orderBy('position')->orderBy('id')
            ->withCount(['phrases' => fn ($q) => $q->where('status', WordstatPhrase::STATUS_ACTIVE)])
            ->get();
    }

    private function monthRequests(int $userId): int
    {
        return WordstatRequestLog::query()->where('user_id', $userId)->where('created_at', '>=', now()->startOfMonth())->count();
    }
}
