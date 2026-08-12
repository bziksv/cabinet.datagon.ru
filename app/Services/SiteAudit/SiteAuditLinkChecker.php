<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Лёгкая проверка URL (HEAD → GET fallback) для битых ссылок.
 *
 * Многие сайты (WAF / Bitrix / антибот) рвут TLS или HTTP/2 на «ботский» UA
 * или на HEAD, хотя в браузере страница открывается. Поэтому при сбое/антибот-коде
 * повторяем GET с HTTP/1.1 и более «браузерным» User-Agent.
 */
class SiteAuditLinkChecker
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client([
            'timeout' => (float) config('site_audit.link_check_timeout', 12),
            'connect_timeout' => (float) config('site_audit.link_check_connect_timeout', 6),
            'http_errors' => false,
            'allow_redirects' => ['max' => 5],
            'verify' => true,
            'headers' => [
                'User-Agent' => (string) config('site_audit.user_agent', 'TitloSiteAuditBot/1.0'),
                'Accept' => '*/*',
            ],
        ]);
    }

    /**
     * @return array{ok:bool,status:?int,error:?string,size_bytes:?int,content_type:?string}
     */
    public function check(string $url): array
    {
        $first = $this->attempt($url, 'HEAD', false);
        if ($this->isOk($first)) {
            return $first;
        }

        if ($this->shouldRetryAsBrowserGet($first)) {
            $second = $this->attempt($url, 'GET', true);
            // Удачный ответ или хотя бы HTTP-код важнее «тихого» SSL/eof.
            if ($this->isOk($second) || $second['status'] !== null) {
                return $second;
            }
        } elseif ($this->shouldRetryGetSameUa($first)) {
            $second = $this->attempt($url, 'GET', false);
            if ($this->isOk($second) || $second['status'] !== null) {
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
    private function shouldRetryAsBrowserGet(array $result): bool
    {
        if (! empty($result['error'])) {
            return true;
        }
        $code = (int) ($result['status'] ?? 0);

        // Антибот / временные отказы — часто ложные «битые» для внешних кейсов.
        return in_array($code, [403, 418, 429, 503], true);
    }

    /**
     * @param  array{ok:bool,status:?int,error:?string,size_bytes:?int,content_type:?string}  $result
     */
    private function shouldRetryGetSameUa(array $result): bool
    {
        $code = (int) ($result['status'] ?? 0);

        return in_array($code, [405, 501], true);
    }

    /**
     * @return array{ok:bool,status:?int,error:?string,size_bytes:?int,content_type:?string}
     */
    private function attempt(string $url, string $method, bool $browserLike): array
    {
        $headers = [
            'Accept' => $browserLike
                ? 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                : '*/*',
        ];
        if ($browserLike) {
            $headers['User-Agent'] = (string) config(
                'site_audit.link_check_browser_ua',
                'Mozilla/5.0 (compatible; TitloSiteAuditBot/1.0; +https://titlo.ru) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            );
            $headers['Accept-Language'] = 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7';
        }
        if (strtoupper($method) === 'GET') {
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
