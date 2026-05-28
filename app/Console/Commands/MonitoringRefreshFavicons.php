<?php

namespace App\Console\Commands;

use App\Classes\Monitoring\ProjectFaviconService;
use App\MonitoringProject;
use Illuminate\Console\Command;

class MonitoringRefreshFavicons extends Command
{
    protected $signature = 'monitoring:refresh-favicons
                            {--missing : только проекты без сохранённой иконки}
                            {--project= : id одного проекта}
                            {--force : перекачать даже если уже есть}';

    protected $description = 'Скачать и сохранить фавиконки проектов мониторинга (PNG 128×128)';

    public function handle(ProjectFaviconService $service): int
    {
        $query = MonitoringProject::query()->orderBy('id');

        if ($projectId = $this->option('project')) {
            $query->where('id', (int) $projectId);
        } elseif ($this->option('missing')) {
            $query->whereNull('favicon_path');
        }

        $force = (bool) $this->option('force');
        $ok = 0;
        $fail = 0;

        $query->chunkById(50, function ($projects) use ($service, $force, &$ok, &$fail) {
            foreach ($projects as $project) {
                $this->line('Project #' . $project->id . ' ' . $project->url);
                if ($service->refresh($project, $force)) {
                    $ok++;
                    $this->info('  OK');
                } else {
                    $fail++;
                    $this->warn('  skip');
                }
            }
        });

        $this->info("Done: {$ok} saved, {$fail} skipped.");

        return 0;
    }
}
