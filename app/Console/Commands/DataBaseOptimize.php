<?php

namespace App\Console\Commands;

use App\Services\Database\TableOptimizeService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DataBaseOptimize extends Command
{
    /**
     * @var string
     */
    protected $signature = 'db:optimize
                        {--table=* : Defaulting to all tables in the default database.}';

    /**
     * @var string
     */
    protected $description = 'Optimize table/s of the database (OPTIMIZE TABLE + history)';

    /**
     * @var \Illuminate\Database\Query\Builder
     */
    protected $db;

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar|null
     */
    protected $progress;

    public function __construct()
    {
        parent::__construct();
        $this->db = DB::table(DB::raw('dual'));
    }

    /**
     * @throws Exception
     */
    public function handle(TableOptimizeService $optimizer): void
    {
        $this->info('Starting Optimization.');

        $tables = $this->getTables();
        $this->progress = $this->output->createProgressBar($tables->count());

        $triggeredBy = $this->option('table') ? 'artisan' : 'cron';

        $tables->each(function ($table) use ($optimizer, $triggeredBy) {
            try {
                // Прямой execute (не requestOptimize): cron не должен уходить в очередь по порогу
                if (! $optimizer->historyReady()) {
                    DB::statement('OPTIMIZE TABLE `' . str_replace('`', '', $table) . '`');
                    $this->progress->advance();

                    return;
                }

                $run = \App\DatabaseTableOptimizeRun::query()->create([
                    'table_name' => $table,
                    'status' => 'running',
                    'mode' => 'sync',
                    'triggered_by' => $triggeredBy,
                    'started_at' => now(),
                ]);
                $optimizer->executeRun($run, false);
                $this->progress->advance();
            } catch (\Throwable $e) {
                $this->error(" {$table}: " . $e->getMessage());
            }
        });

        try {
            app(\App\Services\Database\DatabaseInventoryService::class)->refreshMetadata();
        } catch (\Throwable $e) {
            // ignore
        }

        $this->info(PHP_EOL . 'Optimization Completed');
    }

    /**
     * @throws Exception
     */
    protected function getDatabase(): string
    {
        $database = (string) config('database.connections.mysql.database');

        if ($database !== '' && $this->existsDatabase($database)) {
            return $database;
        }

        throw new Exception("This database {$database} doesn't exists.");
    }

    private function existsDatabase(string $databaseName): bool
    {
        return DB::table('information_schema.schemata')
            ->where('SCHEMA_NAME', $databaseName)
            ->exists();
    }

    /**
     * @throws Exception
     */
    private function getTables(): Collection
    {
        $tableList = collect($this->option('table'))->filter()->values();
        if ($tableList->isEmpty()) {
            return DB::table('information_schema.tables')
                ->where('TABLE_SCHEMA', $this->getDatabase())
                ->orderBy('TABLE_NAME')
                ->pluck('TABLE_NAME');
        }

        if ($this->existsTables($tableList)) {
            return $tableList;
        }

        throw new Exception("One or more tables provided doesn't exists.");
    }

    /**
     * @throws Exception
     */
    private function existsTables(Collection $tables): bool
    {
        $count = DB::table('information_schema.tables')
            ->where('TABLE_SCHEMA', $this->getDatabase())
            ->whereIn('TABLE_NAME', $tables->all())
            ->count();

        return $count === $tables->count();
    }
}
