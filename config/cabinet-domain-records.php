<?php

return [
    'version' => '1.0.6',
    'dns_types' => ['A', 'AAAA', 'MX', 'NS', 'CNAME', 'TXT', 'SOA', 'SRV'],
    'doh_timeout' => 8,
    'reverse_ip_timeout' => 12,

    /** Демо на titlo.ru/zapisi-domena/ — POST /api/demo/zapisi-domena/run */
    'demo' => [
        'max_runs_per_day' => 5,
        'max_neighbors_per_ip' => 8,
    ],
];
