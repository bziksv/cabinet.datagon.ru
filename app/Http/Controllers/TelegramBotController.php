<?php

namespace App\Http\Controllers;

use App\Services\TelegramUpdateHandler;
use Illuminate\Http\Request;

class TelegramBotController extends Controller
{
    public function index(Request $request)
    {
        $message = $request->json('message') ?? $request->input('message');

        if (is_array($message) && !empty($message['chat']['id']) && !empty($message['text'])) {
            app(TelegramUpdateHandler::class)->handleMessage($message);
        }

        return response('ok', 200);
    }
}
