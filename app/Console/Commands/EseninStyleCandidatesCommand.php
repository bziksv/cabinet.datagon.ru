<?php

namespace App\Console\Commands;

use App\Support\Esenin\EseninStyleLearning;
use Illuminate\Console\Command;

class EseninStyleCandidatesCommand extends Command
{
    protected $signature = 'esenin:style-candidates
        {--min-hits=3 : Minimum hits to list as ready}
        {--source= : Filter by source (turgenev_report, turgenev_diff)}';

    protected $description = 'List style dictionary candidates collected from Turgenev reports';

    public function handle(): int
    {
        $minHits = max(1, (int) $this->option('min-hits'));
        $sourceFilter = trim((string) $this->option('source'));
        $items = EseninStyleLearning::listCandidates();

        if ($sourceFilter !== '') {
            $items = array_filter($items, static function ($item) use ($sourceFilter) {
                return (string) ($item['source'] ?? '') === $sourceFilter;
            });
        }

        $ready = array_filter($items, static function ($item) use ($minHits) {
            return (int) ($item['hits'] ?? 0) >= $minHits;
        });

        if ($ready === []) {
            $this->info('No candidates with min hits ' . $minHits . ($sourceFilter !== '' ? ' and source ' . $sourceFilter : ''));

            return 0;
        }

        usort($ready, static function ($a, $b) {
            return ((int) ($b['hits'] ?? 0)) <=> ((int) ($a['hits'] ?? 0));
        });

        $rows = [];
        foreach ($ready as $item) {
            $rows[] = [
                (string) ($item['phrase'] ?? ''),
                (int) ($item['hits'] ?? 0),
                (int) ($item['weight'] ?? 1),
                (string) ($item['rule_id'] ?? ''),
                (string) ($item['source'] ?? ''),
                mb_substr((string) ($item['hint'] ?? ''), 0, 80, 'UTF-8'),
            ];
        }

        $this->table(['phrase', 'hits', 'weight', 'rule_id', 'source', 'hint'], $rows);
        $this->line('');
        $this->info(count($ready) . ' candidates are promoted into the live analyzer at runtime (min hits ' . $minHits . ').');

        return 0;
    }
}
