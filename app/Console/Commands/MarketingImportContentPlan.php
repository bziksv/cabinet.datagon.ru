<?php

namespace App\Console\Commands;

use App\Services\Marketing\MarketingContentPlanImporter;
use Illuminate\Console\Command;

class MarketingImportContentPlan extends Command
{
    protected $signature = 'marketing:import-content-plan {path? : Путь к seo-glossary-plan.md (по умолчанию — копия в database/data/marketing)}';

    protected $description = 'Маркетинг сайта: импорт терминов SEO-словаря и тем блога из seo-glossary-plan.md (идемпотентно, правки не перетирает)';

    public function handle(MarketingContentPlanImporter $importer): int
    {
        $path = (string) ($this->argument('path') ?: config('cabinet-marketing.content_plan_file'));
        $stats = $importer->importFile($path);

        $this->info('Файл: ' . $path);
        foreach ($stats as $key => $value) {
            $this->line(sprintf('  %s: %d', $key, $value));
        }

        return 0;
    }
}
