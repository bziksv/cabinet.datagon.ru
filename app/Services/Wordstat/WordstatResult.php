<?php

namespace App\Services\Wordstat;

class WordstatResult
{
    public int $totalCount = 0;
    /** @var array<int, array{phrase: string, count: int}> */
    public array $left = [];
    /** @var array<int, array{phrase: string, count: int}> */
    public array $right = [];
}
