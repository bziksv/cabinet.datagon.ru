<?php

namespace App\Http\Controllers;

use App\IntegrationApiKey;
use App\Services\Integration\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntegrationApiKeysController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index()
    {
        $keys = IntegrationApiKey::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->get();

        return view('integration.api-keys', [
            'keys' => $keys,
            'plainKey' => session('integration_api_key_plain'),
            'tokenStats' => \App\Support\AiDeepSeekTokenStats::forUser((int) Auth::id()),
        ]);
    }

    public function store(Request $request, ApiKeyService $apiKeys)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $created = $apiKeys->create(Auth::user(), $data['name'], ['relevance', 'ai']);

        return redirect()
            ->route('integration.api-keys.index')
            ->with('integration_api_key_plain', $created['plain'])
            ->with('success', __('API key created. Copy it now — it will not be shown again.'));
    }

    public function destroy(int $id, ApiKeyService $apiKeys)
    {
        $key = IntegrationApiKey::where('user_id', Auth::id())->where('id', $id)->firstOrFail();
        $apiKeys->revoke($key);

        return redirect()
            ->route('integration.api-keys.index')
            ->with('success', __('API key revoked.'));
    }
}
