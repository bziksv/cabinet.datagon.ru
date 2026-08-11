<?php

namespace App\Services\SiteAudit;

/**
 * Телефон / адрес + эвристика «коммерческая страница» + lite коммерческие сигналы.
 */
class SiteAuditContacts
{
    /**
     * @return array{
     *   has_phone:bool,
     *   has_address:bool,
     *   phone_sample:?string,
     *   address_sample:?string,
     *   phone_source:?string,
     *   address_source:?string
     * }
     */
    public static function detect(string $text, string $html = ''): array
    {
        $phone = self::findPhone($text, $html);
        $address = self::findAddress($text, $html);

        return [
            'has_phone' => $phone['value'] !== null,
            'has_address' => $address['value'] !== null,
            'phone_sample' => $phone['value'],
            'address_sample' => $address['value'],
            'phone_source' => $phone['source'],
            'address_source' => $address['source'],
        ];
    }

    /**
     * @return array{value:?string,source:?string}
     */
    private static function findPhone(string $text, string $html): array
    {
        if ($html !== '') {
            if (preg_match('/\bhref\s*=\s*["\']\s*tel:\s*(\+?[0-9][0-9\-\.\s()]{6,})\s*["\']/iu', $html, $m)) {
                return ['value' => self::clipContact($m[1]), 'source' => 'tel_href'];
            }
            if (preg_match('/itemprop\s*=\s*["\']telephone["\'][^>]*>\s*([^<]{5,40})/iu', $html, $m)) {
                return ['value' => self::clipContact(strip_tags($m[1])), 'source' => 'microdata'];
            }
            $fromLd = self::jsonLdPhones($html);
            if ($fromLd !== null) {
                return ['value' => $fromLd, 'source' => 'json_ld'];
            }
        }

        if (preg_match('/(?:\+7|8)[\s\-.]?\(?\d{3}\)?[\s\-.]?\d{3}[\s\-.]?\d{2}[\s\-.]?\d{2}/u', $text, $m)) {
            return ['value' => self::clipContact($m[0]), 'source' => 'text'];
        }
        // Без скобок / с 8-800
        if (preg_match('/(?:\+7|8)\s*[\-]?\s*\(?\s*8\s*0\s*0\s*\)?[\s\-.]?\d{3}[\s\-.]?\d{2}[\s\-.]?\d{2}/u', $text, $m)) {
            return ['value' => self::clipContact($m[0]), 'source' => 'text'];
        }
        if (preg_match('/\btel:\s*(\+?\d[\d\-\.\s()]{6,})/i', $text, $m)) {
            return ['value' => self::clipContact($m[1]), 'source' => 'text'];
        }

        return ['value' => null, 'source' => null];
    }

    /**
     * @return array{value:?string,source:?string}
     */
    private static function findAddress(string $text, string $html): array
    {
        if ($html !== '') {
            $fromLd = self::jsonLdAddress($html);
            if ($fromLd !== null) {
                return ['value' => $fromLd, 'source' => 'json_ld'];
            }
            if (preg_match('/itemprop\s*=\s*["\']streetAddress["\'][^>]*>\s*([^<]{5,120})/iu', $html, $m)) {
                return ['value' => self::clipContact(strip_tags($m[1])), 'source' => 'microdata'];
            }
        }

        // «проспект» / «пр-т» / «ул.» + номер дома
        if (preg_match(
            '/(?:ул\.|улица|пр-т|пр\.|проспект|пер\.|переулок|шоссе|бульвар|наб\.|площадь|пл\.)\s*[\p{L}\d\-.\s]{2,60}?\d{1,4}[а-яА-Яa-zA-Z]?/iu',
            $text,
            $m
        )) {
            return ['value' => self::clipContact($m[0]), 'source' => 'text'];
        }
        if (preg_match('/\bд\.\s*\d{1,4}[а-яА-Яa-zA-Z]?\b/iu', $text, $m)) {
            return ['value' => self::clipContact($m[0]), 'source' => 'text'];
        }
        if (preg_match('/\b\d{6}\b.{0,60}\b(г\.|город)\b/iu', $text, $m)
            || preg_match('/\b(г\.|город)\b.{0,60}\b\d{6}\b/iu', $text, $m)) {
            return ['value' => self::clipContact($m[0]), 'source' => 'text'];
        }
        // Город + улицеподобный кусок без явного «ул.»
        if (preg_match('/\b(?:г\.|город)\s*[\p{L}\-]{2,30}.{0,40}\d{1,4}\b/iu', $text, $m)) {
            return ['value' => self::clipContact($m[0]), 'source' => 'text'];
        }

        return ['value' => null, 'source' => null];
    }

    private static function jsonLdPhones(string $html): ?string
    {
        foreach (self::jsonLdBlocks($html) as $blob) {
            if (preg_match('/"telephone"\s*:\s*"([^"]{5,40})"/iu', $blob, $m)) {
                return self::clipContact($m[1]);
            }
            if (preg_match('/"telephone"\s*:\s*\[\s*"([^"]{5,40})"/iu', $blob, $m)) {
                return self::clipContact($m[1]);
            }
        }

        return null;
    }

    private static function jsonLdAddress(string $html): ?string
    {
        foreach (self::jsonLdBlocks($html) as $blob) {
            $street = null;
            $locality = null;
            $postal = null;
            if (preg_match('/"streetAddress"\s*:\s*"([^"]{3,120})"/iu', $blob, $m)) {
                $street = trim($m[1]);
            }
            if (preg_match('/"addressLocality"\s*:\s*"([^"]{2,60})"/iu', $blob, $m)) {
                $locality = trim($m[1]);
            }
            if (preg_match('/"postalCode"\s*:\s*"([^"]{4,12})"/iu', $blob, $m)) {
                $postal = trim($m[1]);
            }
            if ($street !== null || $locality !== null) {
                $parts = array_filter([$postal, $locality, $street]);

                return self::clipContact(implode(', ', $parts));
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function jsonLdBlocks(string $html): array
    {
        if (! preg_match_all(
            '/<script\b[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $mm
        )) {
            return [];
        }
        $out = [];
        foreach ($mm[1] as $raw) {
            $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($raw !== '') {
                $out[] = $raw;
            }
        }

        return $out;
    }

    private static function clipContact(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?: $value);
        if (mb_strlen($value) > 120) {
            return rtrim(mb_substr($value, 0, 117)) . '…';
        }

        return $value;
    }

    /**
     * Lite-сигналы коммерческой страницы (без сравнения с ТОП конкурентов).
     *
     * @return array{
     *   has_price:bool,
     *   has_cta:bool,
     *   has_delivery:bool,
     *   has_payment:bool,
     *   has_stock:bool,
     *   has_reviews:bool
     * }
     */
    public static function detectSignals(string $text): array
    {
        $t = mb_strtolower($text);

        $hasPrice = (bool) preg_match('/[₽€$]\s*\d|\d[\d\s]{0,12}\s*(₽|руб\.?|рублей|rub\b)/iu', $text)
            || (bool) preg_match('/\b(цена|стоимость|от\s+\d[\d\s]{2,})\b/iu', $t);

        $hasCta = false;
        foreach (['купить', 'заказать', 'в корзину', 'оформить заказ', 'оставить заявку', 'добавить в корзину', 'buy now', 'add to cart'] as $w) {
            if (mb_strpos($t, $w) !== false) {
                $hasCta = true;
                break;
            }
        }

        $hasDelivery = false;
        foreach (['доставк', 'самовывоз', 'shipping', 'pickup', 'возврат товар'] as $w) {
            if (mb_strpos($t, $w) !== false) {
                $hasDelivery = true;
                break;
            }
        }

        $hasPayment = false;
        foreach (['оплат', 'банковск', 'картой', 'безнал', 'рассрочк', 'кредит', 'visa', 'mastercard', 'мир '] as $w) {
            if (mb_strpos($t, $w) !== false) {
                $hasPayment = true;
                break;
            }
        }

        $hasStock = false;
        foreach (['в наличии', 'нет в наличии', 'под заказ', 'остаток', 'на склад', 'in stock', 'out of stock'] as $w) {
            if (mb_strpos($t, $w) !== false) {
                $hasStock = true;
                break;
            }
        }

        $hasReviews = false;
        foreach (['отзыв', 'рейтинг', 'оценок', 'review', 'rating', 'звезд'] as $w) {
            if (mb_strpos($t, $w) !== false) {
                $hasReviews = true;
                break;
            }
        }

        return [
            'has_price' => $hasPrice,
            'has_cta' => $hasCta,
            'has_delivery' => $hasDelivery,
            'has_payment' => $hasPayment,
            'has_stock' => $hasStock,
            'has_reviews' => $hasReviews,
        ];
    }

    /**
     * @param array{title?:?string,h1?:?string} $parsed
     */
    public static function looksCommercial(string $url, array $parsed, string $text): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        // Блог / новости сами по себе не «витрина» — телефон в шапке не требуем как для карточки товара.
        if (preg_match('#/(blog|blogs|news|article|articles|post|posts)(/|$)#iu', $path)) {
            return false;
        }
        if (preg_match('#/(catalog|katalog|product|tovar|shop|magazin|cart|korzina|order|zakaz|price|ceny|uslugi|usluga|uslug)#iu', $path)) {
            return true;
        }

        $blob = mb_strtolower(
            trim((string) ($parsed['title'] ?? '') . ' ' . (string) ($parsed['h1'] ?? '') . ' ' . mb_substr($text, 0, 2000))
        );
        $markers = [
            'купить', 'цена', 'цены', 'заказать', 'доставка', 'корзин', '₽', 'руб.',
            'в наличии', 'скидк', 'рассрочк', 'оформить заказ',
        ];
        foreach ($markers as $w) {
            if (mb_strpos($blob, $w) !== false) {
                return true;
            }
        }

        return false;
    }
}
