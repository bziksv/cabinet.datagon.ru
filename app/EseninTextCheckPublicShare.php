<?php

namespace App;

use App\Support\EseninTextCheckPublicShareTtl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EseninTextCheckPublicShare extends Model
{
    protected $table = 'esenin_text_check_public_shares';

    protected $guarded = [];

    protected $dates = [
        'expires_at',
        'revoked_at',
    ];

    /** @var bool|null */
    protected static $tableAvailable;

    public static function tableAvailable(): bool
    {
        if (self::$tableAvailable === null) {
            try {
                self::$tableAvailable = Schema::hasTable('esenin_text_check_public_shares');
            } catch (\Throwable $e) {
                self::$tableAvailable = false;
            }
        }

        return self::$tableAvailable;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EseninTextCheckSession::class, 'esenin_text_check_session_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isUnlimited(): bool
    {
        return $this->expires_at === null;
    }

    public function expiresLabel(): string
    {
        if ($this->isUnlimited()) {
            return (string) __('Site monitoring share ttl unlimited');
        }

        return __('Valid until') . ' ' . $this->expires_at->format('d.m.Y H:i');
    }

    public function ttlDaysFromPayload(): int
    {
        $meta = $this->decodedPayload()['meta'] ?? [];
        if (isset($meta['ttl_days'])) {
            return EseninTextCheckPublicShareTtl::normalize($meta['ttl_days']);
        }

        return $this->isUnlimited() ? EseninTextCheckPublicShareTtl::UNLIMITED : 30;
    }

    public function publicUrl(): string
    {
        return url('/public/share/esenin-text-check/' . $this->token);
    }

    /**
     * @return array{result: array, text: string, name: string, meta: array}
     */
    public function decodedPayload(): array
    {
        $data = json_decode((string) $this->payload, true);

        if (!is_array($data)) {
            return ['result' => [], 'text' => '', 'name' => '', 'meta' => []];
        }

        return [
            'result' => is_array($data['result'] ?? null) ? $data['result'] : [],
            'text' => (string) ($data['text'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
        ];
    }

    public static function snapshotHash(array $snapshot): string
    {
        return hash('sha256', json_encode([
            'result' => $snapshot['result'] ?? [],
            'text' => $snapshot['text'] ?? '',
        ], JSON_UNESCAPED_UNICODE));
    }

    public static function activeForSession(?int $sessionId, int $userId): ?self
    {
        if (!self::tableAvailable() || $sessionId === null || $sessionId <= 0) {
            return null;
        }

        return static::query()
            ->where('esenin_text_check_session_id', $sessionId)
            ->where('user_id', $userId)
            ->active()
            ->orderByDesc('id')
            ->first();
    }

    public static function issueForSession(
        int $userId,
        ?int $sessionId,
        array $snapshot,
        array $meta,
        int $ttlDays = 30
    ): ?self {
        if (!self::tableAvailable()) {
            return null;
        }

        $ttlDays = EseninTextCheckPublicShareTtl::normalize($ttlDays);
        $hash = self::snapshotHash($snapshot);

        $revokeQuery = static::query()
            ->where('user_id', $userId)
            ->active()
            ->where('snapshot_hash', $hash);

        if ($sessionId !== null && $sessionId > 0) {
            $revokeQuery->where('esenin_text_check_session_id', $sessionId);
        }

        $revokeQuery->update(['revoked_at' => Carbon::now()]);

        return static::create([
            'user_id' => $userId,
            'esenin_text_check_session_id' => $sessionId > 0 ? $sessionId : null,
            'token' => Str::random(48),
            'payload' => json_encode([
                'result' => $snapshot['result'] ?? [],
                'text' => $snapshot['text'] ?? '',
                'name' => $snapshot['name'] ?? '',
                'meta' => array_merge($meta, ['ttl_days' => $ttlDays]),
            ], JSON_UNESCAPED_UNICODE),
            'snapshot_hash' => $hash,
            'expires_at' => EseninTextCheckPublicShareTtl::resolveExpiresAt($ttlDays),
        ]);
    }

    public static function revokeForSession(int $userId, ?int $sessionId): int
    {
        if (!self::tableAvailable()) {
            return 0;
        }

        $query = static::query()
            ->where('user_id', $userId)
            ->active();

        if ($sessionId !== null && $sessionId > 0) {
            $query->where('esenin_text_check_session_id', $sessionId);
        }

        return $query->update(['revoked_at' => Carbon::now()]);
    }

    public static function registerUrl(): string
    {
        $query = http_build_query([
            'module' => 'esenin-text-check',
            'from' => 'esenin-text-check-public-share',
        ]);

        return route('register') . '?' . $query;
    }
}
