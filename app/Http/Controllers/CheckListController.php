<?php

namespace App\Http\Controllers;

use App\ChecklistNotification;
use App\ChecklistProjectLabels;
use App\Checklist;
use App\CheckListsLabels;
use App\ChecklistStubs;
use App\ChecklistTasks;
use App\Classes\SimpleHtmlDom\HtmlDocument;
use App\DomainMonitoring;
use App\MetaTag;
use App\MonitoringDataTableColumnsProject;
use App\ProjectRelevanceHistory;
use App\User;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckListController extends Controller
{
    public function index()
    {
        $labels = CheckListsLabels::where('user_id', Auth::id())->get();

        return view('checklist.index', compact('labels'));
    }

    public function tasks(Checklist $checklist)
    {
        $checklist->load([
            'labels',
            'tasks' => function ($query) {
                $query->select('id', 'project_id', 'status', 'active_after');
            },
        ]);

        $host = parse_url($checklist->url, PHP_URL_HOST) ?: $checklist->url;
        $labels = $checklist->labels->toArray();
        $checklist = $this->confirmArray([$checklist->toArray()]);
        $allLabels = CheckListsLabels::where('user_id', Auth::id())->get(['id', 'name', 'color'])->toArray();

        return view('checklist.tasks', compact('checklist', 'host', 'labels', 'allLabels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'url' => 'required',
        ], [
            'url.required' => __('A link to the landing page is required.'),
        ]);

        if (!preg_match("~^(?:f|ht)tps?://~i", $request->input('url'))) {
            $fullUrl = "https://" . $request->input('url');
        } else {
            $fullUrl = $request->input('url');
        }

        if (Checklist::where('user_id', Auth::id())->where('url', $fullUrl)->count() > 0) {
            return response()->json([
                'errors' => ['URL' => 'У вас уже есть проект с таким URL']
            ], 422);
        }

        DB::beginTransaction();
        try {
            $client = new Client();
            $response = $client->get($fullUrl);
            if ($response->getStatusCode() === 200) {
                $icon = $this->findIcon($response->getBody()->getContents());

                $project = Checklist::create([
                    'user_id' => Auth::id(),
                    'icon' => $this->saveIcon($icon, $fullUrl),
                    'url' => $fullUrl,
                ]);

                $this->createSubTasks(
                    $request->input('tasks'),
                    $project->id,
                    null,
                    $request->input('projectStartDate'),
                    $request->input('waitDays'),
                );

                if ($request->input('saveStub') === 'all') {
                    $tree = $this->configureStubs($project->id);
                    $data = [];

                    $data[] = [
                        'user_id' => Auth::id(),
                        'tree' => $tree,
                        'type' => 'personal',
                        'checklist_id' => $request->input('dynamicStub') == 1 ? $project->id : null,
                    ];

                    $data[] = [
                        'user_id' => Auth::id(),
                        'tree' => $tree,
                        'type' => 'classic',
                        'checklist_id' => $request->input('dynamicStub') == 1 ? $project->id : null,
                    ];

                    ChecklistStubs::insert($data);

                } else if ($request->input('saveStub') !== 'no') {
                    $tree = $this->configureStubs($project->id);

                    $data = [
                        'user_id' => Auth::id(),
                        'tree' => $tree,
                        'type' => $request->input('saveStub'),
                    ];

                    if ($request->input('dynamicStub') == 1) {
                        $data['checklist_id'] = $project->id;
                    }

                    ChecklistStubs::create($data);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            Log::debug('error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            DB::rollback();

            return response()->json([
                'errors' => [
                    $e->getMessage()
                ]
            ], 422);
        }

        return response()->json([
            'message' => __('Success')
        ], 201);
    }

    public function update(Request $request): string
    {
        $this->createSubTasks($request->input('tasks'), $request->input('projectID'), $request->input('parentTask'));

        ChecklistStubs::where('checklist_id', $request->input('projectID'))
            ->update([
                'tree' => $this->configureStubs($request->input('projectID'))
            ]);

        return 'Успешно';
    }

    public function storeStub(Request $request): string
    {
        if ($request->input('action') === 'all') {
            ChecklistStubs::create([
                'user_id' => Auth::id(),
                'name' => $request->input('name'),
                'tree' => json_encode($request->input('stubs')),
                'type' => 'classic',
            ]);

            ChecklistStubs::create([
                'user_id' => Auth::id(),
                'name' => $request->input('name'),
                'tree' => json_encode($request->input('stubs')),
                'type' => 'personal',
            ]);
        } else {
            ChecklistStubs::create([
                'user_id' => Auth::id(),
                'name' => $request->input('name'),
                'tree' => json_encode($request->input('stubs')),
                'type' => $request->input('action'),
            ]);
        }

        return 'Успешно';
    }

    public function editStub(Request $request): JsonResponse
    {
        ChecklistStubs::where('id', $request->input('id'))
            ->update([
                'name' => $request->input('name')
            ]);

        return response()->json([]);
    }

    public function getChecklists(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $sql = Checklist::where('user_id', $userId)
            ->where('archive', 0);

        $labelName = $request->input('label_name');

        if ($labelName) {
            $labels = CheckListsLabels::where('user_id', $userId)
                ->where('name', 'like', "%$labelName%")
                ->with('checklists')
                ->get();

            $projectIds = [];

            foreach ($labels as $label) {
                $projects = $label->checklists;
                foreach ($projects as $project) {
                    $projectIds[] = $project->id;
                }
            }

            $sql = $sql->whereIn('id', $projectIds);
        }

        if (isset($request->url)) {
            $sql->where('url', 'like', "%$request->url%");
        }

        $countOnPage = (int) $request->input('countOnPage', 3) ?: 3;
        $total = (clone $sql)->count();

        $lists = $sql->skip($request->input('skip', 0))
            ->take($countOnPage)
            ->with('tasks:project_id,status,active_after')
            ->with('labels')
            ->get(['icon', 'url', 'id'])
            ->toArray();

        $hosts = [];
        foreach ($lists as $list) {
            $host = parse_url($list['url'], PHP_URL_HOST);
            if ($host) {
                $hosts[] = $host;
            }
        }

        if ($hosts !== []) {
            $monitoringByHost = Auth::user()->monitoringProjects()
                ->whereIn('url', array_unique($hosts))
                ->get(['id', 'url'])
                ->keyBy('url');

            $statsByProjectId = MonitoringDataTableColumnsProject::query()
                ->whereIn('monitoring_project_id', $monitoringByHost->pluck('id'))
                ->get()
                ->keyBy('monitoring_project_id');

            foreach ($lists as $key => $list) {
                $host = parse_url($list['url'], PHP_URL_HOST);
                $monitoring = $host ? $monitoringByHost->get($host) : null;
                if ($monitoring && $statsByProjectId->has($monitoring->id)) {
                    $lists[$key]['statistics'] = $statsByProjectId->get($monitoring->id);
                }
            }
        }

        $paginate = (int) ceil($total / $countOnPage);

        return response()->json([
            'lists' => $this->confirmArray($lists),
            'paginate' => $paginate
        ]);
    }

    public function getChecklistsKanban(Request $request): JsonResponse
    {
        $projectIds = Checklist::where('user_id', Auth::id())
            ->where('archive', 0)
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return response()->json($this->emptyKanbanPayload());
        }

        $allTasks = ChecklistTasks::whereIn('project_id', $projectIds)
            ->with(['project:id,url,icon,user_id,archive'])
            ->orderBy('id', 'desc')
            ->get();

        $notReadyOrRepeat = static function (ChecklistTasks $task): bool {
            return !in_array($task->status, ['ready', 'repeat'], true);
        };

        $activeForDayBuckets = static function (ChecklistTasks $task): bool {
            return !in_array($task->status, ['ready', 'repeat', 'expired'], true);
        };

        $dateContains = static function ($value, string $needle): bool {
            if ($value === null || $value === '') {
                return false;
            }

            return strpos((string) $value, $needle) !== false;
        };

        $todayStr = Carbon::now()->toDateString();
        $tomorrowStr = Carbon::now()->copy()->addDay()->toDateString();

        $tasks = $allTasks->filter($notReadyOrRepeat)->values();
        $expired = $allTasks->where('status', 'expired')->values();

        $toDayTasks = $allTasks->filter(function (ChecklistTasks $task) use ($activeForDayBuckets, $todayStr, $dateContains) {
            return $activeForDayBuckets($task) && $dateContains($task->deadline, $todayStr);
        })->values();

        $tomorrowTasks = $allTasks->filter(function (ChecklistTasks $task) use ($activeForDayBuckets, $tomorrowStr, $dateContains) {
            return $activeForDayBuckets($task) && $dateContains($task->deadline, $tomorrowStr);
        })->values();

        $today = Carbon::now();
        $tomorrowDate = Carbon::now()->copy()->addDay()->format('d.m.Y');
        $dayOfWeek = strtolower($today->englishDayOfWeek);
        $nextDays = [];

        for ($i = 1; $i <= 7; $i++) {
            $nextDay = $today->copy()->next($dayOfWeek);
            $dayKey = strtolower($nextDay->englishDayOfWeek);
            $dayStr = $nextDay->toDateString();

            $nextDays[$dayKey] = $allTasks->filter(function (ChecklistTasks $task) use ($activeForDayBuckets, $dayStr, $dateContains) {
                return $activeForDayBuckets($task)
                    && ($dateContains($task->deadline, $dayStr) || $dateContains($task->date_start, $dayStr));
            })->values();

            $nextDays[$dayKey . 'Date'] = $nextDay->format('d.m.Y');

            $dayOfWeek = strtolower($nextDay->copy()->addDay()->englishDayOfWeek);
        }

        return response()->json([
            'tasks' => $tasks,
            'expired' => $expired,
            'toDay' => $toDayTasks,
            'todayDate' => Carbon::now()->format('d.m.Y'),
            'tomorrow' => $tomorrowTasks,
            'tomorrowDate' => $tomorrowDate,
            'monday' => $nextDays['monday'] ?? collect(),
            'mondayDate' => $nextDays['mondayDate'] ?? '',
            'mondayDateDate' => $nextDays['mondayDate'] ?? '',
            'tuesday' => $nextDays['tuesday'] ?? collect(),
            'tuesdayDate' => $nextDays['tuesdayDate'] ?? '',
            'wednesday' => $nextDays['wednesday'] ?? collect(),
            'wednesdayDate' => $nextDays['wednesdayDate'] ?? '',
            'thursday' => $nextDays['thursday'] ?? collect(),
            'thursdayDate' => $nextDays['thursdayDate'] ?? '',
            'friday' => $nextDays['friday'] ?? collect(),
            'fridayDate' => $nextDays['fridayDate'] ?? '',
            'saturday' => $nextDays['saturday'] ?? collect(),
            'saturdayDate' => $nextDays['saturdayDate'] ?? '',
            'sunday' => $nextDays['sunday'] ?? collect(),
            'sundayDate' => $nextDays['sundayDate'] ?? '',
        ]);
    }

    private function emptyKanbanPayload(): array
    {
        $todayDate = Carbon::now()->format('d.m.Y');
        $tomorrowDate = Carbon::now()->copy()->addDay()->format('d.m.Y');

        return [
            'tasks' => [],
            'expired' => [],
            'toDay' => [],
            'todayDate' => $todayDate,
            'tomorrow' => [],
            'tomorrowDate' => $tomorrowDate,
            'monday' => [],
            'mondayDate' => '',
            'mondayDateDate' => '',
            'tuesday' => [],
            'tuesdayDate' => '',
            'wednesday' => [],
            'wednesdayDate' => '',
            'thursday' => [],
            'thursdayDate' => '',
            'friday' => [],
            'fridayDate' => '',
            'saturday' => [],
            'saturdayDate' => '',
            'sunday' => [],
            'sundayDate' => '',
        ];
    }

    public function saveChecklistsKanban(Request $request): JsonResponse
    {
        $update = [
            'deadline' => Carbon::parse($request->input('deadline')),
            'status' => $request->input('status')
        ];

        if ($update['status'] === 'expired') {
            $update['deadline'] = Carbon::now();
        }

        ChecklistTasks::where('id', $request->input('id'))->update($update);

        return response()->json([
            'deadline' => $update['deadline']->format('d.m.Y'),
            'status' => __(ucfirst($update['status']))
        ]);
    }

    public function inArchive(Checklist $project)
    {
        if (!User::isUserAdmin() && $project->user_id != Auth::id()) {
            return response()->json([
                'errors' => ['abort' => 'У вас нет прав']
            ], 422);
        }

        $project->update(['archive' => 1]);

        return 'Проект перемещён в архив';
    }

    public function archive(): array
    {
        $lists = Checklist::where('user_id', Auth::id())
            ->with('tasks:project_id,status,active_after')
            ->with('labels')
            ->where('archive', 1)
            ->get(['icon', 'url', 'id'])
            ->toArray();

        return $this->confirmArray($lists);
    }

    public function destroy(Checklist $project)
    {
        if (!User::isUserAdmin() && $project->user_id != Auth::id()) {
            return response()->json([
                'errors' => ['abort' => 'У вас нет прав']
            ], 422);
        }

        if ($project->archive) {
            $project->delete();

            ChecklistStubs::where('checklist_id', $project->id)
                ->update([
                    'checklist_id' => null
                ]);
        }

        return 'Чеклист был удалён';
    }

    public function restore(Checklist $project)
    {
        if (!User::isUserAdmin() && $project->user_id != Auth::id()) {
            return response()->json([
                'errors' => ['abort' => 'У вас нет прав']
            ], 422);
        }

        if ($project->archive) {
            $project->update(['archive' => 0]);
        }

        return 'Чеклист был восстановлен';
    }

    public function createLabel(Request $request)
    {
        if (CheckListsLabels::where('user_id', Auth::id())->where('name', $request->name)->count() > 0) {
            return response()->json([
                'errors' => ['unique' => 'У вас уже существует метка с таким названием']
            ], 422);
        }

        $label = CheckListsLabels::create([
            'user_id' => Auth::id(),
            'color' => $request->color,
            'name' => $request->name,
        ]);

        return [
            'message' => 'Новая метка успешно создана',
            'label' => $label
        ];
    }

    public function removeLabel(CheckListsLabels $label)
    {
        if ($label->user_id !== Auth::id() && !User::isUserAdmin()) {
            return response()->json([
                'errors' => ['abort' => 'У вас нет прав']
            ], 422);
        }

        $label->delete();

        return 'Метка успешно удалена';
    }

    public function editLabel(Request $request): string
    {
        $sql = CheckListsLabels::where('id', $request->id);

        if (!User::isUserAdmin()) {
            $sql = $sql->where('user_id', Auth::id());
        }

        $sql->update([
            $request->type => $request->target
        ]);

        return 'Метка успешно изменена';
    }

    public function createRelation(Request $request)
    {
        if (empty($request->checklistId) || empty($request->labelId)) {
            return response()->json([
                'errors' => ['unique' => 'Вы должны выбрать чеклист и метку']
            ], 422);
        }

        $link = ChecklistProjectLabels::where('checklist_project_id', $request->checklistId)
            ->where('checklist_label_id', $request->labelId)
            ->first();

        if ($link === null) {
            ChecklistProjectLabels::create([
                'checklist_project_id' => $request->checklistId,
                'checklist_label_id' => $request->labelId,
            ]);

            return CheckListsLabels::find($request->labelId);
        } else {
            return response()->json([
                'errors' => ['unique' => 'Метка уже привязана к проекту']
            ], 422);
        }
    }

    public function removeRelation(Request $request): string
    {
        ChecklistProjectLabels::where('checklist_project_id', $request->checkListID)
            ->where('checklist_label_id', $request->labelID)
            ->delete();

        return 'Метка успешно удалена';
    }

    public function getTasks(Request $request): array
    {
        $projectId = (int) $request->input('id');
        $perPage = (int) $request->input('count', 3) ?: 3;
        $skip = (int) $request->input('skip', 0);
        $hasSearch = isset($request->search) && $request->search !== '';

        $sql = ChecklistTasks::where('project_id', $projectId);

        if ($request->sort === 'deactivated') {
            $sql->whereDate('active_after', '<=', Carbon::now());
        }

        if ($hasSearch) {
            $sql->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->sort === 'new-sort') {
            $sql->orderBy('id', 'desc');
        } elseif ($request->sort === 'old-sort') {
            $sql->orderBy('id', 'asc');
        } elseif ($request->sort != 'all') {
            $sql->where('status', $request->sort);
        }

        $checklistModel = Checklist::where('id', $projectId)
            ->where('user_id', Auth::id())
            ->first(['id', 'icon', 'url']);

        if ($checklistModel === null) {
            return [
                'checklist' => [],
                'tasks' => [],
                'paginate' => 0,
            ];
        }

        if ($hasSearch) {
            $paginate = (int) ceil((clone $sql)->count() / $perPage);
            $tasks = $sql->skip($skip)->take($perPage)->get()->toArray();
        } else {
            [$tasks, $paginate] = $this->paginatedTaskTree($sql, $projectId, $skip, $perPage);
        }

        $checklistPayload = array_merge(
            $checklistModel->toArray(),
            $this->checklistTaskStats($projectId)
        );

        return [
            'checklist' => [$checklistPayload],
            'tasks' => $tasks,
            'paginate' => $paginate,
        ];
    }

    public function getTask(ChecklistTasks $task)
    {
        if ($task->project->user_id === Auth::id()) {
            return $task;
        } else {
            return [];
        }
    }

    public function removeRepeatTask(Request $request): JsonResponse
    {
        ChecklistTasks::where('status', 'repeat')
            ->where('id', $request->id)
            ->delete();

        return response()->json([], 200);
    }

    public function removeTask(Request $request): string
    {
        $task = ChecklistTasks::where('id', $request->input('id'))->first();
        $id = $task->project_id;
        $task->delete();

        if (filter_var($request->removeSubTasks, FILTER_VALIDATE_BOOLEAN)) {
            $childIds = [];
            $this->findChildIds($request->input('id'), $childIds);

            ChecklistNotification::whereIn('checklist_task_id', $childIds)->delete();
            ChecklistTasks::whereIn('id', $childIds)->delete();
        } else {
            ChecklistTasks::where('subtask', 1)
                ->where('task_id', $request->input('id'))
                ->update([
                    'subtask' => 0,
                    'task_id' => null
                ]);
        }

        $tasks = ChecklistTasks::where('project_id', $id)
            ->whereDate('active_after', '<=', Carbon::now())
            ->get()
            ->toArray();

        $tree = $this->buildTaskStructure($tasks);

        ChecklistStubs::where('checklist_id', $id)
            ->update([
                'tree' => json_encode($tree)
            ]);

        return 'Успешно удалено';
    }

    public function findChildIds($parentId, &$childIds)
    {
        $children = ChecklistTasks::where('task_id', $parentId)
            ->whereDate('active_after', '<=', Carbon::now())
            ->pluck('id')
            ->toArray();

        foreach ($children as $child) {
            $childIds[] = $child;
            $this->findChildIds($child, $childIds);
        }
    }

    public function editTask(Request $request): JsonResponse
    {
        if (empty($request->value)) {
            return response()->json([
                'errors' => ['not empty' => 'Значение не может быть пустым']
            ], 422);
        }

        if ($request->type === 'deadline') {
            $date = Carbon::parse($request->value);
            $updates = [
                $request->type => $request->value,
            ];

            if ($date->isPast()) {
                $updates['status'] = 'expired';
            }

            ChecklistTasks::where('id', $request->id)->update($updates);

            $notification = ChecklistNotification::where('checklist_task_id', $request->id)->first();

            if (isset($notification)) {
                $notification->update([
                    'deadline' => $request->value
                ]);
            } else {
                ChecklistNotification::create([
                    'checklist_task_id' => $request->id,
                    'user_id' => Auth::id(),
                    'deadline' => $request->value
                ]);
            }

            ChecklistNotification::updateOrCreate(
                ['checklist_task_id' => $request->id],
                ['deadline' => $request->value],
            );

            return response()->json([
                'newStatus' => $updates['status'] ?? 'undefined'
            ]);

        } else {
            ChecklistTasks::where('id', $request->id)
                ->update([
                    $request->type => $request->value
                ]);
        }

        if ($request->type === 'status' && $request->value === 'ready') {
            ChecklistTasks::where('id', $request->id)
                ->update([
                    'end_date' => Carbon::now()
                ]);
        }

        return response()->json();
    }

    public function editRepeatTask(Request $request)
    {
        ChecklistTasks::where('id', $request->id)
            ->where('status', 'repeat')
            ->update([
                $request->name => $request->value
            ]);
    }

    public function addNewTasks(Request $request): string
    {
        $this->createSubTasks($request->input('tasks'), $request->input('id'), $request->input('parentID'));

        $tasks = ChecklistTasks::where('project_id', $request->input('id'))
            ->whereDate('active_after', '<=', Carbon::now())
            ->get()
            ->toArray();

        $tree = $this->buildTaskStructure($tasks);

        ChecklistStubs::where('checklist_id', $request->input('id'))
            ->update([
                'tree' => json_encode($tree)
            ]);

        return 'Успешно';
    }

    public function getStubs()
    {
        return ChecklistStubs::where('user_id', Auth::id())
            ->orWhere('type', 'classic')
            ->orderByDesc('type')
            ->get();
    }

    public function getClassicStubs(Request $request): JsonResponse
    {
        $sql = ChecklistStubs::where('type', 'classic');

        if ($request->input('name')) {
            $sql->where('name', 'like', "%$request->name%");
        }

        $stubs = $sql->skip($request->input('skip', 0))
            ->take($request->input('count', 3))
            ->get();

        $paginate = (int)ceil($sql->count() / $request->input('count', 3));

        return response()->json([
            'stubs' => $stubs,
            'paginate' => $paginate
        ]);
    }

    public function getPersonalStubs(Request $request)
    {
        $sql = ChecklistStubs::where('type', 'personal')
            ->where('user_id', Auth::id());

        if ($request->input('name')) {
            $sql->where('name', 'like', "%$request->name%");
        }

        $stubs = $sql->skip($request->input('skip', 0))
            ->take($request->input('count', 3))
            ->get();

        $paginate = (int)ceil($sql->count() / $request->input('count', 3));

        return response()->json([
            'stubs' => $stubs,
            'paginate' => $paginate
        ]);
    }

    public function removeStub(ChecklistStubs $stub): string
    {
        if ($stub->classic && User::isUserAdmin()) {
            $stub->delete();
        } else if ($stub->user_id === Auth::id()) {
            $stub->delete();
        }

        return 'Успешно';
    }

    public function relevanceProjects()
    {
        $projects = ProjectRelevanceHistory::where('user_id', Auth::id())->get()->pluck('name');

        foreach ($projects as $key => $project) {
            if (Checklist::where('user_id', Auth::id())->where('url', "https://$project")->count() > 0) {
                unset($projects[$key]);
            }
        }

        return $projects;
    }

    public function metaTagsProjects()
    {
        $projects = MetaTag::where('user_id', Auth::id())->get()->pluck('links');

        foreach ($projects as $key => $project) {
            if (Checklist::where('user_id', Auth::id())->where('url', "https://$project")->count() > 0) {
                unset($projects[$key]);
            }
        }

        return $projects;
    }

    public function monitoringProjects()
    {
        $projects = Auth::user()->monitoringProjects->pluck('url');

        foreach ($projects as $key => $project) {
            if (Checklist::where('user_id', Auth::id())->where('url', "https://$project")->count() > 0) {
                unset($projects[$key]);
            }
        }

        return $projects;
    }

    public function monitoringSites()
    {
        $projects = DomainMonitoring::where('user_id', Auth::id())->get()->pluck('project_name');

        foreach ($projects as $key => $project) {
            if (Checklist::where('user_id', Auth::id())->where('url', "https://$project")->count() > 0) {
                unset($projects[$key]);
            }
        }

        return $projects;
    }

    public function multiplyCreate(Request $request): JsonResponse
    {
        $fails = [];
        foreach ($request->urls as $url) {
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                $fullUrl = "https://" . $url;
            } else {
                $fullUrl = $url;
            }

            try {
                $client = new Client();
                $response = $client->get($fullUrl);
                if ($response->getStatusCode() === 200) {
                    $icon = $this->findIcon($response->getBody()->getContents());
                    Checklist::create([
                        'user_id' => Auth::id(),
                        'icon' => $this->saveIcon($icon, $fullUrl),
                        'url' => $fullUrl,
                    ]);
                }
            } catch (Throwable $exception) {
                $fails[] = "<u><a href='$fullUrl' target='_blank'>$fullUrl</a></u> не удалось подключится, проект не был сохранён";
            }
        }

        return response()->json([
            'message' => 'Новые проекты успешно добавлены',
            'fails' => $fails
        ]);
    }

    public function getNotifications()
    {
        return ChecklistNotification::where('status', '!=', 'wait')
            ->where('user_id', Auth::id())
            ->orderBy('status')
            ->with('task.project')
            ->get();
    }

    public function readNotification(ChecklistNotification $notification): JsonResponse
    {
        $notification->update([
            'status' => 'read'
        ]);

        return response()->json([]);
    }

    public function deleteNotification(ChecklistNotification $notification): JsonResponse
    {
        $notification->delete();

        return response()->json();
    }

    private function createSubTasks($tasks, $projectId, $taskId = null, $projectStartDate = null, $waitDays = null): void
    {
        foreach ($tasks as $task) {
            $task = $task[0] ?? $task;
            $deadline = isset($task['deadline']) ? Carbon::parse($task['deadline'])->toDateTimeString() : Carbon::now()->toDateTimeString();

            $object = [
                'project_id' => $projectId,
                'name' => $task['name'] ?? 'Без названия',
                'status' => $task['status'],
                'description' => $task['description'] ?? '',
                'date_start' => isset($task['start']) ? Carbon::parse($task['start'])->toDateTimeString() : Carbon::now()->toDateTimeString(),
                'deadline' => $deadline,
            ];

            if ($task['status'] === 'deactivated') {
                $object['active_after'] = $task['active_after'];
                $object['date_start'] = $task['active_after'];
                $object['deadline'] = Carbon::parse($task['active_after'])->addDays($task['count_days']);
            } else if ($task['status'] === 'repeat') {
                $object['weekends'] = $task['weekends'];

                if ($task['weekends']) {
                    $object['date_start'] = Carbon::parse($task['active_after'])->addWeekdays($task['repeat_after']);
                } else {
                    $object['date_start'] = Carbon::parse($task['active_after'])->addDays($task['repeat_after']);
                }

                $object['deadline_every'] = $task['count_days'];
                $object['repeat_every'] = $task['repeat_after'];
            }

            if (isset($taskId)) {
                $object['subtask'] = 1;
                $object['task_id'] = $taskId;
            }

            if ($projectStartDate === 'wait') {
                $object['date_start'] = Carbon::parse($object['date_start'])->addDays($waitDays);
                $object['deadline'] = Carbon::parse($object['deadline'])->addDays($waitDays);
                if (isset($object['active_after'])) {
                    $object['active_after'] = Carbon::parse($object['active_after'])->addDays($waitDays);
                }
            }

            $newRecord = ChecklistTasks::create($object);

            ChecklistNotification::create([
                'checklist_task_id' => $newRecord->id,
                'user_id' => Auth::id(),
                'deadline' => $deadline
            ]);

            if (isset($task['subtasks'])) {
                $this->createSubTasks(
                    $task['subtasks'],
                    $projectId,
                    $newRecord->id,
                    $projectStartDate,
                    $waitDays
                );
            }
        }
    }

    private function findIcon($html)
    {
        $document = new HtmlDocument();
        $document->load(mb_strtolower($html));

        $elem = $document->find('link[rel="shortcut icon"]');

        if ($elem === []) {
            $elem = $document->find('link[rel="icon"]');
        }

        return $elem;
    }

    private function saveIcon($icon, $fullUrl): ?string
    {
        $md5 = md5(microtime(true));
        $path = "/checklist/$md5.jpg";

        if (count($icon) > 0 && $icon[0]->attr['href']) {
            if (filter_var($icon[0]->attr['href'], FILTER_VALIDATE_URL)) {
                $faviconData = file_get_contents($icon[0]->attr['href']);
            } else if (filter_var("https://" . parse_url($fullUrl)['host'] . $icon[0]->attr['href'], FILTER_VALIDATE_URL)) {
                $faviconData = file_get_contents("https://" . parse_url($fullUrl)['host'] . $icon[0]->attr['href']);
            } else {
                $faviconData = 'no data';
            }

            Storage::put($path, $faviconData);
        }

        return $path;
    }

    private function confirmArray($lists): array
    {
        foreach ($lists as $key => $list) {
            if (isset($list['tasks_total'])) {
                continue;
            }

            $deactivated = 0;
            $expired = 0;
            $repeat = 0;
            $inWork = 0;
            $ready = 0;
            $new = 0;

            foreach ($list['tasks'] ?? [] as $task) {
                if ($task['status'] === 'in_work') {
                    $inWork++;
                } else if ($task['status'] === 'ready') {
                    $ready++;
                } else if ($task['status'] === 'new') {
                    $new++;
                } else if ($task['status'] === 'deactivated') {
                    $deactivated++;
                } else if ($task['status'] === 'expired') {
                    $expired++;
                } else if ($task['status'] === 'repeat') {
                    $repeat++;
                }
            }

            $lists[$key]['inactive'] = $deactivated;
            $lists[$key]['expired'] = $expired;
            $lists[$key]['repeat'] = $repeat;
            $lists[$key]['ready'] = $ready;
            $lists[$key]['work'] = $inWork;
            $lists[$key]['new'] = $new;
            $lists[$key]['tasks_total'] = count($list['tasks'] ?? []);
        }

        return $lists;
    }

    private function checklistTaskStats(int $projectId): array
    {
        $counts = ChecklistTasks::where('project_id', $projectId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'inactive' => (int) ($counts['deactivated'] ?? 0),
            'expired' => (int) ($counts['expired'] ?? 0),
            'repeat' => (int) ($counts['repeat'] ?? 0),
            'ready' => (int) ($counts['ready'] ?? 0),
            'work' => (int) ($counts['in_work'] ?? 0),
            'new' => (int) ($counts['new'] ?? 0),
            'tasks_total' => (int) $counts->sum(),
        ];
    }

    private function paginatedTaskTree($baseQuery, int $projectId, int $skip, int $perPage): array
    {
        $rootQuery = (clone $baseQuery)->where('subtask', 0);
        $paginate = (int) ceil((clone $rootQuery)->count() / $perPage);

        $roots = (clone $rootQuery)->skip($skip)->take($perPage)->get()->toArray();

        if ($roots === []) {
            return [[], $paginate];
        }

        $flat = $roots;
        $parentIds = array_column($roots, 'id');

        while ($parentIds !== []) {
            $children = ChecklistTasks::where('project_id', $projectId)
                ->whereIn('task_id', $parentIds)
                ->get()
                ->toArray();

            if ($children === []) {
                break;
            }

            $flat = array_merge($flat, $children);
            $parentIds = array_column($children, 'id');
        }

        return [$this->buildTaskStructure($flat), $paginate];
    }

    private function buildTaskStructure($tasks, $parentId = null): array
    {
        $result = [];

        foreach ($tasks as $item) {
            if ($item['task_id'] === $parentId) {
                $task = [
                    'id' => $item['id'],
                    'project_id' => $item['project_id'],
                    'name' => $item['name'],
                    'status' => $item['status'],
                    'description' => $item['description'],
                    'subtask' => $item['subtask'],
                    'weekends' => $item['weekends'],
                    'task_id' => $item['task_id'],
                    'repeat_every' => $item['repeat_every'] ?? '',
                    'deadline_every' => $item['deadline_every'] ?? '',
                    'date_start' => $item['date_start'],
                    'deadline' => $item['deadline'],
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at']
                ];

                $subtasks = $this->buildTaskStructure($tasks, $item['id']);
                if (!empty($subtasks)) {
                    $task['subtasks'] = $subtasks;
                }

                $result[] = $task;
            }

        }

        return $result;
    }

    private function configureStubs($id)
    {
        $tasks = ChecklistTasks::where('project_id', $id)
            ->whereDate('active_after', '<=', Carbon::now())
            ->get()
            ->toArray();

        return json_encode($this->buildTaskStructure($tasks));
    }

    public function getRepeatTasks(Request $request)
    {
        $columnIndex = $request->input('order.0.column');
        $columnSortOrder = $request->input('order.0.dir');
        $columnName = $request['columns'][$columnIndex]['name'];

        $id = Checklist::where('user_id', Auth::id())->pluck('id');

        $totalRecords = ChecklistTasks::whereIn('project_id', $id)
            ->where('status', 'repeat')
            ->count();

        $records = ChecklistTasks::whereIn('project_id', $id)
            ->orderBy($columnName, $columnSortOrder)
            ->where('status', 'repeat')
            ->with('project');

        foreach ($request['columns'] as $column) {
            $search = $column['search']['value'];
            if (isset($search)) {
                $columnSearch = $column['name'];

                switch ($columnSearch) {
                    case 'name':
                    case 'description':
                    case 'date_start':
                    case 'deadline_every':
                    case 'repeat_every':
                    case 'weekends':
                        $records->where($columnSearch, 'like', "%$search%");
                        break;
                    default:
                        break;
                }
            }
        }

        $start = $request->input('start');
        $pageNumber = floor($start / $request->input('length')) + 1;
        $records = $records->paginate($request->input('length'), ['*'], 'page', $pageNumber);

        $aaData = [];
        foreach ($records as $record) {
            $aaData[] = [
                'id' => $record->id,
                'name' => $record->name,
                'description' => $record->description,
                'date_start' => $record->date_start,
                'deadline_every' => $record->deadline_every,
                'repeat_every' => $record->repeat_every,
                'weekends' => $record->weekends,
                'project' => $record->project,
            ];
        }

        return json_encode([
            'draw' => (int)$request['draw'],
            'iTotalRecords' => $totalRecords,
            'iTotalDisplayRecords' => $totalRecords,
            'aaData' => $aaData
        ]);
    }

    public function storeRepeatTasks(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required',
            'date_start' => 'required',
            'repeat_every' => 'required',
            'deadline_every' => 'required',
            'ids' => 'array|required',
        ], [
            'name.required' => 'Название задачи не может быть пустым',
            'date_start.required' => 'Вы забыли указать дату первого запуска',
            'repeat_every.required' => 'Вы забыли указать промежуток повторения задачи',
            'deadline_every.required' => 'Вы забыли указать количество дней на выполнение',
            'ids.required' => 'Вам нужно указать список ваших чеклистов',
        ]);

        try {
            DB::beginTransaction();

            $insert = [];

            foreach ($request->ids as $id) {
                $insert[] = [
                    'project_id' => $id,
                    'name' => $request->name,
                    'description' => $request->description,
                    'repeat_every' => $request->repeat_every,
                    'deadline_every' => $request->deadline_every,
                    'weekends' => $request->weekends,
                    'date_start' => $request->date_start,
                    'status' => 'repeat',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];
            }

            ChecklistTasks::insert($insert);
            DB::commit();

            return response()->json([
                'message' => __('Success')
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getAllChecklists()
    {
        return Checklist::where('user_id', Auth::id())->get();
    }
}
