<?php

namespace App\Jobs\AIGeneration;

use App\AiGenerationHistory;
use App\TextAnalyzer;
use Illuminate\Bus\Queueable;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerationCategoryQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;

    /** Воркер ai_generation --timeout=600. Две попытки DeepSeek по 240 с. */
    public $timeout = 520;

    public function __construct(AiGenerationHistory $data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        $deepseekService = app(\App\Services\deepseek\DeepSeekBaseService::class);

        try {
            $finalPrompt = $this->getMacroses($this->data->prompt);
            $finalPrompt .= $this->getCancelWords();

            if ($this->data->parrameters['source'] === AiGenerationHistory::SOURCE_PARSE_HTML) {
                $link = $this->data->parrameters['link'];
                $htmlContent = TextAnalyzer::removeStylesAndScripts(
                    TextAnalyzer::curlInitV2($link)
                );
                $htmlMax = (int) config('integration_api.ai_html_max_chars', 80000);
                if ($htmlMax > 0 && is_string($htmlContent) && mb_strlen($htmlContent) > $htmlMax) {
                    $htmlContent = mb_substr($htmlContent, 0, $htmlMax) . "\n…[truncated]";
                }

                $finalPrompt .= "\n\nНиже приведено содержимое страницы. Используй его для выполнения задачи:\n";
                $finalPrompt .= "=== НАЧАЛО КОНТЕНТА ===\n";
                $finalPrompt .= $htmlContent;
                $finalPrompt .= "\n=== КОНЕЦ КОНТЕНТА ===\n";
            }

            // TLP — после HTML: иначе модель «забывает» длинный список слов.
            $finalPrompt .= $this->getWords();

            $this->data->result = $deepseekService->request($finalPrompt);
            $usage = $deepseekService->getLastUsageDetails();
            $this->data->used_tokens = (int) ($usage['total_tokens'] ?? $deepseekService->getLastUsageTokens());
            if (\Illuminate\Support\Facades\Schema::hasColumn('ai_generation_histories', 'prompt_tokens')) {
                $this->data->prompt_tokens = (int) ($usage['prompt_tokens'] ?? 0);
                $this->data->completion_tokens = (int) ($usage['completion_tokens'] ?? 0);
            }

            $type = (string) ($this->data->parrameters['type'] ?? $this->data->type);
            if ($type === 'phrase_batch') {
                $items = $this->data->parrameters['items'] ?? [];
                $expected = is_array($items) ? count($items) : (int) ($this->data->parrameters['items_count'] ?? 0);
                $phrases = app(\App\Services\Integration\AiGenerateService::class)
                    ->parsePhraseBatchResult((string) $this->data->result, $expected);
                $this->data->result = json_encode($phrases, JSON_UNESCAPED_UNICODE);
            }

            $this->data->status = AiGenerationHistory::COMPLETED;
            $this->data->save();
        } catch (Exception $e) {
            $this->data->result = $e->getMessage();
            $this->data->status = AiGenerationHistory::FAILED;
            $this->data->save();
        }
    }

    private function getMacroses($finalPrompt) {
        if (preg_match_all('/--(.+?)--/', $finalPrompt, $matches)) {
            $macroNames = array_unique($matches[1]);

            $macros = \App\AiGenerationMacro::where('user_id', $this->data->user_id)
                ->whereIn('name', $macroNames)
                ->get()
                ->keyBy('name');

            foreach ($macroNames as $macroName) {
                if ($macros->has($macroName)) {
                    $finalPrompt = str_replace(
                        '--' . $macroName . '--', 
                        $macros->get($macroName)->content, 
                        $finalPrompt
                    );
                }
            }
        }

        return $finalPrompt;
    }

    private function getWords() {
        $keywords = $this->data->parrameters['keywords'] ?? null;
        if (!is_array($keywords) || $keywords === []) {
            return '';
        }

        $priorityLimit = 40;
        $restCap = max(0, (int) config('integration_api.ai_keywords_max', 80) - $priorityLimit);
        $priority = [];
        $rest = [];
        $i = 0;
        foreach ($keywords as $item) {
            if (!is_array($item)) {
                continue;
            }
            $word = trim((string) ($item['word'] ?? ''));
            if ($word === '') {
                continue;
            }
            $count = (int) ($item['count'] ?? 1);
            if ($count < 1) {
                $count = 1;
            }
            $row = ['word' => $word, 'count' => $count];
            if ($i < $priorityLimit) {
                $priority[] = $row;
            } elseif (count($rest) < $restCap) {
                $rest[] = $row;
            } else {
                break;
            }
            $i++;
        }

        if ($priority === [] && $rest === []) {
            return '';
        }

        $addWords = "\n\n=== ТОП-ЛИСТ ФРАЗ (TLP) ===\n"
            . "Слова отсортированы по важности в выдаче (TF-IDF ТОП). Склонения и падежи разрешены.\n"
            . "Текст должен остаться грамотным, по теме товара/раздела и читаемым.\n"
            . "НЕ вставляй слова, которые не относятся к описываемому товару/категории\n"
            . "(другие типы оборудования, чужие бренды линейки, стоматология к УЗИ и т.п.) —\n"
            . "такие слова из списка ПРОПУСКАЙ, даже если они в блоке ниже.\n"
            . "Не превращай описание в свалку ключей и не раздувай off-topic абзацы ради TLP.\n\n";

        if ($priority !== []) {
            $addWords .= "ПРИОРИТЕТ — по возможности вплети каждое уместное слово ниже "
                . "(минимум указанное число раз; это первые " . count($priority) . " по важности).\n"
                . "Если слово явно не про этот товар — пропусти без замены на бессмыслицу:\n";
            foreach ($priority as $item) {
                // Не требуем 7–8 повторов на слово — модель тогда игнорирует весь список.
                $times = min(max(1, (int) $item['count']), 2);
                $addWords .= "- {$item['word']} — минимум {$times} раз (если уместно)\n";
            }
            $addWords .= "\n";
        }

        if ($rest !== []) {
            $addWords .= "ЖЕЛАТЕЛЬНО — только уместные по теме (по 1 разу, где есть смысл):\n";
            foreach ($rest as $item) {
                $addWords .= "- {$item['word']}\n";
            }
            $addWords .= "\n";
        }

        $addWords .= "Перед ответом проверь: уместные слова из «ПРИОРИТЕТ» есть в тексте "
            . "(с учётом склонений). Off-topic слова не пихай через силу.\n";

        return $addWords;
    }

    private function getCancelWords() {
        $cancelWords = '';
        if (isset($this->data->parrameters['stopwords']) && is_array($this->data->parrameters['stopwords'])) {
            $cancelWords = "\nСлова которые запрещенно использовать в любом числе и падеже:\n";
            foreach ($this->data->parrameters['stopwords'] as $word) {
                if (is_array($word)) {
                    $word = (string) ($word['word'] ?? '');
                }
                $word = trim((string) $word);
                if ($word === '') {
                    continue;
                }
                $cancelWords .= '- ' . $word . "\n";
            }
        }

        return $cancelWords;
    }
}
