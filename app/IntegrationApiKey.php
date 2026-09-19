<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationApiKey extends Model
{
    protected $table = 'integration_api_keys';

    protected $fillable = [
        'user_id',
        'name',
        'prefix',
        'key_hash',
        'scopes',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes;
        if (!is_array($scopes) || $scopes === []) {
            return true;
        }

        return in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }
}
