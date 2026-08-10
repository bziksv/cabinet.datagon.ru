<?php

/**
 * Сид богатой демо-проверки Site Audit (без artisan / Laravel bootstrap).
 *
 * Usage:
 *   php scripts/seed-site-audit-rich-demo.php
 *   php scripts/seed-site-audit-rich-demo.php --user=4
 *   php scripts/seed-site-audit-rich-demo.php --email=sv6@list.ru
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/Support/SiteAuditDemoFixture.php';

use App\Support\SiteAuditDemoFixture;

function env_load(string $path): array
{
    $out = [];
    if (! is_file($path)) {
        throw new RuntimeException('.env not found');
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if ((strlen($v) >= 2) && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
            $v = substr($v, 1, -1);
        }
        $out[$k] = $v;
    }

    return $out;
}

function arg_value(array $argv, string $name): ?string
{
    foreach ($argv as $a) {
        if (strpos($a, $name . '=') === 0) {
            return substr($a, strlen($name) + 1);
        }
    }

    return null;
}

$env = env_load($root . '/.env');
$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST'] ?? '127.0.0.1',
        $env['DB_PORT'] ?? '3306',
        $env['DB_DATABASE'] ?? ''
    ),
    $env['DB_USERNAME'] ?? '',
    $env['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$userId = null;
$emailArg = arg_value($argv, '--email');
$userArg = arg_value($argv, '--user');
if ($userArg !== null && $userArg !== '') {
    $userId = (int) $userArg;
} elseif ($emailArg !== null && $emailArg !== '') {
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute([$emailArg]);
    $userId = (int) $st->fetchColumn();
} else {
    // По умолчанию — владелец gorexpert / локальный sv6
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute(['sv6@list.ru']);
    $userId = (int) $st->fetchColumn();
}

if ($userId <= 0) {
    fwrite(STDERR, "User not found\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    // Удалить прошлую фикстуру этого домена у пользователя
    $st = $pdo->prepare('SELECT id FROM site_audit_projects WHERE user_id = ? AND domain = ?');
    $st->execute([$userId, SiteAuditDemoFixture::DOMAIN]);
    $oldProjectIds = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($oldProjectIds) {
        $in = implode(',', array_map('intval', $oldProjectIds));
        $crawlIds = $pdo->query("SELECT id FROM site_audit_crawls WHERE project_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        if ($crawlIds) {
            $cin = implode(',', array_map('intval', $crawlIds));
            $pdo->exec("DELETE FROM site_audit_findings WHERE crawl_id IN ($cin)");
            $pdo->exec("DELETE FROM site_audit_pages WHERE crawl_id IN ($cin)");
            $pdo->exec("DELETE FROM site_audit_crawl_stats WHERE crawl_id IN ($cin)");
            $pdo->exec("DELETE FROM site_audit_crawls WHERE id IN ($cin)");
        }
        $pdo->exec("DELETE FROM site_audit_projects WHERE id IN ($in)");
    }

    // Старый share_token мог остаться у другого проекта
    $pdo->prepare('UPDATE site_audit_crawls SET share_token = NULL WHERE share_token = ?')
        ->execute([SiteAuditDemoFixture::SHARE_TOKEN]);

    $data = SiteAuditDemoFixture::build($userId);

    $p = $data['project'];
    $st = $pdo->prepare('INSERT INTO site_audit_projects (user_id, team_id, domain, name, settings_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?)');
    $st->execute([$p['user_id'], $p['team_id'], $p['domain'], $p['name'], $p['settings_json'], $p['created_at'], $p['updated_at']]);
    $projectId = (int) $pdo->lastInsertId();

    $c = $data['crawl'];
    $st = $pdo->prepare('INSERT INTO site_audit_crawls (
        project_id, user_id, status, pages_total, pages_fetched, pages_limit,
        buckets_json, counts_json, progress_json, error, save_html,
        share_token, share_enabled_at, share_white_label, share_brand_name, share_brand_url, share_brand_logo,
        started_at, finished_at, created_at, updated_at
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        $projectId, $c['user_id'], $c['status'], $c['pages_total'], $c['pages_fetched'], $c['pages_limit'],
        $c['buckets_json'], $c['counts_json'], $c['progress_json'], $c['error'], $c['save_html'],
        $c['share_token'], $c['share_enabled_at'], $c['share_white_label'], $c['share_brand_name'], $c['share_brand_url'], $c['share_brand_logo'],
        $c['started_at'], $c['finished_at'], $c['created_at'], $c['updated_at'],
    ]);
    $crawlId = (int) $pdo->lastInsertId();

    $pageSql = 'INSERT INTO site_audit_pages (
        crawl_id, url, url_hash, final_url, status_code, redirect_chain, size_bytes, content_type, charset,
        title, title_hash, description, description_hash, h1, h1_count, h2_count, canonical, robots_meta, noindex,
        word_count, text_len, content_hash, content_unchanged, simhash, out_links_json, img_srcs_json, asset_srcs_json,
        click_depth, discovered_via, discovered_from, img_count, img_without_alt, unique_img_src_count,
        strong_count, em_count, nausea_classic, nausea_academic, top_word, top_word_count, top_bigram, top_bigram_count,
        top_trigram, top_trigram_count, noindex_text_len, html_storage_key, html_bytes_gz, created_at, updated_at
    ) VALUES (' . implode(',', array_fill(0, 48, '?')) . ')';
    $pageSt = $pdo->prepare($pageSql);
    foreach ($data['pages'] as $row) {
        $pageSt->execute([
            $crawlId, $row['url'], $row['url_hash'], $row['final_url'], $row['status_code'], $row['redirect_chain'],
            $row['size_bytes'], $row['content_type'], $row['charset'], $row['title'], $row['title_hash'],
            $row['description'], $row['description_hash'], $row['h1'], $row['h1_count'], $row['h2_count'],
            $row['canonical'], $row['robots_meta'], $row['noindex'], $row['word_count'], $row['text_len'],
            $row['content_hash'], $row['content_unchanged'], $row['simhash'], $row['out_links_json'],
            $row['img_srcs_json'], $row['asset_srcs_json'], $row['click_depth'], $row['discovered_via'],
            $row['discovered_from'], $row['img_count'], $row['img_without_alt'], $row['unique_img_src_count'],
            $row['strong_count'], $row['em_count'], $row['nausea_classic'], $row['nausea_academic'],
            $row['top_word'], $row['top_word_count'], $row['top_bigram'], $row['top_bigram_count'],
            $row['top_trigram'], $row['top_trigram_count'], $row['noindex_text_len'], $row['html_storage_key'],
            $row['html_bytes_gz'], $row['created_at'], $row['updated_at'],
        ]);
    }

    $findCols = '(crawl_id, code, severity, url, url_hash, meta_json, created_at, updated_at)';
    $batch = [];
    $flushFindings = function () use ($pdo, &$batch, $findCols, $crawlId) {
        if ($batch === []) {
            return;
        }
        $placeholders = [];
        $values = [];
        foreach ($batch as $f) {
            $placeholders[] = '(?,?,?,?,?,?,?,?)';
            array_push(
                $values,
                $crawlId,
                $f['code'],
                $f['severity'],
                $f['url'],
                $f['url_hash'],
                $f['meta_json'],
                $f['created_at'],
                $f['updated_at']
            );
        }
        $pdo->prepare('INSERT INTO site_audit_findings ' . $findCols . ' VALUES ' . implode(',', $placeholders))
            ->execute($values);
        $batch = [];
    };
    foreach ($data['findings'] as $f) {
        $batch[] = $f;
        if (count($batch) >= 200) {
            $flushFindings();
        }
    }
    $flushFindings();

    $statSt = $pdo->prepare('INSERT INTO site_audit_crawl_stats (crawl_id, bucket, value, created_at, updated_at) VALUES (?,?,?,?,?)');
    foreach ($data['stats'] as $s) {
        $statSt->execute([$crawlId, $s['bucket'], $s['value'], $s['created_at'], $s['updated_at']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}

$s = $data['summary'];
echo "OK user_id={$userId} project_id={$projectId} crawl_id={$crawlId}\n";
echo "pages={$s['pages']} findings={$s['findings']} codes={$s['codes']}\n";
echo "Crawl: http://localhost:3002/site-audit/crawl/{$crawlId}\n";
echo "HTML: http://localhost:3002/site-audit/crawl/{$crawlId}/report/html_critical_errors\n";
