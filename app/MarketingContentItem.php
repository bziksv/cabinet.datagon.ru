<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Единица контент-плана titlo.ru: термин SEO-словаря или статья блога, с ТЗ.
 */
class MarketingContentItem extends Model
{
    public const TYPE_TERM = 'term';
    public const TYPE_ARTICLE = 'article';

    protected $table = 'marketing_content_items';

    protected $guarded = [];

    protected $casts = [
        'is_top' => 'boolean',
    ];

    protected $dates = ['lsi_analyzed_at'];

    /** @return array<int, string> */
    public function moduleList(): array
    {
        $raw = trim((string) $this->modules);
        if ($raw === '' || $raw === '—') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/u', $raw))));
    }

    /** Ссылка на лендинг модуля, если это slug (латиница/цифры/дефис/подчёркивание). */
    public static function moduleUrl(string $slug): ?string
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
            return null;
        }

        return rtrim((string) config('cabinet-marketing.site_url'), '/') . '/' . $slug . '/';
    }

    /** ТЗ заполнено минимально: основной запрос + структура. */
    public function hasBrief(): bool
    {
        return trim((string) $this->main_query) !== '' && trim((string) $this->outline) !== '';
    }

    public function hasLsi(): bool
    {
        return trim((string) $this->lsi_words) !== '';
    }

    /** @return array<int, string> */
    public static function splitLines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/u', $text))));
    }
}
