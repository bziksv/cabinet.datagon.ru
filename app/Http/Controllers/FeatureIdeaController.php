<?php

namespace App\Http\Controllers;

use App\FeatureIdea;
use App\FeatureIdeaVote;
use App\Support\FeatureIdeaAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeatureIdeaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View
    {
        $filter = (string) $request->query('tab', 'popular');
        $search = trim((string) $request->query('q', ''));
        $isStaff = FeatureIdeaAccess::isStaff();
        $userId = (int) Auth::id();

        $query = FeatureIdea::query()
            ->with([
                'user' => static function ($q) {
                    $q->select('id', 'name', 'last_name', 'email', 'image');
                },
            ]);

        $this->applyTabFilter($query, $filter, $isStaff, $userId);
        $this->applySearch($query, $search);

        if ($filter === 'popular') {
            $query->orderByDesc('votes_count')->orderByDesc('approved_at');
        } elseif ($filter === 'new') {
            $query->orderByDesc('approved_at')->orderByDesc('created_at');
        } elseif ($filter === 'moderation' && $isStaff) {
            $query->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $ideas = $query->paginate(15)->appends($request->only(['tab', 'q']));

        $votedIds = $this->votedIdeaIdsForPage($ideas);

        return view('ideas.index', [
            'ideas' => $ideas,
            'filter' => $filter,
            'search' => $search,
            'isStaff' => $isStaff,
            'votedIds' => $votedIds,
            'stats' => $this->boardStats($isStaff, $userId),
            'pendingCount' => FeatureIdeaAccess::staffPendingCount(),
        ]);
    }

    public function create(): View
    {
        return view('ideas.create', [
            'isStaff' => FeatureIdeaAccess::isStaff(),
            'pendingCount' => FeatureIdeaAccess::staffPendingCount(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:8', 'max:160'],
            'body' => ['required', 'string', 'min:24', 'max:4000'],
        ]);

        $idea = FeatureIdea::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => FeatureIdea::STATUS_PENDING,
        ]);

        flash()->overlay(__('Your idea was sent for moderation. We will publish it after review.'), __('Thank you'))->success();

        return redirect()->route('ideas.index', ['tab' => 'mine']);
    }

    public function vote(Request $request, FeatureIdea $idea): JsonResponse
    {
        if (!FeatureIdeaAccess::canView($idea)) {
            abort(403);
        }

        if (!FeatureIdeaAccess::canVote($idea)) {
            return response()->json([
                'message' => $idea->isApproved()
                    ? __('You cannot vote for your own idea.')
                    : __('Voting is available only for published ideas.'),
            ], 422);
        }

        $userId = (int) Auth::id();
        $voted = false;
        $count = (int) $idea->votes_count;

        DB::transaction(function () use ($idea, $userId, &$voted, &$count) {
            $existing = FeatureIdeaVote::where('feature_idea_id', $idea->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                $idea->decrement('votes_count');
                $voted = false;
            } else {
                FeatureIdeaVote::create([
                    'feature_idea_id' => $idea->id,
                    'user_id' => $userId,
                ]);
                $idea->increment('votes_count');
                $voted = true;
            }

            $idea->refresh();
            $count = (int) $idea->votes_count;
        });

        return response()->json([
            'voted' => $voted,
            'votes_count' => $count,
            'votes_label' => $this->votesLabel($count),
        ]);
    }

    public function approve(Request $request, FeatureIdea $idea): RedirectResponse
    {
        $this->authorizeModeration($idea);

        $data = $request->validate([
            'moderator_note' => ['nullable', 'string', 'max:500'],
        ]);

        $idea->update([
            'status' => FeatureIdea::STATUS_APPROVED,
            'moderated_by' => Auth::id(),
            'approved_at' => now(),
            'rejected_at' => null,
            'moderator_note' => $data['moderator_note'] ?? null,
        ]);

        flash()->overlay(__('Idea published — users can vote now.'), __('Moderation'))->success();

        return redirect()->route('ideas.index', ['tab' => 'moderation']);
    }

    public function reject(Request $request, FeatureIdea $idea): RedirectResponse
    {
        $this->authorizeModeration($idea);

        $data = $request->validate([
            'moderator_note' => ['nullable', 'string', 'max:500'],
        ]);

        $idea->update([
            'status' => FeatureIdea::STATUS_REJECTED,
            'moderated_by' => Auth::id(),
            'rejected_at' => now(),
            'approved_at' => null,
            'moderator_note' => $data['moderator_note'] ?? null,
        ]);

        flash()->overlay(__('Idea declined.'), __('Moderation'))->info();

        return redirect()->route('ideas.index', ['tab' => 'moderation']);
    }

    private function authorizeModeration(FeatureIdea $idea): void
    {
        if (!FeatureIdeaAccess::canModerate($idea)) {
            abort(403);
        }
    }

    private function applyTabFilter($query, string $filter, bool $isStaff, int $userId): void
    {
        if ($filter === 'mine') {
            $query->where('user_id', $userId);

            return;
        }

        if ($filter === 'moderation' && $isStaff) {
            $query->where('status', FeatureIdea::STATUS_PENDING);

            return;
        }

        if ($filter === 'new') {
            $query->where('status', FeatureIdea::STATUS_APPROVED);

            return;
        }

        $query->where('status', FeatureIdea::STATUS_APPROVED);
    }

    private function applySearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
        $query->where(function ($q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('body', 'like', $like);
        });
    }

    /**
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $ideas
     * @return int[]
     */
    private function votedIdeaIdsForPage($ideas): array
    {
        if (!Auth::check() || $ideas->isEmpty()) {
            return [];
        }

        return FeatureIdeaVote::where('user_id', Auth::id())
            ->whereIn('feature_idea_id', $ideas->pluck('id'))
            ->pluck('feature_idea_id')
            ->map(static function ($id) {
                return (int) $id;
            })
            ->all();
    }

    private function boardStats(bool $isStaff, int $userId): array
    {
        return [
            'published' => (int) FeatureIdea::where('status', FeatureIdea::STATUS_APPROVED)->count(),
            'votes_total' => (int) FeatureIdeaVote::count(),
            'mine' => (int) FeatureIdea::where('user_id', $userId)->count(),
            'pending_staff' => $isStaff
                ? (int) FeatureIdea::where('status', FeatureIdea::STATUS_PENDING)->count()
                : 0,
        ];
    }

    private function votesLabel(int $count): string
    {
        return trans_choice(':count vote|:count votes', $count, ['count' => $count]);
    }
}
