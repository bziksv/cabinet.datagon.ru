<?php

namespace App\Http\Controllers;

use App\Services\NotificationAdminTestService;
use App\Support\UserNotificationsRegistry;
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
            'adminEmail' => $user ? $user->email : null,
            'telegramConnected' => $user ? $user->isTelegramConnected() : false,
        ]);
    }

    public function testTelegram(Request $request, NotificationAdminTestService $tester): JsonResponse
    {
        $eventId = (string) $request->input('event_id', '');
        if (!$tester->isKnownEvent($eventId)) {
            return response()->json(['ok' => false, 'message' => __('Users notify test unknown event')], 422);
        }

        $result = $tester->sendTelegram($eventId, Auth::user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function testEmail(Request $request, NotificationAdminTestService $tester): JsonResponse
    {
        $eventId = (string) $request->input('event_id', '');
        if (!$tester->isKnownEvent($eventId)) {
            return response()->json(['ok' => false, 'message' => __('Users notify test unknown event')], 422);
        }

        $result = $tester->sendEmail($eventId, Auth::user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function previewModal(string $eventId, NotificationAdminTestService $tester): JsonResponse
    {
        if (!$tester->isKnownEvent($eventId) || !$tester->supportsModalPreview($eventId)) {
            return response()->json(['ok' => false, 'message' => __('Users notify test unsupported')], 404);
        }

        $preview = $tester->renderModalPreview($eventId, Auth::user());

        return response()->json([
            'ok' => true,
            'title' => $preview['title'],
            'html' => $preview['html'],
        ]);
    }

    public function previewEmail(string $eventId, NotificationAdminTestService $tester): View
    {
        if (!$tester->isKnownEvent($eventId) || !$tester->supportsEmail($eventId)) {
            abort(404);
        }

        $html = $tester->renderEmailPreview($eventId, Auth::user());

        return view('admin.notifications.preview-email', [
            'eventId' => $eventId,
            'html' => $html,
        ]);
    }
}
