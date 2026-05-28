<?php

namespace App\Http\Controllers;

use App\MenuItemsPosition;
use App\Services\MenuProjectRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PositionMenuItemsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('refresh_sidebar')) {
            cabinet_clear_menu_session_cache();
        }

        MenuProjectRegistry::ensureAllLoaded();
        $items = MenuItemsPosition::sortMenu();

        return view('positions.index', [
            'items' => $items,
            'sidebarMenuStale' => $request->boolean('refresh_sidebar'),
        ]);
    }

    public function edit(Request $request): JsonResponse
    {
        $config = MenuItemsPosition::firstOrNew(['user_id' => Auth::id()]);
        $config->positions = $request->input('menuItems');
        $config->save();

        cabinet_clear_menu_session_cache();

        return response()->json([], 201);
    }

    public function remove(Request $request): JsonResponse
    {
        MenuItemsPosition::where('user_id', Auth::id())->delete();

        cabinet_clear_menu_session_cache();

        return response()->json(['ok' => true], 200);
    }
}
