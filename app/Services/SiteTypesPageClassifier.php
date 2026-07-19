<?php

namespace App\Services;

/**
 * Эвристическая типизация: каталог → URL-сигналы → HTML (корзина, schema, CMS).
 * HTML для неизвестных доменов качается пачкой через curl_multi.
 */
class SiteTypesPageClassifier
{
    private const TIMEOUT = 4;

    private const CONNECT_TIMEOUT = 2;

    private const MAX_HTML = 200000;

    private const BATCH = 12;

    /** @var array<string, array{type: string, source: string, score: int}> */
    private $cache = [];

    /** @var array<string, string> domain => html */
    private $htmlCache = [];

    /**
     * Предзагрузка HTML для доменов, которых нет в каталоге.
     *
     * @param array<int, array{url: string, domain: string}> $items
     */
    public function prefetchHtml(array $items): void
    {
        $pending = [];
        foreach ($items as $item) {
            $domain = mb_strtolower(trim((string) ($item['domain'] ?? '')));
            $url = trim((string) ($item['url'] ?? ''));
            if ($domain === '' || $url === '') {
                continue;
            }
            if (isset($this->htmlCache[$domain]) || isset($this->cache[$domain])) {
                continue;
            }
            // URL-эвристика уже сильная — HTML не нужен.
            $fromUrl = $this->classifyByUrl($url, $domain);
            if ($fromUrl['score'] >= 70) {
                $this->cache[$domain] = $fromUrl;
                continue;
            }
            if (! isset($pending[$domain])) {
                $pending[$domain] = $url;
            }
        }

        if ($pending === []) {
            return;
        }

        $chunks = array_chunk($pending, self::BATCH, true);
        foreach ($chunks as $chunk) {
            $this->fetchHtmlBatch($chunk);
        }
    }

    /**
     * Быстрая типизация без HTTP: каталог + URL-эвристики.
     *
     * @return array{type: string, source: string, score: int}
     */
    public function classifyWithoutHtml(string $url, string $domain, string $catalogType): array
    {
        if ($catalogType !== 'unknown' && $catalogType !== '') {
            return ['type' => $catalogType, 'source' => 'catalog', 'score' => 100];
        }

        $domain = mb_strtolower(trim($domain));
        $cacheKey = $domain !== '' ? $domain : mb_strtolower($url);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $fromUrl = $this->classifyByUrl($url, $domain);

        return $this->cache[$cacheKey] = $fromUrl['score'] > 0
            ? $fromUrl
            : ['type' => 'unknown', 'source' => 'empty', 'score' => 0];
    }

    /**
     * @return array{type: string, source: string, score: int}
     */
    public function classify(string $url, string $domain, string $catalogType): array
    {
        if ($catalogType !== 'unknown' && $catalogType !== '') {
            return ['type' => $catalogType, 'source' => 'catalog', 'score' => 100];
        }

        $domain = mb_strtolower(trim($domain));
        $cacheKey = $domain !== '' ? $domain : mb_strtolower($url);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $fromUrl = $this->classifyByUrl($url, $domain);
        if ($fromUrl['score'] >= 70) {
            return $this->cache[$cacheKey] = $fromUrl;
        }

        $html = '';
        if ($domain !== '' && isset($this->htmlCache[$domain])) {
            $html = $this->htmlCache[$domain];
        } else {
            $html = $this->fetchHtml($url);
            if ($domain !== '' && $html !== '') {
                $this->htmlCache[$domain] = $html;
            }
        }

        if ($html === '') {
            return $this->cache[$cacheKey] = $fromUrl['score'] > 0
                ? $fromUrl
                : ['type' => 'unknown', 'source' => 'empty', 'score' => 0];
        }

        $fromHtml = $this->classifyByHtml($html, $url, $domain);
        // URL + HTML усиливают друг друга для магазинов
        if ($fromUrl['type'] === 'ecommerce' && $fromHtml['type'] === 'ecommerce') {
            $fromHtml['score'] = min(100, $fromHtml['score'] + 15);
        } elseif ($fromUrl['score'] > 0 && $fromHtml['score'] < $fromUrl['score']) {
            return $this->cache[$cacheKey] = $fromUrl;
        }

        if ($fromHtml['score'] >= 35 || ($fromHtml['type'] === 'ecommerce' && $fromHtml['score'] >= 28)) {
            return $this->cache[$cacheKey] = $fromHtml;
        }

        return $this->cache[$cacheKey] = $fromUrl['score'] > 0
            ? $fromUrl
            : ['type' => 'unknown', 'source' => 'html_weak', 'score' => (int) $fromHtml['score']];
    }

    /**
     * @return array{type: string, source: string, score: int}
     */
    private function classifyByUrl(string $url, string $domain): array
    {
        $u = mb_strtolower($url);
        $d = mb_strtolower($domain);

        $shopPath = [
            '/catalog', '/catalogue', '/shop/', '/shop?', '/product', '/products', '/cart', '/basket',
            '/checkout', '/buy', '/order', '/товар', '/каталог', '/корзин', '/магазин',
            '/category', '/item/', '/goods', '/sku',
        ];
        $pathHits = 0;
        foreach ($shopPath as $p) {
            if (mb_strpos($u, $p) !== false) {
                $pathHits++;
            }
        }
        if ($pathHits > 0) {
            return ['type' => 'ecommerce', 'source' => 'url', 'score' => min(90, 70 + $pathHits * 5)];
        }

        // /something.html на домене с med/shop/store
        if (preg_match('#/[a-z0-9\-_]+\.html(\?|$)#i', $u)
            && preg_match('/(shop|store|market|magazin|med|opt|tovar)/u', $d)) {
            return ['type' => 'ecommerce', 'source' => 'url', 'score' => 68];
        }

        if (preg_match('/(^|\.|-)(shop|store|market|magazin|mall)($|\.|-)/u', $d)) {
            return ['type' => 'ecommerce', 'source' => 'domain', 'score' => 75];
        }
        if (mb_strpos($d, '-shop') !== false || mb_strpos($d, 'shop-') !== false) {
            return ['type' => 'ecommerce', 'source' => 'domain', 'score' => 75];
        }

        return ['type' => 'unknown', 'source' => 'url', 'score' => 0];
    }

    /**
     * @return array{type: string, source: string, score: int}
     */
    private function classifyByHtml(string $html, string $url, string $domain): array
    {
        $h = mb_strtolower($html);
        $scores = [
            'ecommerce' => 0,
            'aggregators' => 0,
            'organizations' => 0,
            'content' => 0,
            'social' => 0,
            'reviews' => 0,
            'news' => 0,
            'games' => 0,
        ];

        $ecomSignals = [
            'schema.org/product' => 40,
            'schema.org/offer' => 35,
            '"@type":"product"' => 40,
            '"@type":"offer"' => 30,
            'itemtype="http://schema.org/product"' => 40,
            'itemtype="https://schema.org/product"' => 40,
            'woocommerce' => 45,
            'opencart' => 40,
            'virtuemart' => 35,
            'insales' => 40,
            'shopify' => 45,
            'bitrix:sale' => 35,
            'bx_catalog' => 30,
            'catalog.element' => 25,
            'add-to-cart' => 35,
            'addtocart' => 35,
            'data-add-to-cart' => 30,
            'в корзину' => 40,
            'добавить в корзину' => 45,
            'купить в 1 клик' => 30,
            'оформить заказ' => 35,
            'наличие на складе' => 25,
            'в наличии' => 14,
            'product-price' => 25,
            'data-price' => 20,
            'js-buy' => 18,
            'priceCurrency' => 25,
            'offers' => 8,
            'корзина' => 18,
            'купить' => 10,
            'цена' => 6,
        ];
        foreach ($ecomSignals as $needle => $pts) {
            if (mb_strpos($h, $needle) !== false) {
                $scores['ecommerce'] += $pts;
            }
        }
        if (preg_match('/\b(руб\.?|₽)\b/u', $h) && (mb_strpos($h, 'купить') !== false || mb_strpos($h, 'корзин') !== false)) {
            $scores['ecommerce'] += 20;
        }

        foreach (['подать объявление', 'разместить объявление', 'объявлений', 'сравнить цены', 'предложений продавцов', 'цены от продавцов'] as $n) {
            if (mb_strpos($h, $n) !== false) {
                $scores['aggregators'] += 28;
            }
        }

        foreach (['отзывы покупателей', 'отзывы о', 'оставить отзыв', 'рейтинг продавца'] as $n) {
            if (mb_strpos($h, $n) !== false) {
                $scores['reviews'] += 22;
            }
        }

        foreach (['article:published_time', 'og:type" content="article', 'новостн'] as $n) {
            if (mb_strpos($h, $n) !== false) {
                $scores['news'] += 15;
            }
        }

        foreach (['wp-content/themes', 'wordpress', 'читать далее', 'статья'] as $n) {
            if (mb_strpos($h, $n) !== false) {
                $scores['content'] += 12;
            }
        }

        foreach (['заказать звонок', 'оставить заявку', 'рассчитать стоимость', 'наши услуги', 'запись на приём'] as $n) {
            if (mb_strpos($h, $n) !== false) {
                $scores['organizations'] += 20;
            }
        }

        arsort($scores);
        $bestType = 'unknown';
        $bestScore = 0;
        foreach ($scores as $type => $score) {
            $bestType = $type;
            $bestScore = (int) $score;
            break;
        }

        if ($bestScore < 28) {
            return ['type' => 'unknown', 'source' => 'html', 'score' => $bestScore];
        }

        if ($bestType === 'organizations' && ($scores['ecommerce'] ?? 0) >= 28) {
            $bestType = 'ecommerce';
            $bestScore = (int) $scores['ecommerce'];
        }

        // Контент/новости не должны перебивать явный магазин
        if (in_array($bestType, ['content', 'news'], true) && ($scores['ecommerce'] ?? 0) >= 35) {
            $bestType = 'ecommerce';
            $bestScore = (int) $scores['ecommerce'];
        }

        return ['type' => $bestType, 'source' => 'html', 'score' => min(100, $bestScore)];
    }

    /**
     * @param array<string, string> $domainToUrl
     */
    private function fetchHtmlBatch(array $domainToUrl): void
    {
        if (! function_exists('curl_multi_init')) {
            foreach ($domainToUrl as $domain => $url) {
                $this->htmlCache[$domain] = $this->fetchHtml($url);
            }

            return;
        }

        $mh = curl_multi_init();
        $handles = [];
        foreach ($domainToUrl as $domain => $url) {
            if (! preg_match('#^https?://#i', $url)) {
                $this->htmlCache[$domain] = '';
                continue;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, $this->curlOpts());
            curl_multi_add_handle($mh, $ch);
            $handles[$domain] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.5);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $domain => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if (! is_string($body) || $body === '' || $code >= 400) {
                $this->htmlCache[$domain] = '';
            } else {
                $this->htmlCache[$domain] = mb_substr($body, 0, self::MAX_HTML);
            }
        }
        curl_multi_close($mh);
    }

    private function fetchHtml(string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return '';
        }

        if (! function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => self::TIMEOUT,
                    'header' => "User-Agent: Mozilla/5.0 (compatible; TitloSiteTypes/1.2)\r\n",
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);

            return is_string($body) ? mb_substr($body, 0, self::MAX_HTML) : '';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $this->curlOpts());
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($body) || $body === '' || $code >= 400) {
            return '';
        }

        return mb_substr($body, 0, self::MAX_HTML);
    }

    /**
     * @return array<int, mixed>
     */
    private function curlOpts(): array
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
            ],
        ];
    }
}
