<?php

namespace App\Services;

use App\Support\Esenin\EseninAnalyzer;
use App\Support\EseninTextCheckSettingsRegistry;

class EseninTextCheckService
{
    /** @var array<string, string> */
    public const MODES = [
        'risk' => 'Общий риск',
        'frequency' => 'Повторы',
        'style' => 'Стилистика',
        'keywords' => 'Запросы',
        'formality' => 'Водность',
        'readability' => 'Удобочитаемость',
    ];

    /**
     * @param array{mode?: string} $options
     * @return array<string, mixed>
     */
    public static function checkText(string $text, array $options = []): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Текст для проверки не указан');
        }

        $maxChars = EseninTextCheckSettingsRegistry::moduleInt('max_chars', 20000);
        $plainLength = EseninAnalyzer::plainTextLength($text);
        if ($plainLength > $maxChars) {
            throw new \InvalidArgumentException(sprintf(
                'Максимум %s символов текста за одну проверку',
                number_format($maxChars, 0, ',', ' ')
            ));
        }

        $mode = self::normalizeMode((string) ($options['mode'] ?? EseninTextCheckSettingsRegistry::moduleString('default_mode', 'risk')));

        return EseninAnalyzer::analyze($text, $mode, [
            'url' => $options['url'] ?? null,
            'tbclass' => $options['tbclass'] ?? null,
        ]);
    }

    /**
     * @param array{mode?: string, tbclass?: ?string} $options
     * @return array<string, mixed>
     */
    public static function checkUrl(string $url, array $options = []): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new \InvalidArgumentException('URL не указан');
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Некорректный URL');
        }

        $text = EseninAnalyzer::extractTextFromUrl($url, trim((string) ($options['tbclass'] ?? '')));
        if ($text === '') {
            throw new \InvalidArgumentException('Не удалось выделить текст на странице');
        }

        return self::checkText($text, array_merge($options, [
            'url' => $url,
            'tbclass' => trim((string) ($options['tbclass'] ?? '')),
        ]));
    }

    public static function levelClass(?int $score): string
    {
        if ($score === null) {
            return 'secondary';
        }

        if ($score >= 13) {
            return 'danger';
        }
        if ($score >= 8) {
            return 'warning';
        }
        if ($score >= 5) {
            return 'info';
        }

        return 'success';
    }

    public static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        if (! array_key_exists($mode, self::MODES)) {
            throw new \InvalidArgumentException('Неизвестный режим проверки');
        }

        return $mode;
    }

    public static function costPerCheck(): int
    {
        return max(1, EseninTextCheckSettingsRegistry::moduleInt('cost_per_check', 1));
    }
}
