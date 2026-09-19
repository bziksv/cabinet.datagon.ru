<?php

namespace App\Services\Integration;

use App\IntegrationApiKey;
use App\User;
use Illuminate\Support\Str;

class ApiKeyService
{
    public const PREFIX = 'titlo_';

    /**
     * @return array{model: IntegrationApiKey, plain: string}
     */
    public function create(User $user, string $name, array $scopes = ['relevance', 'ai']): array
    {
        if ($scopes === []) {
            $scopes = ['relevance', 'ai'];
        }
        $plain = self::PREFIX . Str::random(40);
        $prefix = substr($plain, 0, 12);

        $model = IntegrationApiKey::create([
            'user_id' => $user->id,
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $plain),
            'scopes' => $scopes,
        ]);

        return [
            'model' => $model,
            'plain' => $plain,
        ];
    }

    public function findValidByPlain(string $plain): ?IntegrationApiKey
    {
        $plain = trim($plain);
        if ($plain === '' || strpos($plain, self::PREFIX) !== 0) {
            return null;
        }

        $key = IntegrationApiKey::where('key_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();

        return $key ?: null;
    }

    public function touch(IntegrationApiKey $key): void
    {
        $key->last_used_at = now();
        $key->save();
    }

    public function revoke(IntegrationApiKey $key): void
    {
        $key->revoked_at = now();
        $key->save();
    }
}
