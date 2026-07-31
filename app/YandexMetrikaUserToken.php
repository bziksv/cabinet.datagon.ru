<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class YandexMetrikaUserToken extends Model
{
    protected $table = 'yandex_metrika_user_tokens';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'yandex_login',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('yandex_metrika_user_tokens');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value !== null && $value !== ''
            ? Crypt::encryptString((string) $value)
            : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value !== null && $value !== ''
            ? Crypt::encryptString((string) $value)
            : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
