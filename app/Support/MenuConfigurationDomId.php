<?php

namespace App\Support;

final class MenuConfigurationDomId
{
    public static function fromGroupName(string $name): string
    {
        $id = preg_replace('/[^a-zA-Zа-яА-Я]/u', '', $name);

        return $id !== '' ? $id : 'group';
    }
}
