<?php

namespace App\Http\Controllers;

use App\Services\TelegramConnectBonusService;
use App\Services\TelegramBotService;
use App\TelegramBot;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    public function index(Request $request)
    {
        $message = $request->input('message');

        if ($message) {
            $telegram = new TelegramBotService($message['chat']['id']);
            $text = explode(" ", $message['text'], 2);

            if (isset($text[1])) {
                $email = base64_decode($text[1]);

                $validator = Validator::make(['email' => $email], [
                    'email' => ['required', 'email'],
                ]);

                if ($validator->passes()) {
                    $result = app(TelegramConnectBonusService::class)
                        ->linkUserFromTelegramStart((int) $message['chat']['id'], $email);

                    if ($result['linked']) {
                        $telegram = new TelegramBotService($message['chat']['id']);
                        if ($result['bonus_granted']) {
                            $amount = app(TelegramConnectBonusService::class)->bonusAmount();
                            $telegram->sendMsg(__('Telegram connect bonus success message', [
                                'amount' => number_format($amount, 0, '.', ' '),
                            ]));
                        } else {
                            $telegram->sendMsg(__('You have successfully subscribed to the notification newsletter'));
                        }
                    }
                } else {
                    $telegram->sendMsg("Команда не распознана");
                }
            }
        }
    }
}
