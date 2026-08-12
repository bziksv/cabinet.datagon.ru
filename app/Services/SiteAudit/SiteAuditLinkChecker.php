<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Лёгкая проверка URL для битых ссылок (внутренних и внешних).
 *
 * Важно: сразу ходим с браузерным UA + HTTP/1.1. Ботовый TitloSiteAuditBot
 * на внешних кейсах часто рвёт TLS ещё до HTTP (WAF) — «fallback после HEAD»
 * уже бесполезен, если handshake умер.
 *
 * Если первый ответ 403/418/429/503 или сбой — ещё один GET без Range.
 */
class SiteAuditLinkChecker
{
    /** @var Client */
    private $client;

    /** @var string */
    private $browserUa;

    public function __construct(?Client $client = null)
    {
        $this->browserUa = (string) config(
            'site_audit.link_check_browser_ua',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        );

        $this->client = $client ?: new Client([
            'timeout' => (float) config('site_audit.link_check_timeout', 12),
            'connect_timeout' => (float) config('site_audit.link_check_connect_timeout', 6),
            'http_errors' => false,
            'allow_redirects' => ['max' => 5],
            'verify' => true,
            'headers' => [
                'User-Agent' => $this->browserUa,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ]);
    }

    /**
     * @return array{ok:bool,status:?int,error:?string,size_bytes:?int,content_type:?string}
     */
    public function check(string $url): array
    {
        // Сразу GET как браузер: HEAD часто режут, а ботовый UA рвёт TLS.
        $first = $this->attempt($url, 'GET', true);
        if ($this->isOk($first)) {
            return $first;
        }

        if ($this->shouldRetryFullGet($first)) {
            $second = $this->attempt($url, 'GET', false);
            if ($this->isOk($second)) {
                return $second;
            }
            // Если первый был тихим SSL/eof, а второй дал HTTP-код — берём второй.
            if ($first['status'] === null && $second['status'] !== null) {
                return $second;
            }
        }

        return $first;
    }

    /**
     * @param  array{ok:bool,status:?int,error:?string,size_bytes:?int,content_type:?string}  $result
     */
    private function isOk(array $result): bool
    {
        return ! empty($result['ok']);
    }

    /**
     * @param  array{ok:bool,status:?int,error:?string,size_bytes:?int,content_type:?string}  $result
     */
    private function shouldRetryFullGet(array $result): bool
    {
        if (! empty($result['error'])) {
            return true;
        }
        $code = (int) ($result['status'] ?? 0);

        return in_array($code, [403, 405, 418, 429, 501, 503], true);
    }

    /**
     * @return array{ok:bool,status:?int,error:?string,size_bytes:?int,content_type:?string}
     */
    private function attempt(string $url, string $method, bool $withRange): array
    {
        $headers = [
            'User-Agent' => $this->browserUa,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
        ];
        if ($withRange && strtoupper($method) === 'GET') {
            $headers['Range'] = 'bytes=0-0';
        }

        $options = [
            'headers' => $headers,
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ];

        try {
            $response = $this->client->request($method, $url, $options);
            $code = $response->getStatusCode();
            // 206 Partial Content — нормальный ответ на Range.
            $ok = ($code >= 200 && $code < 400) || $code === 206;
            $len = $response->getHeaderLine('Content-Length');
            $size = ($len !== '' && ctype_digit($len)) ? (int) $len : null;
            $ct = trim((string) $response->getHeaderLine('Content-Type'));
            if ($ct !== '') {
                $ct = explode(';', $ct, 2)[0];
                $ct = trim($ct);
            } else {
                $ct = null;
            }

            return [
                'ok' => $ok,
                'status' => $code,
                'error' => null,
                'size_bytes' => $size,
                'content_type' => $ct,
            ];
        } catch (GuzzleException $e) {
            return [
                'ok' => false,
                'status' => null,
                'error' => $e->getMessage(),
                'size_bytes' => null,
                'content_type' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'error' => $e->getMessage(),
                'size_bytes' => null,
                'content_type' => null,
            ];
        }
    }
}
