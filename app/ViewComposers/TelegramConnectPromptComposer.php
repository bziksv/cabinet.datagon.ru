<?php

namespace App\ViewComposers;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TelegramConnectPromptComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            $view->with([
                'showTelegramConnectPrompt' => false,
                'telegramBotSubscribeUrl' => null,
            ]);

            return;
        }

        $view->with([
            'showTelegramConnectPrompt' => $user->shouldShowTelegramConnectPrompt(),
            'telegramBotSubscribeUrl' => $user->telegramBotSubscribeUrl(),
        ]);
    }
}
