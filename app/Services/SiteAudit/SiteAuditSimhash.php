<?php

namespace App\Services\SiteAudit;

/**
 * 64-bit SimHash по токенам текста + 5-граммный второй проход (для «похожих страниц»).
 * SimHash хранится как 16 hex-символов; шинголовы — список строк в shingles_json.
 */
class SiteAuditSimhash
{
    public static function fromText(string $text): ?string
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
        if ($text === '' || mb_strlen($text) < 40) {
            return null;
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($tokens) || count($tokens) < 8) {
            return null;
        }

        $bits = array_fill(0, 64, 0);
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }
            list($hi, $lo) = self::hash64Parts($token);
            for ($i = 0; $i < 32; $i++) {
                $bits[$i] += (($lo >> $i) & 1) ? 1 : -1;
                $bits[$i + 32] += (($hi >> $i) & 1) ? 1 : -1;
            }
        }

        $loOut = 0;
        $hiOut = 0;
        for ($i = 0; $i < 32; $i++) {
            if ($bits[$i] > 0) {
                $loOut |= (1 << $i);
            }
            if ($bits[$i + 32] > 0) {
                $hiOut |= (1 << $i);
            }
        }

        return sprintf('%08x%08x', $hiOut & 0xffffffff, $loOut & 0xffffffff);
    }

    public static function hamming(?string $a, ?string $b): int
    {
        if ($a === null || $b === null || strlen($a) !== 16 || strlen($b) !== 16) {
            return 64;
        }
        if (! ctype_xdigit($a) || ! ctype_xdigit($b)) {
            return 64;
        }

        $binA = @hex2bin($a);
        $binB = @hex2bin($b);
        if ($binA === false || $binB === false || strlen($binA) !== 8) {
            return 64;
        }

        $pa = unpack('N2', $binA);
        $pb = unpack('N2', $binB);
        $x1 = ((int) $pa[1]) ^ ((int) $pb[1]);
        $x2 = ((int) $pa[2]) ^ ((int) $pb[2]);

        return self::popcount32($x1) + self::popcount32($x2);
    }

    /**
     * N-граммы по подряд идущим словам. При длинном тексте — равномерная выборка (не только шапка).
     *
     * @return list<string>
     */
    public static function shinglesFromText(string $text, ?int $size = null, ?int $maxStore = null): array
    {
        $size = $size !== null ? max(2, $size) : max(2, (int) config('site_audit.simhash_shingle_size', 5));
        $maxStore = $maxStore !== null ? max($size, $maxStore) : max($size, (int) config('site_audit.simhash_shingle_store_max', 120));

        $tokens = self::shingleTokens($text);
        if (count($tokens) < $size) {
            return [];
        }

        $all = [];
        $last = count($tokens) - $size;
        for ($i = 0; $i <= $last; $i++) {
            $all[] = implode(' ', array_slice($tokens, $i, $size));
        }
        $all = array_values(array_unique($all));
        $n = count($all);
        if ($n <= $maxStore) {
            return $all;
        }

        // Равномерно по документу — иначе в сэмпл попадёт только меню в начале.
        $out = [];
        $seen = [];
        for ($k = 0; $k < $maxStore; $k++) {
            $idx = (int) floor($k * ($n - 1) / max(1, $maxStore - 1));
            $s = $all[$idx];
            if (isset($seen[$s])) {
                continue;
            }
            $seen[$s] = true;
            $out[] = $s;
        }

        return $out;
    }

    /**
     * Доля общих шинголов: |∩| / min(|A|,|B|).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array{ratio:float,shared:int,total_a:int,total_b:int,samples:list<string>}
     */
    public static function shingleOverlap(array $a, array $b, int $sampleLimit = 8): array
    {
        $setA = [];
        foreach ($a as $s) {
            $s = trim((string) $s);
            if ($s !== '') {
                $setA[$s] = true;
            }
        }
        $setB = [];
        foreach ($b as $s) {
            $s = trim((string) $s);
            if ($s !== '') {
                $setB[$s] = true;
            }
        }
        $na = count($setA);
        $nb = count($setB);
        if ($na < 1 || $nb < 1) {
            return [
                'ratio' => 0.0,
                'shared' => 0,
                'total_a' => $na,
                'total_b' => $nb,
                'samples' => [],
            ];
        }

        $shared = 0;
        $samples = [];
        foreach ($setA as $s => $_) {
            if (! isset($setB[$s])) {
                continue;
            }
            $shared++;
            if (count($samples) < $sampleLimit) {
                $samples[] = $s;
            }
        }
        $denom = min($na, $nb);

        return [
            'ratio' => $denom > 0 ? round($shared / $denom, 4) : 0.0,
            'shared' => $shared,
            'total_a' => $na,
            'total_b' => $nb,
            'samples' => $samples,
        ];
    }

    /**
     * Нормализовать shingles_json страницы в список строк.
     *
     * @param  mixed  $raw
     * @return list<string>
     */
    public static function normalizeShingleList($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($raw) || $raw === []) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $s = trim($item);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private static function shingleTokens(string $text): array
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [];
        }
        $out = [];
        foreach ($parts as $w) {
            if (mb_strlen($w) < 2) {
                continue;
            }
            $out[] = $w;
        }

        return $out;
    }

    private static function popcount32(int $x): int
    {
        $x = $x & 0xffffffff;
        $c = 0;
        while ($x !== 0) {
            $x &= ($x - 1);
            $c++;
            // safety
            if ($c > 32) {
                break;
            }
        }

        return $c;
    }

    /**
     * @return int[] [hi, lo] unsigned 32-bit
     */
    private static function hash64Parts(string $token): array
    {
        $h1 = crc32($token);
        $h2 = crc32(strrev($token) . '#sa');
        // crc32 returns signed on some platforms
        return [$h1 & 0xffffffff, $h2 & 0xffffffff];
    }
}
