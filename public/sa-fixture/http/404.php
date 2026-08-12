<?php
/**
 * Fixture for Site Audit broken_external_link (test.titlo.ru → cabinet.titlo.ru).
 * Not a product page. noindex.
 */
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Site Audit fixture HTTP 404\n";
