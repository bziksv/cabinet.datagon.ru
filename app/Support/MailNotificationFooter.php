<?php

namespace App\Support;

class MailNotificationFooter
{
    public static function manageUrl(?string $preferenceKey = null): string
    {
        $url = route('profile.index');

        if ($preferenceKey !== null && $preferenceKey !== '') {
            $url .= '?pref=' . rawurlencode($preferenceKey);
        }

        return $url . '#notifications';
    }

    public static function unsubscribeMarkdown(?string $preferenceKey = null): string
    {
        return __('Mail notifications unsubscribe', ['url' => self::manageUrl($preferenceKey)]);
    }
}
