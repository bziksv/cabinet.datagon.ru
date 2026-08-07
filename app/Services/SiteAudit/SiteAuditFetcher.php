<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\RedirectMiddleware;
use Psr\Http\Message\ResponseInterface;

class SiteAuditFetcher
{
    /** @var Client */
    private $client;

    /** @var int|null */
    private $crawlId;

    public function __construct(?Client $client = null, ?int $crawlId = null)
    {
        $this->crawlId = $crawlId;
        $this->client = $client ?: new Client([
            'timeout' => config('site_audit.request_timeout', 15),
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => config('site_audit.max_redirects', 10),
                'track_redirects' => true,
            ],
            'verify' => true,
            'headers' => [],
            // keep-alive между URL одного краула
            'curl' => defined('CURLOPT_TCP_KEEPALIVE') ? [CURLOPT_TCP_KEEPALIVE => 1] : [],
        ]);
    }

    public static function fromCrawlSettings(array $settings, ?int $crawlId = null): self
    {
        return new self(null, $crawlId);
    }

    /**
     * @return array{ok:bool,status_code:?int,final_url:string,redirect_chain:array,body:?string,body_path:?string,size_bytes:int,content_type:?string,x_robots:?string,sec_headers:array,error:?string,user_agent:?string,ua_rotated:bool}
     */
    public function fetch(string $url): array
    {
        $ua = $this->resolveUa();
        $result = $this->doRequest($url, $ua);

        $bad = SiteAuditUserAgentSession::shouldRotate(
            $result['status_code'],
            ! $result['ok'] && ($result['body'] ?? null) === null && ($result['body_path'] ?? null) === null
        );

        if ($this->crawlId && $bad) {
            SiteAuditBodyTemp::release($result['body_path'] ?? null);
            $ua = SiteAuditUserAgentSession::rotate($this->crawlId, $ua);
            $retry = $this->doRequest($url, $ua);
            $retry['ua_rotated'] = true;
            $retry['user_agent'] = $ua;

            return $retry;
        }

        $result['ua_rotated'] = false;
        $result['user_agent'] = $ua;

        return $result;
    }

    private function resolveUa(): string
    {
        if ($this->crawlId) {
            return SiteAuditUserAgentSession::current($this->crawlId, true);
        }

        $pool = config('site_audit.user_agents', []);
        if (is_array($pool) && $pool) {
            return $pool[array_rand($pool)];
        }

        return (string) config('site_audit.user_agent', 'Mozilla/5.0');
    }

    /**
     * Параллельная загрузка URL (Guzzle Pool). Порядок результатов = порядок $urls.
     *
     * @param string[] $urls
     * @param callable|null $beforeEach fn(string $url): void — throttle перед стартом запроса
     * @return array<int, array>
     */
    public function fetchMany(array $urls, int $concurrency = 1, ?callable $beforeEach = null): array
    {
        $urls = array_values($urls);
        if ($urls === []) {
            return [];
        }

        $concurrency = max(1, $concurrency);
        if ($concurrency === 1 || count($urls) === 1) {
            $out = [];
            foreach ($urls as $url) {
                if ($beforeEach) {
                    $beforeEach($url);
                }
                $out[] = $this->fetch($url);
            }

            return $out;
        }

        $ua = $this->resolveUa();
        $useTemp = SiteAuditBodyTemp::enabled();
        /** @var array<int, string|null> $bodyPaths */
        $bodyPaths = [];
        /** @var array<int, array|null> $results */
        $results = array_fill(0, count($urls), null);

        $requests = function () use ($urls, $ua, $useTemp, &$bodyPaths, $beforeEach) {
            foreach ($urls as $index => $url) {
                yield $index => function () use ($url, $index, $ua, $useTemp, &$bodyPaths, $beforeEach) {
                    if ($beforeEach) {
                        $beforeEach($url);
                    }
                    $options = ['headers' => $this->browserHeaders($ua)];
                    if ($useTemp) {
                        $bodyPath = SiteAuditBodyTemp::allocate($this->crawlId);
                        $bodyPaths[$index] = $bodyPath;
                        $options['sink'] = $bodyPath;
                    } else {
                        $bodyPaths[$index] = null;
                    }

                    return $this->client->getAsync($url, $options);
                };
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response, $index) use ($urls, $ua, &$results, &$bodyPaths) {
                $index = (int) $index;
                $results[$index] = $this->normalizeResponse(
                    $urls[$index],
                    $ua,
                    $response,
                    $bodyPaths[$index] ?? null
                );
                $results[$index]['ua_rotated'] = false;
            },
            'rejected' => function ($reason, $index) use ($urls, $ua, &$results, &$bodyPaths) {
                $index = (int) $index;
                $bodyPath = $bodyPaths[$index] ?? null;
                SiteAuditBodyTemp::release($bodyPath);
                $status = null;
                $message = is_object($reason) && method_exists($reason, 'getMessage')
                    ? $reason->getMessage()
                    : (string) $reason;
                if ($reason instanceof RequestException && $reason->hasResponse() && $reason->getResponse()) {
                    $status = $reason->getResponse()->getStatusCode();
                }
                $results[$index] = $this->fail($urls[$index], $ua, $status, $message);
            },
        ]);
        $pool->promise()->wait();

        // sticky UA: если волна «плохая» — ротация к следующей
        if ($this->crawlId) {
            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }
                $bad = SiteAuditUserAgentSession::shouldRotate(
                    $result['status_code'] ?? null,
                    ! ($result['ok'] ?? false) && ($result['body'] ?? null) === null && ($result['body_path'] ?? null) === null
                );
                if ($bad) {
                    SiteAuditUserAgentSession::rotate($this->crawlId, $ua);
                    break;
                }
            }
        }

        foreach ($results as $i => $result) {
            if (! is_array($result)) {
                $results[$i] = $this->fail($urls[$i], $ua, null, 'empty parallel result');
            }
        }

        return $results;
    }

    private function doRequest(string $url, string $ua): array
    {
        $headers = $this->browserHeaders($ua);
        $useTemp = SiteAuditBodyTemp::enabled();
        $bodyPath = null;

        try {
            $options = ['headers' => $headers];
            if ($useTemp) {
                $bodyPath = SiteAuditBodyTemp::allocate($this->crawlId);
                $options['sink'] = $bodyPath;
            }

            $response = $this->client->get($url, $options);

            return $this->normalizeResponse($url, $ua, $response, $useTemp ? $bodyPath : null);
        } catch (RequestException $e) {
            SiteAuditBodyTemp::release($bodyPath);

            return $this->fail($url, $ua, $e->hasResponse() && $e->getResponse()
                ? $e->getResponse()->getStatusCode()
                : null, $e->getMessage());
        } catch (\Throwable $e) {
            SiteAuditBodyTemp::release($bodyPath);

            return $this->fail($url, $ua, null, $e->getMessage());
        }
    }

    /**
     * @return array{ok:bool,status_code:?int,final_url:string,redirect_chain:array,body:?string,body_path:?string,size_bytes:int,content_type:?string,x_robots:?string,sec_headers:array,error:?string,user_agent:?string,ua_rotated:bool}
     */
    private function normalizeResponse(string $url, string $ua, ResponseInterface $response, ?string $bodyPath): array
    {
        $status = $response->getStatusCode();
        $history = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
        $chain = [];
        if (is_array($history) && $history) {
            foreach ($history as $h) {
                $chain[] = $h;
            }
        }
        $final = $url;
        if ($chain) {
            $final = end($chain) ?: $url;
            reset($chain);
        }
        $hist = $response->getHeader('X-Guzzle-Redirect-History');
        if ($hist) {
            $final = end($hist) ?: $final;
        }

        $maxBytes = max(1, (int) config('site_audit.large_page_bytes', 1_500_000));
        $body = null;
        $size = 0;
        $useTemp = $bodyPath !== null && $bodyPath !== '';

        if ($useTemp) {
            $size = is_file($bodyPath) ? (int) filesize($bodyPath) : 0;
            if ($size > $maxBytes) {
                $fh = @fopen($bodyPath, 'c+b');
                if ($fh) {
                    ftruncate($fh, $maxBytes);
                    fclose($fh);
                    $size = $maxBytes;
                }
            }
        } else {
            $body = (string) $response->getBody();
            $size = strlen($body);
            if ($size > $maxBytes) {
                $body = substr($body, 0, $maxBytes);
                $size = strlen($body);
            }
        }

        return [
            'ok' => true,
            'status_code' => $status,
            'final_url' => $final,
            'redirect_chain' => $chain,
            'body' => $body,
            'body_path' => $useTemp ? $bodyPath : null,
            'size_bytes' => $size,
            'content_type' => $response->getHeaderLine('Content-Type') ?: null,
            'x_robots' => $response->getHeaderLine('X-Robots-Tag') ?: null,
            'sec_headers' => [
                'hsts' => $response->getHeaderLine('Strict-Transport-Security') !== '',
                'x_frame' => $response->getHeaderLine('X-Frame-Options') !== '',
                'x_content_type' => $response->getHeaderLine('X-Content-Type-Options') !== '',
                'csp' => $response->getHeaderLine('Content-Security-Policy') !== '',
                'referrer_policy' => $response->getHeaderLine('Referrer-Policy') !== '',
                'permissions_policy' => $response->getHeaderLine('Permissions-Policy') !== ''
                    || $response->getHeaderLine('Feature-Policy') !== '',
                'coop' => $response->getHeaderLine('Cross-Origin-Opener-Policy') !== '',
                'coep' => $response->getHeaderLine('Cross-Origin-Embedder-Policy') !== '',
                'corp' => $response->getHeaderLine('Cross-Origin-Resource-Policy') !== '',
            ],
            'error' => null,
            'user_agent' => $ua,
            'ua_rotated' => false,
        ];
    }

    private function fail(string $url, string $ua, ?int $status, string $error): array
    {
        return [
            'ok' => false,
            'status_code' => $status,
            'final_url' => $url,
            'redirect_chain' => [],
            'body' => null,
            'body_path' => null,
            'size_bytes' => 0,
            'content_type' => null,
            'x_robots' => null,
            'sec_headers' => [
                'hsts' => false,
                'x_frame' => false,
                'x_content_type' => false,
                'csp' => false,
                'referrer_policy' => false,
                'permissions_policy' => false,
                'coop' => false,
                'coep' => false,
                'corp' => false,
            ],
            'error' => $error,
            'user_agent' => $ua,
            'ua_rotated' => false,
        ];
    }

    private function browserHeaders(string $ua): array
    {
        $isChrome = stripos($ua, 'Chrome') !== false && stripos($ua, 'Edg') === false;
        $isFirefox = stripos($ua, 'Firefox') !== false;

        $headers = [
            'User-Agent' => $ua,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
            'Connection' => 'keep-alive',
        ];

        if ($isChrome || $isFirefox) {
            $headers['Sec-Fetch-Dest'] = 'document';
            $headers['Sec-Fetch-Mode'] = 'navigate';
            $headers['Sec-Fetch-Site'] = 'none';
            $headers['Sec-Fetch-User'] = '?1';
        }

        return $headers;
    }
}
