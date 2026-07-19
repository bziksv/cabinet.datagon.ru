<?php

namespace App\Support;

use App\Services\EseninTextCheckService;
use App\Support\Esenin\EseninAnalyzer;
use App\Support\Esenin\EseninHtmlHighlighter;
use App\TextAnalyzer;
use App\User;

/**
 * Проверка «Есенин» из модуля «Анализ текста».
 */
class TextAnalyzerEsenin
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     * @return array{response: array<string, mixed>, esenin_error: string|null}
     */
    public static function attach(array $request, array $response, string $plain, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $eseninError = null;

        if (! TextAnalyzer::shouldCheckEsenin($request)) {
            return [
                'response' => $response,
                'esenin_error' => null,
            ];
        }

        if ($user && method_exists($user, 'can') && ! $user->can('Esenin text check')) {
            $response['esenin'] = [
                'error' => true,
                'message' => __('Text analyzer esenin no permission'),
            ];

            return [
                'response' => $response,
                'esenin_error' => $response['esenin']['message'],
            ];
        }

        $cost = EseninTextCheckLimits::checkCost();
        if (! EseninTextCheckLimits::canSpend($cost, $user)) {
            $response['esenin'] = [
                'error' => true,
                'message' => EseninTextCheckLimits::limitMessage($user)
                    ?: __('Your limits are exhausted this month'),
            ];

            return [
                'response' => $response,
                'esenin_error' => $response['esenin']['message'],
            ];
        }

        if (trim($plain) === '') {
            $response['esenin'] = [
                'error' => true,
                'message' => __('Text analyzer esenin empty text'),
            ];

            return [
                'response' => $response,
                'esenin_error' => $response['esenin']['message'],
            ];
        }

        try {
            @set_time_limit(600);
            $result = EseninTextCheckService::checkText($plain, ['mode' => 'risk']);
            EseninTextCheckLimits::spend($cost, $user);
            $response['esenin'] = self::summarize($result, $plain);
            $response['esenin']['cost'] = $cost;
        } catch (\InvalidArgumentException $e) {
            $eseninError = $e->getMessage();
            $response['esenin'] = ['error' => true, 'message' => $eseninError];
        } catch (\Throwable $e) {
            report($e);
            $eseninError = __('Text analyzer esenin failed');
            $response['esenin'] = ['error' => true, 'message' => $eseninError];
        }

        return [
            'response' => $response,
            'esenin_error' => $eseninError,
        ];
    }

    /**
     * Компактный снимок для session / результатов анализатора.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function summarize(array $result, string $plain = ''): array
    {
        $metricKeys = [
            'wateriness',
            'academic_nausea',
            'classic_nausea',
            'informative_share',
            'readability_index',
        ];
        $metrics = [];
        foreach ($metricKeys as $key) {
            if (array_key_exists($key, $result['metrics'] ?? [])) {
                $metrics[$key] = $result['metrics'][$key];
            }
        }

        $blocks = [];
        foreach ($result['blocks'] ?? [] as $code => $block) {
            $blocks[$code] = [
                'score' => (int) ($block['score'] ?? 0),
            ];
        }

        // Не раздуваем session всеми блоками подсветки — risk + активные с оценкой > 0
        $allHighlights = $result['highlights'] ?? [];
        $highlights = [];
        if (isset($allHighlights['risk'])) {
            $highlights['risk'] = $allHighlights['risk'];
        }
        foreach ($blocks as $code => $block) {
            if ((int) ($block['score'] ?? 0) > 0 && isset($allHighlights[$code])) {
                $highlights[$code] = $allHighlights[$code];
            }
        }
        if ($highlights === [] && ! empty($result['highlighted_html'])) {
            $highlights['risk'] = $result['highlighted_html'];
        }

        return [
            'risk' => (int) ($result['risk'] ?? 0),
            'level' => (string) ($result['level'] ?? ''),
            'details' => $result['details'] ?? [],
            'metrics' => $metrics,
            'blocks' => $blocks,
            'stats' => $result['stats'] ?? [],
            'text' => EseninHtmlHighlighter::isHtml($plain)
                ? trim(EseninAnalyzer::extractPlainText($plain))
                : $plain,
            'source_html' => EseninHtmlHighlighter::isHtml($plain) ? $plain : '',
            'highlighted_html' => (string) ($highlights['risk'] ?? ($result['highlighted_html'] ?? '')),
            'highlights' => $highlights,
            'module_url' => url('/esenin-text-check'),
        ];
    }
}
