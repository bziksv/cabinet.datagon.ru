<?php

namespace App\Support;

use App\AiGenerationHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Учёт токенов генерации (DeepSeek) по пользователям из ai_generation_histories.
 */
class AiDeepSeekTokenStats
{
    /** Оценка стоимости DeepSeek: USD за 1 млн токенов. */
    public const USD_PER_MILLION_TOKENS = 2.0;

    /**
     * Пресеты периода для истории генерации.
     *
     * @return array<string, string> value => label
     */
    public static function periodPresets(): array
    {
        return [
            'all' => 'Все время',
            'today' => 'Сегодня',
            'yesterday' => 'Вчера',
            'current_month' => 'Текущий месяц',
            'prev_month' => 'Прошлый месяц',
            'last_30' => 'Последние 30 дней',
            'last_60' => 'Последние 60 дней',
            'last_90' => 'Последние 90 дней',
            'last_180' => 'Последние 180 дней',
            'last_365' => 'Последние 365 дней',
            'current_year' => 'Текущий год',
            'prev_year' => 'Прошлый год',
        ];
    }

    /**
     * @return array{0:?Carbon,1:?Carbon} [fromInclusive, toExclusive] — null = без фильтра
     */
    public static function resolvePeriod(?string $preset): array
    {
        $preset = strtolower(trim((string) $preset));
        if ($preset === '' || $preset === 'all' || !isset(self::periodPresets()[$preset])) {
            return [null, null];
        }

        $now = Carbon::now();

        switch ($preset) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->addDay()->startOfDay()];
            case 'yesterday':
                $y = $now->copy()->subDay();

                return [$y->copy()->startOfDay(), $y->copy()->addDay()->startOfDay()];
            case 'current_month':
                return [$now->copy()->startOfMonth(), $now->copy()->addMonthNoOverflow()->startOfMonth()];
            case 'prev_month':
                $m = $now->copy()->subMonthNoOverflow();

                return [$m->copy()->startOfMonth(), $m->copy()->addMonthNoOverflow()->startOfMonth()];
            case 'last_30':
                return [$now->copy()->subDays(29)->startOfDay(), $now->copy()->addDay()->startOfDay()];
            case 'last_60':
                return [$now->copy()->subDays(59)->startOfDay(), $now->copy()->addDay()->startOfDay()];
            case 'last_90':
                return [$now->copy()->subDays(89)->startOfDay(), $now->copy()->addDay()->startOfDay()];
            case 'last_180':
                return [$now->copy()->subDays(179)->startOfDay(), $now->copy()->addDay()->startOfDay()];
            case 'last_365':
                return [$now->copy()->subDays(364)->startOfDay(), $now->copy()->addDay()->startOfDay()];
            case 'current_year':
                return [$now->copy()->startOfYear(), $now->copy()->addYear()->startOfYear()];
            case 'prev_year':
                $y = $now->copy()->subYear();

                return [$y->copy()->startOfYear(), $y->copy()->addYear()->startOfYear()];
            default:
                return [null, null];
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @return mixed
     */
    public static function applyPeriod($query, ?string $preset, string $column = 'created_at')
    {
        [$from, $to] = self::resolvePeriod($preset);
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<', $to);
        }

        return $query;
    }

    public static function costUsd(int $tokens): float
    {
        if ($tokens <= 0) {
            return 0.0;
        }

        return ($tokens / 1000000) * self::USD_PER_MILLION_TOKENS;
    }

    /** Формат для UI: $0.1082 */
    public static function formatUsd(float $usd, int $decimals = 4): string
    {
        if ($usd <= 0) {
            return '$0.00';
        }
        $decimals = max(2, min(6, $decimals));
        $formatted = number_format($usd, $decimals, '.', ' ');
        if (strpos($formatted, '.') !== false) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
            if (strpos($formatted, '.') === false) {
                $formatted .= '.00';
            } else {
                $frac = substr($formatted, strpos($formatted, '.') + 1);
                if (strlen($frac) < 2) {
                    $formatted .= str_repeat('0', 2 - strlen($frac));
                }
            }
        }

        return '$' . $formatted;
    }

    public static function formatInt(int $n): string
    {
        return number_format($n, 0, '', ' ');
    }

    /**
     * @return array{requests:int,completed:int,tokens:int,prompt_tokens:int,completion_tokens:int,cost_usd:float,cost_fmt:string,tokens_fmt:string,requests_fmt:string,prompt_tokens_fmt:string,completion_tokens_fmt:string}
     */
    public static function forUser(int $userId, ?string $period = null): array
    {
        if ($userId <= 0 || !self::tableReady()) {
            return self::empty();
        }

        $hasBreakdown = Schema::hasColumn('ai_generation_histories', 'prompt_tokens');

        $q = AiGenerationHistory::query()
            ->where('user_id', $userId);
        self::applyPeriod($q, $period);

        $q->selectRaw('COUNT(*) AS requests')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed", [AiGenerationHistory::COMPLETED])
            ->selectRaw('COALESCE(SUM(used_tokens), 0) AS tokens');

        if ($hasBreakdown) {
            $q->selectRaw('COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens')
                ->selectRaw('COALESCE(SUM(completion_tokens), 0) AS completion_tokens');
        } else {
            $q->selectRaw('0 AS prompt_tokens')
                ->selectRaw('0 AS completion_tokens');
        }

        $row = $q->first();
        $tokens = (int) ($row->tokens ?? 0);
        $cost = self::costUsd($tokens);
        $requests = (int) ($row->requests ?? 0);
        $promptTokens = (int) ($row->prompt_tokens ?? 0);
        $completionTokens = (int) ($row->completion_tokens ?? 0);

        return [
            'requests' => $requests,
            'completed' => (int) ($row->completed ?? 0),
            'tokens' => $tokens,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_usd' => $cost,
            'cost_fmt' => self::formatUsd($cost, 4),
            'tokens_fmt' => self::formatInt($tokens),
            'requests_fmt' => self::formatInt($requests),
            'prompt_tokens_fmt' => self::formatInt($promptTokens),
            'completion_tokens_fmt' => self::formatInt($completionTokens),
        ];
    }

    /**
     * @return array{summary: array<string,mixed>, rows: array<int, array<string,mixed>>}
     */
    public static function adminLeaderboard(int $limit = 100, ?string $period = null): array
    {
        if (!self::tableReady()) {
            return [
                'summary' => [
                    'users' => 0,
                    'requests' => 0,
                    'tokens' => 0,
                    'cost_usd' => 0.0,
                    'cost_fmt' => '$0.00',
                    'tokens_fmt' => '0',
                    'requests_fmt' => '0',
                    'users_fmt' => '0',
                ],
                'rows' => [],
            ];
        }

        $limit = max(1, min(500, $limit));

        $summaryQ = AiGenerationHistory::query();
        self::applyPeriod($summaryQ, $period);
        $summaryRow = $summaryQ
            ->selectRaw('COUNT(DISTINCT user_id) AS users')
            ->selectRaw('COUNT(*) AS requests')
            ->selectRaw('COALESCE(SUM(used_tokens), 0) AS tokens')
            ->first();

        $hasBreakdown = Schema::hasColumn('ai_generation_histories', 'prompt_tokens');
        $promptSelect = $hasBreakdown
            ? 'COALESCE(SUM(h.prompt_tokens), 0) as prompt_tokens'
            : '0 as prompt_tokens';
        $completionSelect = $hasBreakdown
            ? 'COALESCE(SUM(h.completion_tokens), 0) as completion_tokens'
            : '0 as completion_tokens';

        $rowsQ = AiGenerationHistory::query()
            ->from('ai_generation_histories as h')
            ->leftJoin('users as u', 'u.id', '=', 'h.user_id');
        self::applyPeriod($rowsQ, $period, 'h.created_at');

        $rows = $rowsQ
            ->select([
                'h.user_id',
                DB::raw('u.name as name'),
                DB::raw('u.email as email'),
                DB::raw('COUNT(*) as requests'),
                DB::raw("SUM(CASE WHEN h.status = '" . AiGenerationHistory::COMPLETED . "' THEN 1 ELSE 0 END) as completed"),
                DB::raw('COALESCE(SUM(h.used_tokens), 0) as tokens'),
                DB::raw($promptSelect),
                DB::raw($completionSelect),
                DB::raw('MAX(h.created_at) as last_at'),
            ])
            ->groupBy('h.user_id', 'u.name', 'u.email')
            ->orderByDesc('tokens')
            ->limit($limit)
            ->get()
            ->map(static function ($row) {
                $tokens = (int) $row->tokens;
                $cost = self::costUsd($tokens);

                return [
                    'user_id' => (int) $row->user_id,
                    'name' => (string) ($row->name ?? ''),
                    'email' => (string) ($row->email ?? ''),
                    'requests' => (int) $row->requests,
                    'completed' => (int) $row->completed,
                    'tokens' => $tokens,
                    'prompt_tokens' => (int) $row->prompt_tokens,
                    'completion_tokens' => (int) $row->completion_tokens,
                    'cost_usd' => $cost,
                    'cost_fmt' => self::formatUsd($cost, 4),
                    'last_at' => $row->last_at ? (string) $row->last_at : null,
                ];
            })
            ->all();

        $summaryTokens = (int) ($summaryRow->tokens ?? 0);
        $summaryRequests = (int) ($summaryRow->requests ?? 0);
        $summaryUsers = (int) ($summaryRow->users ?? 0);

        return [
            'summary' => [
                'users' => $summaryUsers,
                'requests' => $summaryRequests,
                'tokens' => $summaryTokens,
                'cost_usd' => self::costUsd($summaryTokens),
                'cost_fmt' => self::formatUsd(self::costUsd($summaryTokens), 4),
                'tokens_fmt' => self::formatInt($summaryTokens),
                'requests_fmt' => self::formatInt($summaryRequests),
                'users_fmt' => self::formatInt($summaryUsers),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{requests:int,completed:int,tokens:int,prompt_tokens:int,completion_tokens:int,cost_usd:float,cost_fmt:string,tokens_fmt:string,requests_fmt:string,prompt_tokens_fmt:string,completion_tokens_fmt:string}
     */
    protected static function empty(): array
    {
        return [
            'requests' => 0,
            'completed' => 0,
            'tokens' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_usd' => 0.0,
            'cost_fmt' => '$0.00',
            'tokens_fmt' => '0',
            'requests_fmt' => '0',
            'prompt_tokens_fmt' => '0',
            'completion_tokens_fmt' => '0',
        ];
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::hasTable('ai_generation_histories');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
