<?php

namespace App\Http\Controllers;

use App\MarketingContentItem;
use App\MarketingStage;
use App\MarketingTask;
use App\Services\Marketing\MarketingContentPlanImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «Маркетинг сайта» — план продвижения titlo.ru: этапы, задачи, контент-план с ТЗ.
 * Только Super Admin / admin.
 */
class MarketingAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    // ---------------------------------------------------------------- обзор

    public function index(): View
    {
        $stages = MarketingStage::query()->with('tasks')->orderBy('sort')->orderBy('id')->get();

        $taskCounts = MarketingTask::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $contentCounts = MarketingContentItem::query()
            ->selectRaw('type, status, count(*) c')
            ->groupBy('type', 'status')
            ->get()
            ->groupBy('type')
            ->map(static function ($rows) {
                return $rows->pluck('c', 'status');
            });

        return view('admin.marketing.index', [
            'stages' => $stages,
            'taskCounts' => $taskCounts,
            'contentCounts' => $contentCounts,
            'taskStatuses' => config('cabinet-marketing.task_statuses'),
            'contentStatuses' => config('cabinet-marketing.content_statuses'),
            'contentTypes' => config('cabinet-marketing.content_types'),
        ]);
    }

    // ---------------------------------------------------------------- этапы

    public function createStage(): View
    {
        return $this->stageForm(new MarketingStage(['status' => 'planned', 'priority' => 'P1']));
    }

    public function storeStage(Request $request): RedirectResponse
    {
        $data = $this->validateStage($request);
        $data['sort'] = (int) MarketingStage::query()->max('sort') + 10;
        MarketingStage::query()->create($data);
        flash(__('Marketing saved'))->success();

        return redirect()->route('admin.marketing.index');
    }

    public function editStage(int $id): View
    {
        return $this->stageForm(MarketingStage::query()->findOrFail($id));
    }

    public function updateStage(Request $request, int $id): RedirectResponse
    {
        $stage = MarketingStage::query()->findOrFail($id);
        $stage->update($this->validateStage($request, $stage->id));
        flash(__('Marketing saved'))->success();

        return redirect()->to(route('admin.marketing.index') . '#stage-' . $stage->id);
    }

    public function destroyStage(int $id): RedirectResponse
    {
        MarketingStage::query()->findOrFail($id)->delete();
        flash(__('Marketing deleted'))->success();

        return redirect()->route('admin.marketing.index');
    }

    // ---------------------------------------------------------------- задачи

    public function createTask(Request $request): View
    {
        return $this->taskForm(new MarketingTask([
            'stage_id' => (int) $request->query('stage_id'),
            'status' => 'planned',
            'priority' => 'P1',
        ]));
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $data = $this->validateTask($request);
        $data['sort'] = (int) MarketingTask::query()->where('stage_id', $data['stage_id'])->max('sort') + 10;
        $task = MarketingTask::query()->create($data);
        flash(__('Marketing saved'))->success();

        return redirect()->to(route('admin.marketing.index') . '#stage-' . $task->stage_id);
    }

    public function editTask(int $id): View
    {
        return $this->taskForm(MarketingTask::query()->findOrFail($id));
    }

    public function updateTask(Request $request, int $id): RedirectResponse
    {
        $task = MarketingTask::query()->findOrFail($id);
        $task->update($this->validateTask($request));
        flash(__('Marketing saved'))->success();

        return redirect()->to(route('admin.marketing.index') . '#stage-' . $task->stage_id);
    }

    /** Быстрая смена статуса из списка этапов. */
    public function updateTaskStatus(Request $request, int $id): RedirectResponse
    {
        $task = MarketingTask::query()->findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('cabinet-marketing.task_statuses')))],
        ]);

        $task->status = $data['status'];
        if ($task->status === 'done' && $task->done_at === null) {
            $task->done_at = now()->toDateString();
        }
        $task->save();

        return redirect()->to(route('admin.marketing.index') . '#stage-' . $task->stage_id);
    }

    public function destroyTask(int $id): RedirectResponse
    {
        $task = MarketingTask::query()->findOrFail($id);
        $stageId = $task->stage_id;
        $task->delete();
        flash(__('Marketing deleted'))->success();

        return redirect()->to(route('admin.marketing.index') . '#stage-' . $stageId);
    }

    // ---------------------------------------------------------------- контент-план

    public function content(Request $request): View
    {
        $filters = [
            'type' => (string) $request->query('type', MarketingContentItem::TYPE_TERM),
            'status' => (string) $request->query('status', ''),
            'priority' => (string) $request->query('priority', ''),
            'category' => (string) $request->query('category', ''),
            'top' => (string) $request->query('top', ''),
            'q' => trim((string) $request->query('q', '')),
        ];
        if (!array_key_exists($filters['type'], config('cabinet-marketing.content_types'))) {
            $filters['type'] = MarketingContentItem::TYPE_TERM;
        }

        $query = MarketingContentItem::query()->where('type', $filters['type']);
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['priority'] !== '') {
            $query->where('priority', $filters['priority']);
        }
        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }
        if ($filters['top'] === '1') {
            $query->where('is_top', true);
        }
        if ($filters['q'] !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%';
            $query->where(static function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('aliases', 'like', $like)
                    ->orWhere('main_query', 'like', $like)
                    ->orWhere('modules', 'like', $like);
            });
        }

        $sort = $this->contentSort($request);
        $this->applyContentSort($query, $sort['sort'], $sort['dir']);

        $items = $query->paginate((int) config('cabinet-marketing.per_page', 50))
            ->appends(array_filter(array_merge($filters, $sort['query']), static function ($v) {
                return $v !== '';
            }));

        $categories = MarketingContentItem::query()->where('type', $filters['type'])
            ->whereNotNull('category')->orderBy('category')->distinct()->pluck('category');

        $typeCounts = MarketingContentItem::query()->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');

        return view('admin.marketing.content', [
            'items' => $items,
            'filters' => $filters,
            'sort' => $sort,
            'categories' => $categories,
            'typeCounts' => $typeCounts,
            'contentStatuses' => config('cabinet-marketing.content_statuses'),
            'contentTypes' => config('cabinet-marketing.content_types'),
            'priorities' => config('cabinet-marketing.priorities'),
        ]);
    }

    public function createContent(Request $request): View
    {
        $type = (string) $request->query('type', MarketingContentItem::TYPE_TERM);

        return $this->contentForm(new MarketingContentItem([
            'type' => array_key_exists($type, config('cabinet-marketing.content_types')) ? $type : MarketingContentItem::TYPE_TERM,
            'status' => 'idea',
            'priority' => 'P2',
        ]));
    }

    public function storeContent(Request $request): RedirectResponse
    {
        $data = $this->validateContent($request);
        $item = MarketingContentItem::query()->create($data);
        flash(__('Marketing saved'))->success();

        return redirect()->route('admin.marketing.content.edit', $item->id);
    }

    public function editContent(int $id): View
    {
        return $this->contentForm(MarketingContentItem::query()->findOrFail($id));
    }

    public function updateContent(Request $request, int $id): RedirectResponse
    {
        $item = MarketingContentItem::query()->findOrFail($id);
        $item->update($this->validateContent($request, $item->id));
        flash(__('Marketing saved'))->success();

        return redirect()->route('admin.marketing.content.edit', $item->id);
    }

    public function destroyContent(int $id): RedirectResponse
    {
        $item = MarketingContentItem::query()->findOrFail($id);
        $type = $item->type;
        $item->delete();
        flash(__('Marketing deleted'))->success();

        return redirect()->route('admin.marketing.content', ['type' => $type]);
    }

    public function importContent(MarketingContentPlanImporter $importer): RedirectResponse
    {
        $stats = $importer->importFile((string) config('cabinet-marketing.content_plan_file'));

        flash(__('Marketing import done', [
            'terms' => $stats['terms_created'],
            'articles' => $stats['articles_created'],
            'updated' => $stats['terms_updated'] + $stats['articles_updated'],
        ]))->success();

        return redirect()->route('admin.marketing.content');
    }

    // ---------------------------------------------------------------- helpers

    /** Колонки, по которым можно сортировать контент-план. '' — сортировка по умолчанию. */
    private const CONTENT_SORTS = ['title', 'category', 'priority', 'status', 'updated_at'];

    /**
     * @return array{sort:string,dir:string,query:array<string,string>}
     */
    private function contentSort(Request $request): array
    {
        $sort = (string) $request->query('sort', '');
        if (!in_array($sort, self::CONTENT_SORTS, true)) {
            $sort = '';
        }
        $dir = strtolower((string) $request->query('dir', '')) === 'desc' ? 'desc' : 'asc';
        if ($sort === '') {
            $dir = 'asc';
        }

        return [
            'sort' => $sort,
            'dir' => $dir,
            'query' => $sort === '' ? [] : ['sort' => $sort, 'dir' => $dir],
        ];
    }

    /**
     * По умолчанию: топ → приоритет P0…P3 → название (А–Я, collation БД utf8mb4_unicode_ci).
     * Явная колонка: она первая, далее те же тай-брейкеры.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applyContentSort($query, string $sort, string $dir): void
    {
        switch ($sort) {
            case 'title':
                $query->orderBy('title', $dir);
                break;
            case 'category':
                // пустые категории — в конце при любом направлении
                $query->orderByRaw('category IS NULL')->orderBy('category', $dir);
                break;
            case 'priority':
                $query->orderBy('priority', $dir);
                break;
            case 'status':
                $codes = array_keys((array) config('cabinet-marketing.content_statuses'));
                $placeholders = implode(',', array_fill(0, count($codes), '?'));
                $query->orderByRaw('FIELD(status, ' . $placeholders . ') ' . $dir, $codes);
                break;
            case 'updated_at':
                $query->orderBy('updated_at', $dir);
                break;
        }

        $query->orderByDesc('is_top')->orderBy('priority')->orderBy('title')->orderBy('id');
    }

    private function stageForm(MarketingStage $stage): View
    {
        return view('admin.marketing.stage-form', [
            'stage' => $stage,
            'taskStatuses' => config('cabinet-marketing.task_statuses'),
            'priorities' => config('cabinet-marketing.priorities'),
        ]);
    }

    private function taskForm(MarketingTask $task): View
    {
        return view('admin.marketing.task-form', [
            'task' => $task,
            'stages' => MarketingStage::query()->orderBy('sort')->orderBy('id')->get(['id', 'title']),
            'taskStatuses' => config('cabinet-marketing.task_statuses'),
            'priorities' => config('cabinet-marketing.priorities'),
        ]);
    }

    private function contentForm(MarketingContentItem $item): View
    {
        return view('admin.marketing.content-form', [
            'item' => $item,
            'contentStatuses' => config('cabinet-marketing.content_statuses'),
            'contentTypes' => config('cabinet-marketing.content_types'),
            'priorities' => config('cabinet-marketing.priorities'),
            'categories' => MarketingContentItem::query()->where('type', $item->type)
                ->whereNotNull('category')->orderBy('category')->distinct()->pluck('category'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateStage(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('marketing_stages', 'slug')->ignore($ignoreId)],
            'goal' => 'nullable|string|max:191',
            'description' => 'nullable|string|max:20000',
            'priority' => ['required', Rule::in(config('cabinet-marketing.priorities'))],
            'status' => ['required', Rule::in(array_keys(config('cabinet-marketing.task_statuses')))],
        ]);

        if (empty($data['slug'])) {
            $base = \Illuminate\Support\Str::slug($data['title']) ?: 'stage';
            $slug = mb_substr($base, 0, 56);
            $n = 1;
            while (MarketingStage::query()->where('slug', $slug)->when($ignoreId, static function ($q) use ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            })->exists()) {
                $slug = mb_substr($base, 0, 56) . '-' . (++$n);
            }
            $data['slug'] = $slug;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateTask(Request $request): array
    {
        $data = $request->validate([
            'stage_id' => 'required|integer|exists:marketing_stages,id',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string|max:20000',
            'status' => ['required', Rule::in(array_keys(config('cabinet-marketing.task_statuses')))],
            'priority' => ['required', Rule::in(config('cabinet-marketing.priorities'))],
            'links' => 'nullable|string|max:5000',
            'done_at' => 'nullable|date',
            'commit_ref' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:20000',
        ]);

        if ($data['status'] === 'done' && empty($data['done_at'])) {
            $data['done_at'] = now()->toDateString();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateContent(Request $request, ?int $ignoreId = null): array
    {
        $types = array_keys(config('cabinet-marketing.content_types'));

        $data = $request->validate([
            'type' => ['required', Rule::in($types)],
            'title' => ['required', 'string', 'max:191', Rule::unique('marketing_content_items', 'title')
                ->where('type', (string) $request->input('type'))
                ->ignore($ignoreId)],
            'slug' => 'nullable|string|max:191',
            'aliases' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:191',
            'priority' => ['required', Rule::in(config('cabinet-marketing.priorities'))],
            'status' => ['required', Rule::in(array_keys(config('cabinet-marketing.content_statuses')))],
            'is_top' => 'nullable|boolean',
            'main_query' => 'nullable|string|max:191',
            'secondary_queries' => 'nullable|string|max:20000',
            'intent' => 'nullable|string|max:64',
            'format' => 'nullable|string|max:64',
            'outline' => 'nullable|string|max:50000',
            'lsi_words' => 'nullable|string|max:50000',
            'lsi_source' => 'nullable|string|max:191',
            'lsi_analyzed_at' => 'nullable|date',
            'target_length' => 'nullable|string|max:128',
            'modules' => 'nullable|string|max:500',
            'related_terms' => 'nullable|string|max:5000',
            'url' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:20000',
        ]);

        $data['is_top'] = $request->boolean('is_top');

        return $data;
    }
}
