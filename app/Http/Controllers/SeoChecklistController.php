<?php

namespace App\Http\Controllers;

use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistItemNote;
use App\SeoChecklist\SeoChecklistProject;
use App\Services\SeoChecklist\SeoChecklistService;
use App\Support\HomeUserSites;
use App\Support\SeoChecklistDefaultTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SeoChecklistController extends Controller
{
    /** @var SeoChecklistService */
    private $service;

    public function __construct(SeoChecklistService $service)
    {
        $this->service = $service;
    }

    /**
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        $this->service->ensureSystemTemplate();

        $projects = SeoChecklistProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();

        $sitesPayload = HomeUserSites::forUser($userId);
        $existingDomains = $projects->pluck('domain')->all();
        $availableDomains = [];
        foreach (($sitesPayload['sites'] ?? []) as $site) {
            $domain = (string) ($site['domain'] ?? '');
            if ($domain !== '' && !in_array($domain, $existingDomains, true)) {
                $availableDomains[] = $domain;
            }
        }

        return view('pages.seo-checklist', [
            'projects' => $projects,
            'availableDomains' => $availableDomains,
            'stages' => SeoChecklistDefaultTemplate::stages(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $domain = (string) $request->input('domain', '');
        $result = $this->service->createProject($userId, $domain);

        if (empty($result['ok']) || empty($result['project'])) {
            return redirect()
                ->route('pages.seo-checklist')
                ->with('error', $result['message'] ?? __('Could not create SEO checklist'));
        }

        return redirect()
            ->route('pages.seo-checklist.show', ['id' => $result['project']->id])
            ->with('success', __('SEO checklist created'));
    }

    /**
     * @return View|RedirectResponse
     */
    public function show(int $id)
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $items = $project->items()->whereNull('parent_id')->with('notes')->get();
        $stagesMeta = SeoChecklistDefaultTemplate::stages();
        $grouped = [];
        foreach ($items as $item) {
            $key = $item->stage_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'title' => $stagesMeta[$key]['title'] ?? $key,
                    'sort' => (int) ($stagesMeta[$key]['sort'] ?? $item->stage_sort),
                    'items' => [],
                    'done' => 0,
                    'total' => 0,
                ];
            }
            $grouped[$key]['items'][] = $item;
            $grouped[$key]['total']++;
            if (in_array($item->status, ['done', 'skip'], true)) {
                $grouped[$key]['done']++;
            }
        }
        uasort($grouped, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        return view('pages.seo-checklist-show', [
            'project' => $project,
            'stages' => array_values($grouped),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function updateItemStatus(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        /** @var SeoChecklistItem|null $item */
        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $status = (string) $request->input('status', '');
        if (in_array($status, ['skip', 'blocked'], true)) {
            $note = trim((string) $request->input('note', ''));
            if ($note === '') {
                return response()->json([
                    'ok' => false,
                    'message' => __('Comment required for this status'),
                ], 422);
            }
            SeoChecklistItemNote::query()->create([
                'item_id' => $item->id,
                'user_id' => (int) Auth::id(),
                'body' => $note,
            ]);
        }

        if (!$this->service->setItemStatus($item, $status, (int) Auth::id())) {
            return response()->json(['ok' => false, 'message' => __('Invalid status')], 422);
        }

        $project->refresh();

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $item->id,
                'status' => $item->status,
                'done_at' => $item->done_at ? $item->done_at->format('d.m.Y H:i') : null,
            ],
            'progress' => [
                'done' => (int) $project->progress_done,
                'total' => (int) $project->progress_total,
            ],
        ]);
    }

    public function addNote(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        $item = $project->items()->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => __('Task not found')], 404);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return response()->json(['ok' => false, 'message' => __('Note cannot be empty')], 422);
        }

        $note = SeoChecklistItemNote::query()->create([
            'item_id' => $item->id,
            'user_id' => (int) Auth::id(),
            'body' => $body,
        ]);
        $project->forceFill(['last_activity_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'note' => [
                'id' => $note->id,
                'body' => $note->body,
                'created_at' => $note->created_at->format('d.m.Y H:i'),
            ],
        ]);
    }

    public function addSubtask(Request $request, int $id, int $itemId): JsonResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return response()->json(['ok' => false, 'message' => __('Project not found')], 404);
        }

        /** @var SeoChecklistItem|null $parent */
        $parent = $project->items()->where('id', $itemId)->first();
        if (!$parent || !$parent->allows_subtasks) {
            return response()->json(['ok' => false, 'message' => __('Subtasks not allowed')], 422);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return response()->json(['ok' => false, 'message' => __('Title required')], 422);
        }

        $sort = (int) $project->items()->where('parent_id', $parent->id)->max('sort') + 10;
        $child = SeoChecklistItem::query()->create([
            'project_id' => $project->id,
            'parent_id' => $parent->id,
            'code' => $parent->code . '_sub_' . time() . '_' . mt_rand(100, 999),
            'stage_key' => $parent->stage_key,
            'stage_sort' => $parent->stage_sort,
            'sort' => $sort,
            'title' => $title,
            'help' => null,
            'role' => $parent->role,
            'is_important' => false,
            'allows_subtasks' => false,
            'status' => 'todo',
            'links_json' => [],
        ]);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $child->id,
                'title' => $child->title,
                'status' => $child->status,
                'parent_id' => $parent->id,
            ],
        ]);
    }

    public function archive(int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-checklist')->with('error', __('Project not found'));
        }

        $project->status = 'archived';
        $project->save();

        return redirect()->route('pages.seo-checklist')->with('success', __('SEO checklist archived'));
    }

    private function findOwnedProject(int $id): ?SeoChecklistProject
    {
        return SeoChecklistProject::query()
            ->where('id', $id)
            ->where('user_id', (int) Auth::id())
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            'todo' => __('To do'),
            'doing' => __('In progress'),
            'done' => __('Done'),
            'skip' => __('Skipped'),
            'blocked' => __('Blocked'),
        ];
    }
}
