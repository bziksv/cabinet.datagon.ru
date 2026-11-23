<?php

namespace App\Http\Controllers;

use App\Jobs\Wordstat\StartWordstatRun;
use App\Services\Wordstat\WordstatCollector;
use App\Services\Wordstat\WordstatEstimator;
use App\Services\Wordstat\WordstatInputParser;
use App\Services\Wordstat\WordstatSettings;
use App\Support\YandexLrRegions;
use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatPreset;
use App\Wordstat\WordstatProject;
use App\Wordstat\WordstatRequestLog;
use App\Wordstat\WordstatRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
