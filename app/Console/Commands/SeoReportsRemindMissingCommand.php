<?php

namespace App\Console\Commands;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportProject;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SeoReportsRemindMissingCommand extends Command
{
    protected $signature = 'seo-reports:remind-missing';

    protected $description = 'Log/remind about missing previous-month SEO reports for active projects';

    public function handle(): int
    {
        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $label = $from->format('Y-m');

        $missing = [];
        SeoReportProject::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($projects) use ($from, $to, &$missing) {
                foreach ($projects as $project) {
                    $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
                    if (empty($settings['remind_missing'])) {
                        continue;
                    }
                    $has = SeoReport::query()
                        ->where('project_id', $project->id)
                        ->whereDate('period_from', $from->toDateString())
                        ->whereDate('period_to', $to->toDateString())
                        ->whereNull('archived_from_report_id')
                        ->whereIn('status', [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED])
                        ->exists();
                    if (!$has) {
                        $missing[] = [
                            'project_id' => (int) $project->id,
                            'user_id' => (int) $project->user_id,
                            'domain' => $project->domain,
                        ];
                    }
                }
            });

        foreach ($missing as $row) {
            Log::notice('seo report missing for month', [
                'month' => $label,
                'project_id' => $row['project_id'],
                'user_id' => $row['user_id'],
                'domain' => $row['domain'],
                'message' => 'Отчёт за ' . $label . ' не собран: ' . $row['domain'],
            ]);
            $this->warn('Missing ' . $label . ' · ' . $row['domain'] . ' (user #' . $row['user_id'] . ')');
        }

        $this->info('Missing reports: ' . count($missing));

        return 0;
    }
}
