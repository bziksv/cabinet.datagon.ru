<?php

return [
    // prod: cabinet-titlo-* supervisor group; локально можно default / sync
    'queue' => env('SEO_REPORTS_QUEUE', 'default'),
    // true = generate in HTTP request (удобно локально без queue:work)
    'sync' => env('SEO_REPORTS_SYNC', env('APP_ENV') === 'local'),
    // TTL кэша ответов Метрики при сборке отчёта (сек.)
    'metrika_cache_ttl' => (int) env('SEO_REPORTS_METRIKA_CACHE_TTL', 3600),
];
