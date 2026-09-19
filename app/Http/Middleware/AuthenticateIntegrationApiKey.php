<?php

namespace App\Http\Middleware;

use App\Services\Integration\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateIntegrationApiKey
{
    /** @var ApiKeyService */
    protected $apiKeys;

    public function __construct(ApiKeyService $apiKeys)
    {
        $this->apiKeys = $apiKeys;
    }

    public function handle(Request $request, Closure $next, string $scope = '*')
    {
        $plain = $this->extractBearer($request);
        if ($plain === null) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'API key required',
            ], 401);
        }

        $key = $this->apiKeys->findValidByPlain($plain);
        if (!$key || !$key->user) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid API key',
            ], 401);
        }

        if ($scope !== '*' && !$key->hasScope($scope) && !$key->hasScope('*')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'API key scope denied',
            ], 403);
        }

        Auth::setUser($key->user);
        $request->attributes->set('integration_api_key', $key);
        $this->apiKeys->touch($key);

        return $next($request);
    }

    protected function extractBearer(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
            return $m[1];
        }

        // Query ?api_key= отключён: ключ в URL попадает в логи / Referer
        return null;
    }
}
