<?php

namespace App\Services\SiteAudit;

/**
 * Телефон / адрес + эвристика «коммерческая страница» + lite коммерческие сигналы.
 */
class SiteAuditContacts
{
    /**
     * @return array{has_phone:bool,has_address:bool}
     */
    public static function detect(string $text): array
    {
        $hasPhone = (bool) preg_match(
            '/(?:\+7|8)[\s\-.]?\(?\d{3}\)?[\s\-.]?\d{3}[\s\-.]?\d{2}[\s\-.]?\d{2}/u',
            $text
        );
        if (! $hasPhone) {
            $hasPhone = (bool) preg_match('/\btel:\+?\d/i', $text);
        }

        $hasAddress = (bool) preg_match(
            '/\b(ул\.|улица|пр-т|проспект|пер\.|переулок|шоссе|бульвар|наб\.|д\.\s*\d+)/iu',
            $text
        );
        if (! $hasAddress) {
            $hasAddress = (bool) preg_match('/\b\d{6}\b.{0,40}\b(г\.|город)\b/iu', $text)
                || (bool) preg_match('/\b(г\.|город)\b.{0,40}\b\d{6}\b/iu', $text);
        }

        return [
            'has_phone' => $hasPhone,
            'has_address' => $hasAddress,
        ];
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
