<?php

namespace App\UniqueWords;

use App\Helpers\WordHelper;

/**
 * Для каждой словоформы возвращает исходные ключевые фразы (строки ввода),
 * в которых она встречается — без склейки соседних строк и «шинголов» по морфологии.
 */
class ShinglesWord
{
    protected $text = '';

    public function getShinglesAroundWord(array $words): array
    {
        $formSet = [];
        foreach ($words as $word) {
            $formSet[mb_strtoupper((string) $word)] = true;
        }

        if ($formSet === []) {
            return [];
        }

        $phrases = [];
        $lines = preg_split('/\r\n|\n|\r/', $this->getText()) ?: [];

        foreach ($lines as $line) {
            $original = trim((string) $line);
            if ($original === '') {
                continue;
            }

            foreach (WordHelper::getWordUpperArray($original) as $token) {
                if (isset($formSet[$token])) {
                    $phrases[] = $original;
                    break;
                }
            }
        }

        return $phrases;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }
}
