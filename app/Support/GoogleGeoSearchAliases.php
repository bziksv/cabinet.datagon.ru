<?php

namespace App\Support;

/**
 * Русские/альтернативные названия → английские для поиска в Google geotargets.
 * Ключи — нижний регистр.
 *
 * @return array<string, list<string>>
 */
final class GoogleGeoSearchAliases
{
    public static function map(): array
    {
        return [
            'вашингтон' => ['washington', 'district of columbia'],
            'нью-йорк' => ['new york'],
            'нью йорк' => ['new york'],
            'лондон' => ['london'],
            'берлин' => ['berlin'],
            'париж' => ['paris'],
            'рим' => ['rome'],
            'мадрид' => ['madrid'],
            'барселона' => ['barcelona'],
            'амстердам' => ['amsterdam'],
            'вена' => ['vienna'],
            'прага' => ['prague'],
            'варшава' => ['warsaw'],
            'будапешт' => ['budapest'],
            'стокгольм' => ['stockholm'],
            'хельсинки' => ['helsinki'],
            'осло' => ['oslo'],
            'копенгаген' => ['copenhagen'],
            'дублин' => ['dublin'],
            'брюссель' => ['brussels'],
            'цюрих' => ['zurich'],
            'женева' => ['geneva'],
            'токио' => ['tokyo'],
            'сеул' => ['seoul'],
            'пекин' => ['beijing'],
            'шанхай' => ['shanghai'],
            'гонконг' => ['hong kong'],
            'сингапур' => ['singapore'],
            'дубай' => ['dubai'],
            'стамбул' => ['istanbul'],
            'тель-авив' => ['tel aviv'],
            'тель авив' => ['tel aviv'],
            'иерусалим' => ['jerusalem'],
            'сидней' => ['sydney'],
            'мельбурн' => ['melbourne'],
            'торонто' => ['toronto'],
            'монреаль' => ['montreal'],
            'ванкувер' => ['vancouver'],
            'лос-анджелес' => ['los angeles'],
            'лос анджелес' => ['los angeles'],
            'сан-франциско' => ['san francisco'],
            'сан франциско' => ['san francisco'],
            'чикаго' => ['chicago'],
            'майами' => ['miami'],
            'бостон' => ['boston'],
            'хьюстон' => ['houston'],
            'атланта' => ['atlanta'],
            'сиэтл' => ['seattle'],
            'минск' => ['minsk'],
            'киев' => ['kyiv', 'kiev'],
            'київ' => ['kyiv', 'kiev'],
            'одесса' => ['odesa', 'odessa'],
            'одеса' => ['odesa', 'odessa'],
            'харьков' => ['kharkiv', 'kharkov'],
            'львов' => ['lviv', 'lvov'],
            'алматы' => ['almaty'],
            'астана' => ['astana', 'nur-sultan'],
            'нур-султан' => ['astana', 'nur-sultan'],
            'ташкент' => ['tashkent'],
            'баку' => ['baku'],
            'ереван' => ['yerevan'],
            'тбилиси' => ['tbilisi'],
            'кишинёв' => ['chisinau', 'kishinev'],
            'кишинев' => ['chisinau', 'kishinev'],
            'рига' => ['riga'],
            'вильнюс' => ['vilnius'],
            'таллин' => ['tallinn'],
            'таллинн' => ['tallinn'],
            'сша' => ['united states', 'usa'],
            'америка' => ['united states'],
            'великобритания' => ['united kingdom'],
            'англия' => ['united kingdom', 'england'],
            'германия' => ['germany'],
            'франция' => ['france'],
            'италия' => ['italy'],
            'испания' => ['spain'],
            'польша' => ['poland'],
            'китай' => ['china'],
            'япония' => ['japan'],
            'турция' => ['turkey'],
            'оаэ' => ['united arab emirates'],
            'израиль' => ['israel'],
            'канада' => ['canada'],
            'австралия' => ['australia'],
            'беларусь' => ['belarus'],
            'белоруссия' => ['belarus'],
            'украина' => ['ukraine'],
            'казахстан' => ['kazakhstan'],
            'узбекистан' => ['uzbekistan'],
        ];
    }

    /**
     * Предпочтительные Criteria ID при точном алиасе (столицы / главные города).
     *
     * @return array<string, string>
     */
    public static function preferredIds(): array
    {
        return [
            'washington' => '1014895', // Washington, District of Columbia
            'district of columbia' => '1014895',
            'new york' => '1023191',
            'london' => '1006886',
            'berlin' => '1003854',
            'paris' => '1006094',
            'minsk' => '1001493',
            'kyiv' => '1012852',
            'kiev' => '1012852',
            'almaty' => '9063099',
            'warsaw' => '1011419',
            'united states' => '2840',
            'germany' => '2276',
            'united kingdom' => '2826',
            'belarus' => '2112',
            'ukraine' => '2804',
            'kazakhstan' => '2077',
        ];
    }

    /**
     * @return list<string>
     */
    public static function variantsFor(string $query): array
    {
        $q = mb_strtolower(trim($query));
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
        if ($q === '') {
            return [];
        }

        $out = [$q];
        $map = self::map();
        if (isset($map[$q])) {
            foreach ($map[$q] as $alias) {
                $out[] = mb_strtolower($alias);
            }
        }

        // Частичное совпадение ключа алиаса («ваш» → нет; «вашингт» → вашингтон)
        foreach ($map as $ru => $aliases) {
            if (mb_strpos($ru, $q) === 0 || mb_strpos($q, $ru) === 0) {
                $out[] = $ru;
                foreach ($aliases as $alias) {
                    $out[] = mb_strtolower($alias);
                }
            }
        }

        $lat = self::transliterateRuToLat($q);
        if ($lat !== '' && $lat !== $q) {
            $out[] = $lat;
            // vashington → washington (частая ошибка транслита ш/щ)
            $out[] = str_replace(['vash', 'sch', 'ya', 'yu', 'zh'], ['wash', 'sh', 'ia', 'iu', 'j'], $lat);
        }

        return array_values(array_unique(array_filter($out)));
    }

    public static function transliterateRuToLat(string $text): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $chars = preg_split('//u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        foreach ($chars as $ch) {
            $out .= $map[$ch] ?? $ch;
        }

        return $out;
    }
}
