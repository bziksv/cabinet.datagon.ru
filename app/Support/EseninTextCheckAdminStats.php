<?php

namespace App\Support;

use App\EseninTextCheckSession;
use App\EseninTextCheckUsage;
use App\EseninTextCheckVersion;
use App\Support\Esenin\EseninStyleLearning;

class EseninTextCheckAdminStats
{
    /**
     * @return array{summary: array<string, int|float>, rows: array<int, array<string, mixed>>}
     */
    public static function snapshot(): array
    {
        $summary = [
            'sessions_total' => 0,
            'versions_total' => 0,
            'checks_this_month' => 0,
            'users_with_checks' => 0,
            'style_candidates' => 0,
        ];

        $rows = [];

        if (EseninTextCheckSession::query()->getConnection()->getSchemaBuilder()->hasTable('esenin_text_check_sessions')) {
            $summary['sessions_total'] = (int) EseninTextCheckSession::query()->count();
        }

        if (EseninTextCheckVersion::query()->getConnection()->getSchemaBuilder()->hasTable('esenin_text_check_versions')) {
            $summary['versions_total'] = (int) EseninTextCheckVersion::query()->count();
        }

        $period = EseninTextCheckLimits::periodKey();
        if (EseninTextCheckUsage::query()->getConnection()->getSchemaBuilder()->hasTable('esenin_text_check_usages')) {
            $summary['checks_this_month'] = (int) EseninTextCheckUsage::query()
                ->where('period', $period)
                ->sum('used');
            $summary['users_with_checks'] = (int) EseninTextCheckUsage::query()
                ->where('period', $period)
                ->where('used', '>', 0)
                ->count();

            $rows = EseninTextCheckUsage::query()
                ->select([
                    'esenin_text_check_usages.user_id',
                    'esenin_text_check_usages.used',
                    'users.email',
                    'users.name',
                ])
                ->join('users', 'users.id', '=', 'esenin_text_check_usages.user_id')
                ->where('esenin_text_check_usages.period', $period)
                ->where('esenin_text_check_usages.used', '>', 0)
                ->orderByDesc('esenin_text_check_usages.used')
                ->limit(100)
                ->get()
                ->map(static function ($row) {
                    return [
                        'user_id' => (int) $row->user_id,
                        'email' => (string) $row->email,
                        'name' => (string) ($row->name ?? ''),
                        'used' => (int) $row->used,
                    ];
                })
                ->all();
        }

        try {
            $summary['style_candidates'] = count(EseninStyleLearning::listCandidates());
        } catch (\Throwable $e) {
            $summary['style_candidates'] = 0;
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }
}
