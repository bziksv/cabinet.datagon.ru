<?php

namespace App\Http\Controllers;


use App\Classes\Monitoring\Queues\OccurrenceDispatch;
use App\Jobs\EnqueueMonitoringOccurrenceJob;
use App\Jobs\EnqueueMonitoringOccurrenceKeysJob;
use App\Monitoring\Services\MonitoringOccurrenceCountService;
use App\Monitoring\Services\MonitoringUserService;
use App\MonitoringProject;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringOccurrenceController extends Controller
{
    protected $user;

    /** @var MonitoringOccurrenceCountService */
    private $countService;

    public function __construct(MonitoringOccurrenceCountService $countService)
    {
        $this->countService = $countService;

        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            return $next($request);
        });
    }

    public function estimate($id)
    {
        $this->authorize('update_occurrence_monitoring');

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project instanceof MonitoringProject) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse queue error'),
            ]);
        }

        if ($project->searchengines()->where('engine', 'yandex')->count() === 0) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence yandex only'),
            ]);
        }

        return response()->json([
            'status' => true,
            'stats' => $this->countService->projectStats($project),
        ]);
    }

    public function estimateKeys(Request $request)
    {
        $this->authorize('update_occurrence_monitoring');

        /** @var User $user */
        $user = $this->user;

        $project = $user->monitoringProjects()->find($request->input('projectId'));
        if (!$project instanceof MonitoringProject) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse queue error'),
            ]);
        }

        $keywordIds = array_values(array_map('intval', (array) $request->input('keys', [])));
        $regionId = (int) $request->input('region');
        $engine = $project->searchengines()->find($regionId);

        if ($engine === null || $keywordIds === []) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse select region'),
            ]);
        }

        if ($engine->engine !== 'yandex') {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence yandex only'),
            ]);
        }

        return response()->json([
            'status' => true,
            'stats' => $this->countService->keysStats($project, $keywordIds, $regionId),
        ]);
    }

    public function update(Request $request)
    {
        $scope = $request->input('scope', 'all');

        return $this->updateByProjectId($request->input('id'), $scope === 'missing');
    }

    public function updateKeys(Request $request)
    {
        $this->authorize('update_occurrence_monitoring');

        /** @var User $user */
        $user = $this->user;

        $project = $user->monitoringProjects()->find($request->input('projectId'));
        if (!$project instanceof MonitoringProject) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse queue error'),
            ]);
        }

        $keywordIds = array_values(array_map('intval', (array) $request->input('keys', [])));
        $regionId = (int) $request->input('region');
        $engine = $project->searchengines()->find($regionId);

        if ($engine === null || $keywordIds === []) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse select region'),
            ]);
        }

        if ($engine->engine !== 'yandex') {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence yandex only'),
            ]);
        }

        if ($userAdmin = (new MonitoringUserService())->getMonitoringAdminUser($project)) {
            $user = $userAdmin;
        }

        $keywords = $project->keywords()->whereIn('id', $keywordIds)->get();
        if ($keywords->isEmpty()) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring keyword delete select one'),
            ]);
        }

        $stats = $this->countService->keysStats($project, $keywords->pluck('id')->all(), $regionId);
        $pairCount = $stats['pairs_all'];

        if ($pairCount === 0) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence nothing to update'),
            ]);
        }

        $queue = new OccurrenceDispatch($user['id'], 'high');
        if (!$queue->reserveForPairs($pairCount)) {
            return $queue->notify();
        }

        EnqueueMonitoringOccurrenceKeysJob::dispatch(
            (int) $project->id,
            $regionId,
            $keywords->pluck('id')->all(),
            'high'
        );

        return $queue->notify();
    }

    protected function updateByProjectId($id, bool $missingOnly = false)
    {
        $this->authorize('update_occurrence_monitoring');

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project instanceof MonitoringProject) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse queue error'),
            ]);
        }

        $regions = $project->searchengines->where('engine', 'yandex');
        if ($regions->isEmpty()) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence yandex only'),
            ]);
        }

        if ($userAdmin = (new MonitoringUserService())->getMonitoringAdminUser($project)) {
            $user = $userAdmin;
        }

        $stats = $this->countService->projectStats($project);
        $pairCount = $missingOnly ? $stats['pairs_missing'] : $stats['pairs_all'];

        if ($pairCount === 0) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence nothing to update'),
            ]);
        }

        $queue = new OccurrenceDispatch($user['id'], 'high');
        if (!$queue->reserveForPairs($pairCount)) {
            return $queue->notify();
        }

        EnqueueMonitoringOccurrenceJob::dispatch((int) $project->id, 'high', $missingOnly);

        return $queue->notify();
    }

}
