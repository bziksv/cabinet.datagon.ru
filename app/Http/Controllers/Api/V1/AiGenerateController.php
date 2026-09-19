<?php

namespace App\Http\Controllers\Api\V1;

use App\AiGenerationHistory;
use App\Http\Controllers\Controller;
use App\Services\Integration\AiGenerateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AiGenerateController extends Controller
{
    /** @var AiGenerateService */
    protected $generator;

    public function __construct(AiGenerateService $generator)
    {
        $this->generator = $generator;
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $record = $this->generator->start(Auth::user(), $request->all());
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $code = $msg === 'ai_monthly_limit_exhausted' ? 429 : 422;
            return response()->json([
                'error' => $msg === 'ai_monthly_limit_exhausted' ? 'ai_monthly_limit_exhausted' : 'validation_error',
                'message' => $msg === 'ai_monthly_limit_exhausted'
                    ? 'Monthly AI generation limit exhausted'
                    : $msg,
            ], $code);
        }

        return response()->json([
            'record_id' => $record->id,
            'status' => $record->status,
        ], 202);
    }

    public function show(int $recordId): JsonResponse
    {
        $record = AiGenerationHistory::where('id', $recordId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$record) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $payload = [
            'record_id' => $record->id,
            'status' => $record->status,
            'type' => data_get($record->parrameters, 'type', $record->type),
        ];

        if ($record->status === AiGenerationHistory::COMPLETED) {
            $result = $record->result;
            $type = data_get($record->parrameters, 'type', $record->type);
            if ($type === 'phrase') {
                $result = $this->generator->normalizePhraseResult($result);
                $payload['result'] = $result;
                $payload['max_length'] = (int) config('integration_api.phrase_max_length', 50);
            } elseif ($type === 'phrase_batch') {
                $itemsMeta = data_get($record->parrameters, 'items', []);
                $expected = is_array($itemsMeta) ? count($itemsMeta) : 0;
                $phrases = $this->generator->parsePhraseBatchResult((string) $result, $expected);
                $outItems = [];
                foreach ($phrases as $i => $phrase) {
                    $meta = is_array($itemsMeta[$i] ?? null) ? $itemsMeta[$i] : [];
                    $outItems[] = [
                        'index' => $i,
                        'external_id' => $meta['external_id'] ?? null,
                        'name' => $meta['name'] ?? null,
                        'result' => $phrase,
                        'ok' => $phrase !== '',
                    ];
                }
                $payload['type'] = 'phrase_batch';
                $payload['items'] = $outItems;
                $payload['result'] = $phrases;
                $payload['max_length'] = (int) config('integration_api.phrase_max_length', 50);
            } else {
                $payload['result'] = $result;
            }
            $payload['used_tokens'] = $record->used_tokens ?? null;
        } elseif ($record->status === AiGenerationHistory::FAILED) {
            $payload['error'] = $this->publicFailureMessage((string) $record->result);
        }

        return response()->json($payload);
    }

    protected function publicFailureMessage(string $raw): string
    {
        $lower = mb_strtolower($raw);
        if (strpos($lower, 'insufficient balance') !== false || strpos($lower, '402') !== false) {
            return 'Недостаточно средств на счёте генерации текстов. Пополните баланс токенов в кабинете поставщика.';
        }
        if (strpos($lower, 'timeout') !== false) {
            return 'Таймаут генерации. Повторите позже.';
        }
        if (strpos($lower, 'unauthorized') !== false || strpos($lower, '401') !== false) {
            return 'Ошибка авторизации сервиса генерации.';
        }

        // Не светим внешние URL/имена провайдеров в ответах магазину
        $clean = preg_replace('#https?://\S+#u', '', $raw);
        $clean = trim(preg_replace('/\s+/u', ' ', (string) $clean));
        if ($clean === '' || mb_strlen($clean) > 240) {
            return 'Генерация не удалась. Повторите позже или обратитесь в поддержку.';
        }

        return $clean;
    }
}
