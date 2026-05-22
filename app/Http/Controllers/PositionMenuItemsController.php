<?php

namespace App\Http\Controllers;

use App\MenuItemsPosition;
use App\Services\MenuProjectRegistry;
use App\Services\MenuUnpublishedModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PositionMenuItemsController extends Controller
{
    public function index()
    {
        MenuProjectRegistry::ensureAllLoaded();
        $items = MenuItemsPosition::sortMenu();
        $menuExtras = MenuUnpublishedModules::summaryForUser();

        return view('positions.index', [
            'items' => $items,
            'unpublishedModules' => $menuExtras['catalogHidden'],
            'moduleExtraPages' => $menuExtras['moduleExtraPages'],
            'outsideCatalog' => $menuExtras['outsideCatalog'],
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

        return response()->json([], 200);
    }
}
