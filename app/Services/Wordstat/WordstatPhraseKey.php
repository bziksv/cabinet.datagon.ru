<?php
namespace App\Services\Wordstat;

class WordstatPhraseKey
{
    public static function make(string $phrase): string
    {
        $s = mb_strtolower($phrase);
        $s = str_replace('ё', 'е', $s);
        $s = preg_replace('/(^|\s)-\S+/u', ' ', $s);
        $s = preg_replace('/[!+"\[\]()|]/u', ' ', $s);
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return mb_substr($s, 0, 400);
    }

    public static function hash(string $key): string
    {
        return sha1($key);
    }
}
