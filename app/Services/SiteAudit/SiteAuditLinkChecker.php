<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;

/**
 * Лёгкая проверка URL (HEAD → GET fallback) для битых ссылок.
 */
class SiteAuditLinkChecker
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client([
            'timeout' => 8,
            'connect_timeout' => 5,
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
        try {
            $response = $this->client->request('HEAD', $url);
            $code = $response->getStatusCode();
            // некоторые сервера не любят HEAD
            if ($code === 405 || $code === 501) {
                $response = $this->client->request('GET', $url, [
                    'headers' => ['Range' => 'bytes=0-0'],
                ]);
                $code = $response->getStatusCode();
            }

            $ok = $code >= 200 && $code < 400;
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
