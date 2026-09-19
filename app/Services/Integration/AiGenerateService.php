<?php

namespace App\Services\Integration;

use App\AiGenerationHistory;
use App\Jobs\AIGeneration\GenerationCategoryQueue;
use App\Services\deepseek\prompts\PromptService;
use App\Support\AiGenerationLocalQueueGuard;
use App\User;
use InvalidArgumentException;

class AiGenerateService
{
    /** @var PromptService */
    protected $prompts;

    public function __construct(PromptService $prompts)
    {
        $this->prompts = $prompts;
    }

    /**
     * @param array{type:string,url?:string,name?:string,items?:array,keywords?:array,stopwords?:array,note?:string,prompt?:string,source?:string} $input
     */
    public function start(User $user, array $input): AiGenerationHistory
    {
        $type = strtolower(trim((string) ($input['type'] ?? '')));
        if (!in_array($type, ['category', 'preview', 'detail', 'phrase'], true)) {
            throw new InvalidArgumentException('type must be category, preview, detail or phrase');
        }

        $this->assertMonthlyBudget($user);

        // Пачка фраз: один запрос к модели на N товаров (экономия токенов).
        if ($type === 'phrase' && !empty($input['items']) && is_array($input['items'])) {
            return $this->startPhraseBatch($user, $input['items'], $input);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $url = trim((string) ($input['url'] ?? ''));
        $nameMax = (int) config('integration_api.ai_name_max_chars', 500);
        if ($nameMax > 0 && mb_strlen($name) > $nameMax) {
            throw new InvalidArgumentException('name too long (max ' . $nameMax . ')');
        }

        if ($type === 'phrase') {
            if ($name === '') {
                throw new InvalidArgumentException('name is required for type phrase');
            }
            // Для укорочения названия HTML страницы не нужен
            $source = AiGenerationHistory::SOURCE_AI_DATABASE;
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('url must be a valid URL');
            }
        } else {
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('url must be a valid URL');
            }
            $source = (string) ($input['source'] ?? AiGenerationHistory::SOURCE_PARSE_HTML);
            if (!in_array($source, [AiGenerationHistory::SOURCE_PARSE_HTML, AiGenerationHistory::SOURCE_AI_DATABASE], true)) {
                $source = AiGenerationHistory::SOURCE_PARSE_HTML;
            }
        }

        $basePrompt = trim((string) ($input['prompt'] ?? ''));
        if ($basePrompt === '') {
            $basePrompt = (string) config('integration_api.prompts.' . $type, '');
        }
        if ($basePrompt === '') {
            throw new InvalidArgumentException('prompt template missing for type ' . $type);
        }
        $promptMax = (int) config('integration_api.ai_prompt_max_chars', 12000);
        if ($promptMax > 0 && mb_strlen($basePrompt) > $promptMax) {
            throw new InvalidArgumentException('prompt too long (max ' . $promptMax . ')');
        }

        $note = isset($input['note']) ? (string) $input['note'] : null;
        $noteMax = (int) config('integration_api.ai_note_max_chars', 2000);
        if ($note !== null && $noteMax > 0 && mb_strlen($note) > $noteMax) {
            throw new InvalidArgumentException('note too long (max ' . $noteMax . ')');
        }
        $keywords = $this->normalizeWords($input['keywords'] ?? []);
        $stopwords = $this->normalizeWords($input['stopwords'] ?? []);
        $kwMax = (int) config('integration_api.ai_keywords_max', 80);
        if ($kwMax > 0 && count($keywords) > $kwMax) {
            $keywords = array_slice($keywords, 0, $kwMax);
        }

        $historyId = isset($input['history_id']) ? (int) $input['history_id'] : null;
        if ($historyId) {
            $owned = \App\RelevanceHistory::where('id', $historyId)
                ->where('user_id', $user->id)
                ->exists();
            if (!$owned) {
                throw new InvalidArgumentException('history_id not found');
            }
        }

        $params = [
            'link' => $url !== '' ? $url : null,
            'name' => $name !== '' ? $name : null,
            'keywords' => $keywords,
            'stopwords' => $stopwords,
            'note' => $note,
            'mode' => 'new',
            'source' => $source,
            'prompt' => $basePrompt,
            'type' => $type,
            'history_id' => $historyId,
        ];

        $prompt = $this->prompts->adaptivePrompt($url, $note, $basePrompt, $name);

        $historyType = AiGenerationHistory::TYPE_CATEGORY;
        if ($type === 'preview') {
            $historyType = AiGenerationHistory::TYPE_ANNOUNCEMENT;
        } elseif ($type === 'detail') {
            $historyType = 'detail';
        } elseif ($type === 'phrase') {
            $historyType = 'phrase';
        }

        $record = AiGenerationHistory::create([
            'user_id' => $user->id,
            'parrameters' => $params,
            'prompt' => $prompt,
            'type' => $historyType,
            'status' => AiGenerationHistory::PENDING,
        ]);

        GenerationCategoryQueue::dispatch($record)->onQueue('ai_generation');

        // Local: без setsid-воркера Bitrix висит на «Ожидание…» — поднимаем сами.
        AiGenerationLocalQueueGuard::ensureWorkers();

        return $record;
    }

    /**
     * @param array<int, array{name?:string,url?:string,external_id?:string|int}|string> $rawItems
     * @param array $input полный payload (для кастомного prompt)
     */
    public function startPhraseBatch(User $user, array $rawItems, array $input = []): AiGenerationHistory
    {
        $max = (int) config('integration_api.phrase_batch_size', 30);
        if ($max < 1) {
            $max = 30;
        }
        if ($max > 50) {
            $max = 50;
        }

        $items = [];
        foreach ($rawItems as $row) {
            if (is_string($row)) {
                $name = trim($row);
                $url = '';
                $externalId = null;
            } elseif (is_array($row)) {
                $name = trim((string) ($row['name'] ?? ''));
                $url = trim((string) ($row['url'] ?? ''));
                $externalId = isset($row['external_id']) ? (string) $row['external_id'] : null;
            } else {
                continue;
            }
            if ($name === '') {
                continue;
            }
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('url must be a valid URL');
            }
            $items[] = [
                'name' => $name,
                'url' => $url,
                'external_id' => $externalId,
            ];
            if (count($items) >= $max) {
                break;
            }
        }

        if ($items === []) {
            throw new InvalidArgumentException('items required (1…' . $max . ' names)');
        }

        $tpl = trim((string) ($input['prompt'] ?? ''));
        if ($tpl === '') {
            $tpl = (string) config('integration_api.prompts.phrase_batch', '');
        }
        if ($tpl === '') {
            throw new InvalidArgumentException('prompt template missing for phrase_batch');
        }

        $listLines = [];
        foreach ($items as $i => $it) {
            $listLines[] = ($i + 1) . '. ' . $it['name'];
        }
        $basePrompt = str_replace('{list}', implode("\n", $listLines), $tpl);
        $prompt = $this->prompts->adaptivePrompt('', null, $basePrompt, '');

        $params = [
            'link' => null,
            'name' => null,
            'keywords' => [],
            'stopwords' => [],
            'note' => null,
            'mode' => 'new',
            'source' => AiGenerationHistory::SOURCE_AI_DATABASE,
            'prompt' => $basePrompt,
            'type' => 'phrase_batch',
            'items' => $items,
            'items_count' => count($items),
        ];

        $record = AiGenerationHistory::create([
            'user_id' => $user->id,
            'parrameters' => $params,
            'prompt' => $prompt,
            'type' => 'phrase_batch',
            'status' => AiGenerationHistory::PENDING,
        ]);

        GenerationCategoryQueue::dispatch($record)->onQueue('ai_generation');
        AiGenerationLocalQueueGuard::ensureWorkers();

        return $record;
    }

    /**
     * Нормализация ответа нейросети для короткой ключевой фразы (≤ max).
     */
    public function normalizePhraseResult(?string $raw): string
    {
        $text = trim((string) $raw);
        $text = preg_replace('/^["«“]+|["»”]+$/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim((string) $text);

        // Если модель вернула несколько строк — берём первую непустую
        if (strpos($text, "\n") !== false) {
            $lines = preg_split('/\R/u', $text) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $text = $line;
                    break;
                }
            }
        }

        $max = (int) config('integration_api.phrase_max_length', 50);
        if ($max < 1) {
            $max = 50;
        }
        if (mb_strlen($text) > $max) {
            $text = rtrim(mb_substr($text, 0, $max));
        }

        return $text;
    }

    /**
     * Разбор ответа пачки фраз → массив нормализованных строк (длина = expected).
     *
     * @return string[]
     */
    public function parsePhraseBatchResult(?string $raw, int $expected): array
    {
        $expected = max(0, $expected);
        $text = trim((string) $raw);
        $decoded = null;

        if ($text !== '' && ($text[0] === '[' || $text[0] === '{')) {
            $decoded = json_decode($text, true);
        }
        if (!is_array($decoded)) {
            if (preg_match('/\[[\s\S]*\]/u', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        $phrases = [];
        if (is_array($decoded)) {
            // [{"result":"..."}] или ["..."]
            $isList = array_keys($decoded) === range(0, count($decoded) - 1);
            if ($isList) {
                foreach ($decoded as $row) {
                    if (is_string($row)) {
                        $phrases[] = $this->normalizePhraseResult($row);
                    } elseif (is_array($row)) {
                        $phrases[] = $this->normalizePhraseResult((string) ($row['result'] ?? $row['phrase'] ?? $row['name'] ?? ''));
                    }
                }
            }
        }

        // fallback: по строкам
        if (count($phrases) < $expected) {
            $lines = preg_split('/\R/u', $text) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '[' || $line[0] === '{') {
                    continue;
                }
                $line = preg_replace('/^\d+[\).\:\-]\s*/u', '', $line);
                $line = $this->normalizePhraseResult($line);
                if ($line !== '') {
                    $phrases[] = $line;
                }
                if (count($phrases) >= $expected) {
                    break;
                }
            }
        }

        while (count($phrases) < $expected) {
            $phrases[] = '';
        }
        if (count($phrases) > $expected) {
            $phrases = array_slice($phrases, 0, $expected);
        }

        return $phrases;
    }

    protected function assertMonthlyBudget(User $user): void
    {
        $limit = (int) config('integration_api.ai_monthly_request_limit', 500);
        if ($limit <= 0) {
            return;
        }
        $used = AiGenerationHistory::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        if ($used >= $limit) {
            throw new InvalidArgumentException('ai_monthly_limit_exhausted');
        }
    }

    protected function normalizeWords($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $word = trim($item);
                if ($word !== '') {
                    $out[] = ['word' => $word, 'count' => 1];
                }
                continue;
            }
            if (is_array($item)) {
                $word = trim((string) ($item['word'] ?? ''));
                if ($word === '') {
                    continue;
                }
                $out[] = [
                    'word' => $word,
                    'count' => max(1, (int) ($item['count'] ?? $item['suggested_count'] ?? 1)),
                ];
            }
        }

        return $out;
    }
}
