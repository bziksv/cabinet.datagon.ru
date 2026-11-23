<?php
namespace App\Services\Wordstat;

class WordstatInputParser
{
    public static function parse(string $text, string $defaultGroup, int $maxLength = 400, int $maxWords = 7): array
    {
        $seeds = [];
        $rejected = [];
        $seen = [];
        $current = $defaultGroup;

        foreach (preg_split('/\r\n|\r|\n/u', $text) as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            if (mb_substr($line, 0, 1) === '#') {
                $name = trim(mb_substr($line, 1));
                $current = $name !== '' ? self::cleanGroup($name) : $defaultGroup;
                continue;
            }

            $group = $current;
            $phrase = $line;

            if (mb_strpos($rawLine, "\t") !== false) {
                [$g, $p] = array_map('trim', explode("\t", $rawLine, 2));
                if ($g !== '' && $p !== '') {
                    $group = self::cleanGroup($g);
                    $phrase = $p;
                }
            } elseif (preg_match('/^([^:\/]{1,120}):\s*(?!\/\/)(.+)$/u', $line, $m)) {
                $group = self::cleanGroup($m[1]);
                $phrase = trim($m[2]);
            }

            $phrase = trim(preg_replace('/\s+/u', ' ', $phrase));
            if (mb_strlen($phrase) > $maxLength) {
                $rejected[] = ['line' => mb_substr($line, 0, 120), 'reason' => 'too_long'];
                continue;
            }
            if (count(preg_split('/\s+/u', WordstatPhraseKey::make($phrase))) > $maxWords) {
                $rejected[] = ['line' => mb_substr($line, 0, 120), 'reason' => 'too_many_words'];
                continue;
            }

            $key = WordstatPhraseKey::make($phrase);
            $dedupe = mb_strtolower($group) . '|' . $key;
            if ($key === '' || isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $seeds[] = ['group' => $group, 'phrase' => $phrase];
        }

        return ['seeds' => $seeds, 'rejected' => $rejected];
    }

    private static function cleanGroup(string $name): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $name)), 0, 255);
    }
}
