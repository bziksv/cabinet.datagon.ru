<?php

namespace App\Http\Controllers;

use App\Services\Xml\XmlProviderBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class XmlProvidersAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(XmlProviderBalanceService $balances): View
    {
        $providersMeta = config('cabinet-xml-providers.providers', []);
        $modules = config('cabinet-xml-providers.modules', []);
        $balanceRows = $balances->all();

        $providers = [];
        foreach ($providersMeta as $id => $meta) {
            $providers[] = array_merge(['id' => $id], $meta, [
                'balance' => $balanceRows[$id] ?? ['ok' => false, 'code' => 'not_loaded'],
            ]);
        }

        return view('admin.xml-providers.index', [
            'providers' => $providers,
            'modules' => $modules,
            'cacheSeconds' => (int) config('cabinet-xml-providers.balance_cache_seconds', 90),
        ]);
    }

    public function refresh(XmlProviderBalanceService $balances): JsonResponse
    {
        return response()->json([
            'providers' => $balances->all(true),
            'fetched_at' => now()->toDateTimeString(),
        ]);
    }
}
