<?php

namespace App\Jobs;

use App\Notifications\PhraseCommerceCompletedNotification;
use App\PhraseCommerceHistory;
use App\PhraseCommerceRun;
use App\Services\PhraseCommerceService;
use App\Support\PhraseCommerceLimits;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunPhraseCommerceCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 3600;

    /** @var int */
    public $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
        $this->onQueue('phrase-commerce');
    }

    public function handle(PhraseCommerceService $service): void
    {
        /** @var PhraseCommerceRun|null $run */
        $run = PhraseCommerceRun::query()->find($this->runId);
        if (! $run || $run->status === PhraseCommerceRun::STATUS_DONE) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()->find($run->user_id);
        if (! $user) {
            $run->update([
                'status' => PhraseCommerceRun::STATUS_FAILED,
                'error_message' => 'Пользователь не найден',
                'finished_at' => now(),
            ]);

            return;
        }

        $params = is_array($run->params) ? $run->params : [];
        $phrases = is_array($params['phrases'] ?? null) ? array_values($params['phrases']) : [];
        $engines = is_array($params['engines'] ?? null) ? array_values($params['engines']) : [];

        if ($phrases === [] || $engines === []) {
            $run->update([
                'status' => PhraseCommerceRun::STATUS_FAILED,
                'error_message' => 'Пустой список фраз или поисковых систем',
                'finished_at' => now(),
            ]);

            return;
        }

        $run->update([
            'status' => PhraseCommerceRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?: now(),
            'phrases_total' => count($phrases),
            'phrases_done' => 0,
            'current_phrase' => $phrases[0] ?? null,
            'error_message' => null,
        ]);

        $allRows = [];
        $totalCost = 0;
        $totalRequests = 0;
        $totalErrors = 0;
        $depth = (int) config('cabinet-phrase-commerce.depth', PhraseCommerceService::DEPTH);
        $contrast = [
            'yandex' => (string) ($params['yandex_lr2'] ?? ''),
            'google' => (string) ($params['google_lr2'] ?? ''),
        ];
        $warning = null;

        try {
            foreach ($phrases as $idx => $phrase) {
                $minPhraseCost = PhraseCommerceService::estimateCost(1, $engines);
                if (! PhraseCommerceLimits::canSpend($minPhraseCost, $user)) {
                    $warning = PhraseCommerceLimits::limitMessage($user)
                        ?: __('Phrase commerce limit exhausted');
                    break;
                }

                $run->update([
                    'current_phrase' => mb_substr((string) $phrase, 0, 255),
                ]);

                $single = $service->analyze([
                    'phrases' => [$phrase],
                    'engines' => $engines,
                    'yandex_lr' => (string) ($params['yandex_lr'] ?? config('cabinet-phrase-commerce.default_yandex_lr', '213')),
                    'google_lr' => (string) ($params['google_lr'] ?? config('cabinet-phrase-commerce.default_google_lr', '1011969')),
                    'yandex_lr2' => $contrast['yandex'],
                    'google_lr2' => $contrast['google'],
                ]);

                $rows = is_array($single['rows'] ?? null) ? $single['rows'] : [];
                $allRows = array_merge($allRows, $rows);

                $phraseCost = max(0, (int) ($single['cost'] ?? $minPhraseCost));
                $remainingBefore = PhraseCommerceLimits::remainingForUser($user);
                if ($remainingBefore !== null && $phraseCost > $remainingBefore) {
                    $phraseCost = $remainingBefore;
                }
                if ($phraseCost > 0) {
                    PhraseCommerceLimits::spend($phraseCost, $user);
                }

                $totalCost += $phraseCost;
                $totalRequests += (int) ($single['requests'] ?? 0);
                $totalErrors += (int) ($single['errors'] ?? 0);
                $depth = (int) ($single['depth'] ?? $depth);

                $cr = is_array($single['contrast_regions'] ?? null) ? $single['contrast_regions'] : [];
                if (! empty($cr['yandex'])) {
                    $contrast['yandex'] = (string) $cr['yandex'];
                }
                if (! empty($cr['google'])) {
                    $contrast['google'] = (string) $cr['google'];
                }

                $run->update([
                    'phrases_done' => $idx + 1,
                    'actual_cost' => $totalCost,
                    'requests_count' => $totalRequests,
                    'errors_count' => $totalErrors,
                ]);
            }

            $summary = $service->summaryFromRows($allRows);
            $finalParams = array_merge($params, [
                'yandex_lr2' => $contrast['yandex'],
                'google_lr2' => $contrast['google'],
            ]);
            $resultsPayload = [
                'summary' => $summary,
                'rows' => $allRows,
                'depth' => $depth,
                'contrast_regions' => $contrast,
            ];

            $historyId = null;
            if ($run->save_to_history && $allRows !== [] && PhraseCommerceLimits::canSaveHistory($user)) {
                if (! PhraseCommerceLimits::canSaveAnother($user)) {
                    $histWarn = PhraseCommerceLimits::historyLimitMessage($user)
                        ?: __('Phrase commerce history limit exhausted');
                    $warning = $warning ? ($warning . '; ' . $histWarn) : $histWarn;
                } else {
                    $title = $run->title ?: ($phrases[0] ?? __('Phrase commerce'));
                    $history = PhraseCommerceHistory::query()->create([
                        'user_id' => $user->id,
                        'title' => mb_substr($title, 0, 255),
                        'params' => $finalParams,
                        'results' => [
                            'summary' => $summary,
                            'rows' => $allRows,
                            'depth' => $depth,
                        ],
                        'phrases_count' => count($phrases),
                        'results_count' => count($allRows),
                        'cost' => $totalCost,
                    ]);
                    $historyId = $history->id;
                }
            }

            $run->update([
                'status' => PhraseCommerceRun::STATUS_DONE,
                'params' => $finalParams,
                'results' => $resultsPayload,
                'history_id' => $historyId,
                'actual_cost' => $totalCost,
                'requests_count' => $totalRequests,
                'errors_count' => $totalErrors,
                'current_phrase' => null,
                'error_message' => $warning,
                'finished_at' => now(),
            ]);

            $this->notifyTelegram($user, $run->fresh());
        } catch (Throwable $e) {
            report($e);
            $run->update([
                'status' => PhraseCommerceRun::STATUS_FAILED,
                'error_message' => 'Ошибка при сборе выдачи. Попробуйте повторить проверку.',
                'finished_at' => now(),
            ]);
        }
    }

    private function notifyTelegram(User $user, ?PhraseCommerceRun $run): void
    {
        if (! $run || $run->notified_at !== null) {
            return;
        }

        try {
            $sent = PhraseCommerceCompletedNotification::sendTelegram($user, $run);
            if ($sent) {
                $run->update(['notified_at' => now()]);
            }
        } catch (Throwable $e) {
            Log::warning('PhraseCommerce telegram notify failed: ' . $e->getMessage(), [
                'run_id' => $run->id,
                'user_id' => $user->id,
            ]);
        }
    }
}
