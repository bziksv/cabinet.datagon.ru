<?php

namespace App\Services\Demo;

use App\Services\EseninTextCheckService;
use App\Support\Esenin\EseninAnalyzer;
use App\Support\TextAnalyzerPdfBranding;

class EseninTextCheckDemoService
{
    public const MODULE = 'proverka-teksta-esenin';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-esenin-text-check.demo', []);
    }

    /**
     * @param array{
     *   source?: string,
     *   text?: string,
     *   url?: string,
     *   tbclass?: string,
     *   mode?: string
     * } $input
     * @return array{ok: true, source: string, text?: string, url?: string, tbclass?: string, mode: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $cfg = self::config();
        $maxChars = (int) ($cfg['max_chars'] ?? 5000);
        $minChars = (int) ($cfg['min_chars'] ?? 100);
        $source = (string) ($input['source'] ?? 'text');
        $source = $source === 'url' ? 'url' : 'text';
        $mode = EseninTextCheckService::normalizeMode((string) ($input['mode'] ?? 'risk'));

        if ($source === 'url') {
            $url = trim((string) ($input['url'] ?? ''));
            if ($url === '') {
                return self::fail(422, 'validation', 'Укажите URL страницы для проверки');
            }
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return self::fail(422, 'validation', 'URL должен быть корректным (http или https)');
            }

            return [
                'ok' => true,
                'source' => 'url',
                'url' => $url,
                'tbclass' => trim((string) ($input['tbclass'] ?? '')),
                'mode' => $mode,
            ];
        }

        $text = (string) ($input['text'] ?? '');
        if (trim($text) === '') {
            return self::fail(422, 'validation', 'Вставьте текст для проверки');
        }

        $plainLength = EseninAnalyzer::plainTextLength($text);
        if ($plainLength < $minChars) {
            return self::fail(
                422,
                'validation',
                sprintf('В демо минимум %d символов текста.', $minChars)
            );
        }
        if ($plainLength > $maxChars) {
            $full = (int) ($cfg['full_max_chars'] ?? config('cabinet-esenin-text-check.max_chars', 20000));

            return self::fail(
                422,
                'validation',
                sprintf(
                    'В демо до %s символов. Полный лимит %s — в кабинете.',
                    number_format($maxChars, 0, ',', ' '),
                    number_format($full, 0, ',', ' ')
                )
            );
        }

        return [
            'ok' => true,
            'source' => 'text',
            'text' => $text,
            'mode' => $mode,
        ];
    }

    /**
     * @param array{source: string, text?: string, url?: string, tbclass?: string, mode: string} $validated
     * @return array<string, mixed>
     */
    public static function check(array $validated): array
    {
        if ($validated['source'] === 'url') {
            return EseninTextCheckService::checkUrl((string) $validated['url'], [
                'mode' => $validated['mode'],
                'more' => true,
                'tbclass' => (string) ($validated['tbclass'] ?? ''),
            ]);
        }

        return EseninTextCheckService::checkText((string) $validated['text'], [
            'mode' => $validated['mode'],
            'more' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 3);

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_runs_per_day' => $maxRuns,
                'max_chars' => (int) ($cfg['max_chars'] ?? 5000),
                'min_chars' => (int) ($cfg['min_chars'] ?? 100),
                'full_max_chars' => (int) ($cfg['full_max_chars'] ?? config('cabinet-esenin-text-check.max_chars', 20000)),
                'cost_per_check' => EseninTextCheckService::costPerCheck(),
            ],
            'result' => $result,
            'upgrade' => [
                'register_url' => url('/register?module=' . self::MODULE . '&from=demo&guest=' . urlencode($guestId)),
                'login_url' => TextAnalyzerPdfBranding::loginUrl(),
            ],
        ];
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'error' => $error, 'message' => $message];
    }
}
