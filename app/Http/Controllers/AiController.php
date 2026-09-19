<?php

namespace App\Http\Controllers;

use App\AiGenerationHistory;
use App\Jobs\AIGeneration\GenerationAnnouncementQueue;
use App\Jobs\AIGeneration\GenerationCategoryQueue;
use App\ProjectRelevanceHistory;
use App\Relevance;
use App\RelevanceHistory;
use App\RelevanceHistoryResult;
use App\Support\AiGenerationLocalQueueGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiController extends Controller
{
    public function story()
    {
        $isAllHistory = request()->is('*/all-history');
        $demoAutoOpen = \App\Support\DemoCabinet::isCurrentUser()
            && (bool) \App\Support\DemoCabinet::aiGenerationShowcase();

        $tokenStats = \App\Support\AiDeepSeekTokenStats::forUser((int) Auth::id());
        $tokenLeaderboard = null;

        return view('ai-generation.story', compact('isAllHistory', 'demoAutoOpen', 'tokenStats', 'tokenLeaderboard'));
    }

    public function getHistoryJson(Request $request)
    {
        $isAllHistory = $request->input('scope') === 'all';
        if ($isAllHistory && !\App\User::isUserAdmin()) {
            abort(403);
        }

        $period = strtolower(trim((string) $request->input('period', 'all')));
        if (!isset(\App\Support\AiDeepSeekTokenStats::periodPresets()[$period])) {
            $period = 'all';
        }

        $baseQuery = AiGenerationHistory::query();
        if (!$isAllHistory) {
            $baseQuery->where('user_id', Auth::id());
        } else {
            $baseQuery->with(['user:id,name,email']);
        }
        \App\Support\AiDeepSeekTokenStats::applyPeriod($baseQuery, $period);

        $recordsTotal = (clone $baseQuery)->count();

        $query = clone $baseQuery;
        if ($searchValue = $request->input('search.value')) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('prompt', 'LIKE', "%{$searchValue}%")
                    ->orWhere('parrameters', 'LIKE', "%{$searchValue}%")
                    ->orWhereHas('user', function ($userQuery) use ($searchValue) {
                        $userQuery->where('email', 'LIKE', "%{$searchValue}%");
                    });
            });
        }

        $recordsFiltered = (clone $query)->count();

        $limit = (int) $request->input('length', 10);
        $start = (int) $request->input('start', 0);

        $cols = ['id', 'user_id', 'used_tokens', 'status', 'prompt', 'result', 'parrameters', 'created_at'];
        if (Schema::hasColumn('ai_generation_histories', 'prompt_tokens')) {
            $cols[] = 'prompt_tokens';
            $cols[] = 'completion_tokens';
        }

        $items = (clone $query)
            ->orderBy('created_at', 'desc')
            ->offset($start)
            ->limit($limit)
            ->get($cols);

        $data = $items->map(function ($item) use ($isAllHistory) {
            $usedTokens = (int) ($item->used_tokens ?? 0);
            $costUsd = \App\Support\AiDeepSeekTokenStats::costUsd($usedTokens);

            return [
                'id' => $item->id,
                'user_info' => $isAllHistory ? [
                    'id' => $item->user->id ?? '?',
                    'name' => $item->user->name ?? '?',
                    'email' => $item->user->email ?? '',
                ] : null,
                'used_tokens' => $usedTokens,
                'used_tokens_fmt' => number_format($usedTokens, 0, '', ' '),
                'cost_usd' => $costUsd,
                'cost_fmt' => \App\Support\AiDeepSeekTokenStats::formatUsd($costUsd, 4),
                'prompt_tokens' => (int) ($item->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($item->completion_tokens ?? 0),
                'source' => $item->parrameters['source'] ?? '',
                'status' => $item->status,
                'date' => $item->created_at->format('d.m.Y H:i'),
                'prompt' => $item->prompt,
                'result' => $item->result,
                'keywords' => $item->parrameters['keywords'] ?? [],
                'stopwords' => $item->parrameters['stopwords'] ?? [],
                'link' => $item->parrameters['link'] ?? '',
            ];
        });

        $payload = [
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'period' => $period,
        ];

        if (!$isAllHistory) {
            $payload['token_stats'] = \App\Support\AiDeepSeekTokenStats::forUser((int) Auth::id(), $period);
        } else {
            $payload['token_leaderboard'] = \App\Support\AiDeepSeekTokenStats::adminLeaderboard(100, $period);
        }

        return response()->json($payload);
    }

    public function prompt()
    {
        $demoShowcase = null;
        if (\App\Support\DemoCabinet::isCurrentUser()) {
            $demoShowcase = \App\Support\DemoCabinet::aiGenerationShowcase();
        }

        return view('ai-generation.prompt', compact('demoShowcase'));
    }

    public function getProjects()
    {
        $projects = ProjectRelevanceHistory::where('user_id', Auth::id())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($projects);
    }

    public function generatePrompt(Request $request)
    {
        $data = $request->validate([
            'id'           => 'nullable|integer',
            'link'         => 'required|url',
            'keywords'     => 'array',
            'stopwords'    => 'array',
            'note'         => 'nullable|string',
            'mode'         => 'required|string|in:new,regenerate',
            'current_text' => 'nullable|string',
            'source'       => 'required|string|in:parse_html,ai_database',
            'prompt'       => 'required|string',
        ]);

        $service = app(\App\Services\deepseek\prompts\PromptService::class);
        $prompt = '';

        if ($data['mode'] == 'new') {
            $prompt = $service->adaptivePrompt(
                $data['link'],
                $data['note'] ?? null,
                $data['prompt']
            );
        } else {
            $record = AiGenerationHistory::where('user_id', Auth::id())
                ->where('id', $data['id'])
                ->where('status', AiGenerationHistory::COMPLETED)
                ->first();

            if ($record) {
                $prompt = $service->regenerateAdaptivePrompt(
                    $record->prompt,
                    $data['current_text'] ?? '',
                    $data['note'] ?? null
                );
            }
        }

        $record = AiGenerationHistory::create([
            'user_id'     => Auth::id(),
            'parrameters' => $data,
            'prompt'      => $prompt,
            'type'        => AiGenerationHistory::TYPE_CATEGORY,
            'status'      => AiGenerationHistory::PENDING,
        ]);

        GenerationCategoryQueue::dispatch($record)->onQueue('ai_generation');
        AiGenerationLocalQueueGuard::ensureWorkers();

        return response()->json([
            'status'    => 'ok',
            'record_id' => $record->id,
        ]);
    }

    public function getResult($recordId)
    {
        $record = AiGenerationHistory::where('id', $recordId)
            ->whereIn('status', [AiGenerationHistory::COMPLETED, AiGenerationHistory::FAILED])
            ->where('user_id', Auth::id())
            ->first();

        return response()->json([
            'status' => 'ok',
            'record' => $record,
        ]);
    }

    public function relevanceHistory($projectId)
    {
        $history = ProjectRelevanceHistory::where('id', $projectId)->first();

        if($history) {
            return $history->stories()->orderBy('id', 'desc')->get([
                'id', 'phrase', 'main_link', 'created_at', 'last_check'
            ]);
        }

        return [];
    }

    public function getPhrases($id) {
        $record = RelevanceHistory::where('id', $id)->with('results')->first();

        if($record && $record->results) {
            $phrases = Relevance::uncompressItem($record->results->phrases);
            $unigram = Relevance::uncompressItem($record->results->unigram_table);

            return response()->json([
                'status' => 'ok',
                'phrases' => $phrases,
                'unigram' => $unigram
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'phrases' => [],
        ]);
    }

    public function allHistory()
    {
        /** @var \App\User $user */
        $user = Auth::user();
        if (!$user::isUserAdmin()) {
            abort(403);
        }

        $isAllHistory = true;
        $demoAutoOpen = false;
        $tokenStats = null;
        $tokenLeaderboard = \App\Support\AiDeepSeekTokenStats::adminLeaderboard(100);

        return view('ai-generation.story', compact('isAllHistory', 'demoAutoOpen', 'tokenStats', 'tokenLeaderboard'));
    }
}
