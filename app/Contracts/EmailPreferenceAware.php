<?php

namespace App\Contracts;

interface EmailPreferenceAware
{
    /**
     * Ключ из реестра cabinet-users-notifications или trigger.{slug}.
     * null — сервисное письмо, отключение недоступно.
     */
    public function emailPreferenceKey(): ?string;
}
