<?php

namespace Tests\Unit\Wordstat;

use App\Services\Wordstat\WordstatClient;
use App\Services\Wordstat\WordstatException;
use PHPUnit\Framework\TestCase;

class WordstatClientTest extends TestCase
{
    private function client(?string $body, ?string &$calledUrl = null): WordstatClient
    {
        return new WordstatClient('https://api.test/wordstat/json/', 'u1', 'k1', function (string $url) use ($body, &$calledUrl) {
            $calledUrl = $url;

            return $body;
        });
    }

    public function testParsesColumnsAndBuildsUrl(): void
    {
        $body = json_encode([
            'totalCount' => '122908',
            'results' => [['phrase' => 'натяжные потолки', 'count' => '122908'], ['phrase' => 'натяжные потолки цена', 'count' => '8234']],
            'associations' => [['phrase' => 'подвесной потолок', 'count' => '13255']],
        ]);
        $url = null;
        $r = $this->client($body, $url)->words('натяжные потолки', [213, 2], 'phone', 200);

        $this->assertSame(122908, $r->totalCount);
        $this->assertSame(['phrase' => 'натяжные потолки цена', 'count' => 8234], $r->left[1]);
        $this->assertSame([['phrase' => 'подвесной потолок', 'count' => 13255]], $r->right);
        $this->assertStringContainsString('user=u1', $url);
        $this->assertStringContainsString('regions=213%2C2', $url);
        $this->assertStringContainsString('device=phone', $url);
        $this->assertStringContainsString('groupby=200', $url);
        $this->assertStringContainsString('pagetype=words', $url);
    }

    public function testFatalAuthError(): void
    {
        $this->expectException(WordstatException::class);
        try {
            $this->client('{"query":"x","error":{"code":-34,"message":"Не корректный ID пользователя или ключ"}}')->words('x', [213], 'all', 10);
        } catch (WordstatException $e) {
            $this->assertFalse($e->isRetryable());
            throw $e;
        }
    }

    public function testRetryableOnRateLimitAndHttpFailure(): void
    {
        foreach (['{"error":{"code":-55,"message":"Превышено количество запросов в секунду"}}', null, 'not json'] as $body) {
            try {
                $this->client($body)->words('x', [213], 'all', 10);
                $this->fail('expected exception');
            } catch (WordstatException $e) {
                $this->assertTrue($e->isRetryable());
            }
        }
    }

    public function testEmptyColumnsAreNotError(): void
    {
        $r = $this->client('{"totalCount":"0","results":[],"associations":[]}')->words('ыыы', [213], 'all', 10);
        $this->assertSame(0, $r->totalCount);
        $this->assertSame([], $r->left);
    }
}
