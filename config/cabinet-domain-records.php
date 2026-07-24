<?php

return [
    'version' => '1.0.7',
    'dns_types' => ['A', 'AAAA', 'MX', 'NS', 'CNAME', 'TXT', 'SOA', 'SRV'],
    'doh_timeout' => 8,
    'reverse_ip_timeout' => 12,

    /**
     * Поддомены не видны из запроса к apex — проверяем эти имена (A/AAAA/CNAME).
     * Плюс хосты *.domain из списка сайтов на том же IP.
     */
    'subdomain_probes' => [
        'www', 'old', 'mail', 'ftp', 'm', 'mobile', 'api', 'blog', 'shop', 'cdn',
        'dev', 'test', 'staging', 'stage', 'beta', 'demo', 'admin', 'panel',
        'ns1', 'ns2', 'mx', 'smtp', 'pop', 'imap', 'webmail', 'cpanel',
    ],
    'subdomain_probe_limit' => 40,

    /** Демо на titlo.ru/zapisi-domena/ — POST /api/demo/zapisi-domena/run */
    'demo' => [
        'max_runs_per_day' => 5,
        'max_neighbors_per_ip' => 8,
    ],
];
