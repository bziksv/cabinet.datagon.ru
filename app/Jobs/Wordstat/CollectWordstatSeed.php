<?php
namespace App\Jobs\Wordstat;

use App\Services\Wordstat\WordstatCollector;
use App\Services\Wordstat\WordstatException;
use App\Wordstat\WordstatRunSeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CollectWordstatSeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TEMPORARY_ERROR = 'Сбор фразы прервался, попробуйте позже';

    public $tries = 5;
    public $timeout = 180;
    private int $seedId;

    public function __construct(int $seedId)
    {
        $this->seedId = $seedId;
    }

    public function handle(WordstatCollector $collector): void
    {
        $seed = WordstatRunSeed::query()->find($this->seedId);
        if (! $seed) {
            return;
        }

        try {
            $collector->collectSeed($seed);
        } catch (WordstatException $e) {
            Log::warning('wordstat.seed_error', ['seed_id' => $seed->id, 'retryable' => $e->isRetryable(), 'message' => $e->getMessage()]);
            if (! $e->isRetryable()) {
                $collector->failRun($seed->run, $e->getMessage());

                return;
            }
            $this->retryOrFail($collector, $seed, $e->getMessage());
        } catch (\Throwable $e) {
            // Любая другая ошибка не должна оставлять затравку в очереди навсегда
            Log::error('wordstat.seed_crash', ['seed_id' => $seed->id, 'message' => $e->getMessage()]);
            $this->retryOrFail($collector, $seed, self::TEMPORARY_ERROR);
        }
    }

    public function failed(\Throwable $e): void
    {
        $seed = WordstatRunSeed::query()->find($this->seedId);
        if ($seed) {
            app(WordstatCollector::class)->failSeed($seed, self::TEMPORARY_ERROR);
        }
    }

    private function retryOrFail(WordstatCollector $collector, WordstatRunSeed $seed, string $message): void
    {
        $collector->resetSeed($seed);
        if ($this->attempts() < $this->tries) {
            $delays = (array) config('cabinet-wordstat-keywords.retry_delays', [10, 30, 90, 90]);
            $this->release((int) ($delays[$this->attempts() - 1] ?? 90));

            return;
        }
        $collector->failSeed($seed, $message);
    }
}
