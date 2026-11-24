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
        $header = null;
        $headerUsed = false;

        foreach (preg_split('/\r\n|\r|\n/u', $text) as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            if (mb_substr($line, 0, 1) === '#') {
                self::rejectEmptyHeader($header, $headerUsed, $rejected);
                $name = trim(mb_substr($line, 1));
                $current = $name !== '' ? self::cleanGroup($name) : $defaultGroup;
                $header = $name !== '' ? $line : null;
                $headerUsed = false;
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
            if ($group === $current) {
                $headerUsed = true;
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

        self::rejectEmptyHeader($header, $headerUsed, $rejected);

        return ['seeds' => $seeds, 'rejected' => $rejected];
    }

    private static function rejectEmptyHeader(?string $header, bool $used, array &$rejected): void
    {
        if ($header !== null && ! $used) {
            $rejected[] = ['line' => mb_substr($header, 0, 120), 'reason' => 'empty_group'];
        }
    }

    private static function cleanGroup(string $name): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $name)), 0, 255);
    }
}
