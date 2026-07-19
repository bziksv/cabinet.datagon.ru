<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainRecordsHistory extends Model
{
    protected $table = 'domain_records_histories';

    protected $fillable = [
        'user_id',
        'domain',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Краткие поля для таблицы истории (из snapshot).
     *
     * @return array{ip: string, dns: string, neighbors: ?int}
     */
    public function tableSummary(): array
    {
        return self::summaryFromSnapshot(is_array($this->snapshot) ? $this->snapshot : []);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array{ip: string, dns: string, neighbors: ?int}
     */
    public static function summaryFromSnapshot(array $snapshot): array
    {
        $ips = [];
        foreach ($snapshot['ips'] ?? [] as $row) {
            if (is_array($row) && ! empty($row['ip'])) {
                $ips[] = (string) $row['ip'];
            } elseif (is_string($row) && $row !== '') {
                $ips[] = $row;
            }
        }
        $ips = array_values(array_unique($ips));
        $ipLabel = $ips === [] ? '—' : implode(', ', array_slice($ips, 0, 2));
        if (count($ips) > 2) {
            $ipLabel .= ' +' . (count($ips) - 2);
        }

        $dnsParts = [];
        $dns = is_array($snapshot['dns'] ?? null) ? $snapshot['dns'] : [];
        foreach (['A', 'AAAA', 'MX', 'NS', 'TXT', 'SOA', 'CNAME'] as $type) {
            $n = is_array($dns[$type] ?? null) ? count($dns[$type]) : 0;
            if ($n > 0) {
                $dnsParts[] = $type . ' ' . $n;
            }
        }
        $dnsLabel = $dnsParts === [] ? '—' : implode(' · ', $dnsParts);

        $neighbors = null;
        $loaded = false;
        foreach ($snapshot['ips'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['neighbors_loaded'])) {
                $loaded = true;
                $neighbors = ($neighbors ?? 0) + count($row['neighbors'] ?? []);
            }
        }
        if (! $loaded) {
            $neighbors = null;
        }

        return [
            'ip' => $ipLabel,
            'dns' => $dnsLabel,
            'neighbors' => $neighbors,
        ];
    }
}
