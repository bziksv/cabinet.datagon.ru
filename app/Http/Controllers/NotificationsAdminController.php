<?php

namespace App\Http\Controllers;

use App\Services\NotificationAdminTestService;
use App\Support\NotificationDispatchLogger;
use App\Support\UserNotificationsRegistry;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class NotificationsAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(NotificationAdminTestService $tester): View
    {
        $user = Auth::user();

        return view('admin.notifications.index', [
            'notifications' => UserNotificationsRegistry::snapshot(),
            'tableRows' => UserNotificationsRegistry::tableRowsForView($tester, $user),
            'dispatchTotals' => NotificationDispatchLogger::totals(),
            'adminEmail' => $user ? $user->email : null,
            'adminLang' => $user ? ($user->lang ?: 'ru') : 'ru',
            'telegramConnected' => $user ? $user->isTelegramConnected() : false,
        ]);
    }

    public function testTelegram(Request $request, NotificationAdminTestService $tester): JsonResponse
    {
        $eventId = (string) $request->input('event_id', '');
        if (!$tester->isKnownEvent($eventId)) {
            return response()->json(['ok' => false, 'message' => __('Users notify test unknown event')], 422);
        }

        $user = Auth::user();
        $result = $tester->sendTelegram($eventId, $user, $this->resolveTestLocale($request, $user));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function testEmail(Request $request, NotificationAdminTestService $tester): JsonResponse
    {
        $eventId = (string) $request->input('event_id', '');
        if (!$tester->isKnownEvent($eventId)) {
            return response()->json(['ok' => false, 'message' => __('Users notify test unknown event')], 422);
        }

        $user = Auth::user();
        $result = $tester->sendEmail($eventId, $user, $this->resolveTestLocale($request, $user));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function previewModal(string $eventId, Request $request, NotificationAdminTestService $tester): JsonResponse
    {
        if (!$tester->isKnownEvent($eventId) || !$tester->supportsModalPreview($eventId)) {
            return response()->json(['ok' => false, 'message' => __('Users notify test unsupported')], 404);
        }

        $user = Auth::user();
        $preview = $tester->renderModalPreview($eventId, $user, $this->resolveTestLocale($request, $user));

        return response()->json([
            'ok' => true,
            'title' => $preview['title'],
            'html' => $preview['html'],
            'lang' => app()->getLocale(),
        ]);
    }

    public function previewEmail(string $eventId, Request $request, NotificationAdminTestService $tester): View
    {
        if (!$tester->isKnownEvent($eventId) || !$tester->supportsEmail($eventId)) {
            abort(404);
        }

        $user = Auth::user();
        $locale = $this->resolveTestLocale($request, $user);
        $html = $tester->renderEmailPreview($eventId, $user, $locale);

        return view('admin.notifications.preview-email', [
            'eventId' => $eventId,
            'html' => $html,
            'locale' => $locale,
        ]);
    }

    private function resolveTestLocale(Request $request, ?User $user): string
    {
        $lang = strtolower((string) $request->input('lang', ''));

        if (in_array($lang, ['ru', 'en'], true)) {
            return $lang;
        }

        return $user && $user->lang ? (string) $user->lang : 'ru';
    }
}
