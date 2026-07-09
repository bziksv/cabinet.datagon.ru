<?php

/**
 * Применить миграцию esenin-text-check без Laravel (PHP 8+ локально).
 * Запуск: php scripts/seed-esenin-text-check-standalone.php
 */

$root = dirname(__DIR__);

foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if ($line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$db = $_ENV['DB_DATABASE'] ?? '';
$user = $_ENV['DB_USERNAME'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

const TEXT_ANALYZER_PROJECT_ID = 15;
const MIGRATION = '2026_07_09_120000_add_esenin_text_check_module';
const TARIFF_LIMITS = [
    'Free' => 5,
    'Optimal' => 200,
    'Ultimate' => 500,
    'Maximum' => 1000,
];

function tableExists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SHOW TABLES LIKE ?');
    $st->execute([$table]);

    return (bool) $st->fetchColumn();
}

function migrationDone(PDO $pdo): bool
{
    if (! tableExists($pdo, 'migrations')) {
        return false;
    }
    $st = $pdo->prepare('SELECT 1 FROM migrations WHERE migration = ? LIMIT 1');
    $st->execute([MIGRATION]);

    return (bool) $st->fetchColumn();
}

function positionsContainId(array $positions, int $searchId): bool
{
    foreach ($positions as $item) {
        if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
            foreach ($item as $entry) {
                if (isset($entry['id']) && (int) $entry['id'] === $searchId) {
                    return true;
                }
            }
            continue;
        }
        if (isset($item['id']) && (int) $item['id'] === $searchId) {
            return true;
        }
    }

    return false;
}

function insertAfterIdInPositions(array $positions, int $afterId, int $newId, bool &$changed): array
{
    $result = [];

    foreach ($positions as $item) {
        if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
            $group = [];
            $groupChanged = false;

            foreach ($item as $entry) {
                if (isset($entry['dir'])) {
                    $group[] = $entry;
                    continue;
                }

                $group[] = $entry;

                if (isset($entry['id']) && (int) $entry['id'] === $afterId) {
                    $group[] = ['id' => $newId];
                    $groupChanged = true;
                    $changed = true;
                }
            }

            if (count($group) > 1) {
                $result[] = $group;
            } elseif ($groupChanged) {
                $result[] = $group;
            }

            continue;
        }

        $result[] = $item;

        if (isset($item['id']) && (int) $item['id'] === $afterId) {
            $result[] = ['id' => $newId];
            $changed = true;
        }
    }

    return $result;
}

if (migrationDone($pdo)) {
    echo "Migration already applied.\n";
    exit(0);
}

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS esenin_text_check_usages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    period VARCHAR(7) NOT NULL,
    used INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY esenin_text_check_usages_user_id_period_unique (user_id, period),
    KEY esenin_text_check_usages_period_index (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
);
echo "Created table esenin_text_check_usages\n";

$now = date('Y-m-d H:i:s');

if (tableExists($pdo, 'tariff_settings')) {
    $st = $pdo->query("SELECT id FROM tariff_settings WHERE code = 'EseninTextCheck' LIMIT 1");
    if (! $st->fetchColumn()) {
        $pdo->prepare(
            'INSERT INTO tariff_settings (name, code, description, message, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            'Проверка текста Есенин (лимит в месяц)',
            'EseninTextCheck',
            '1 проверка текста или страницы = 1 лимит. Локальный анализ SEO-текста.',
            'Лимит проверок «Есенин» исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
            $now,
            $now,
        ]);
        $settingId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO tariff_setting_values (tariff_setting_id, tariff, value, sort, created_at, updated_at) VALUES (?, ?, ?, 500, ?, ?)'
        );
        foreach (TARIFF_LIMITS as $tariff => $value) {
            $ins->execute([$settingId, $tariff, $value, $now, $now]);
        }
        echo "Seeded tariff EseninTextCheck\n";
    }
}

$newId = null;
if (tableExists($pdo, 'main_projects')) {
    $st = $pdo->query("SELECT id FROM main_projects WHERE link LIKE '%/esenin-text-check%' LIMIT 1");
    $newId = $st->fetchColumn();
    if (! $newId) {
        $parent = $pdo->query('SELECT access, position, buttons FROM main_projects WHERE id = ' . TEXT_ANALYZER_PROJECT_ID . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($parent) {
            $pdo->prepare(
                'INSERT INTO main_projects (access, controller, color, title, description, link, icon, `show`, position, buttons, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)'
            )->execute([
                $parent['access'],
                "EseninTextCheckController@index\r\n",
                '#6f42c1',
                'Esenin text check',
                'Оценка риска «Баден-Баден» для SEO-текстов.',
                'https://cabinet.titlo.ru/esenin-text-check',
                '<i class="fas fa-spell-check"></i>',
                ((int) ($parent['position'] ?? 80)) + 1,
                $parent['buttons'] ?? '[]',
                $now,
                $now,
            ]);
            $newId = (int) $pdo->lastInsertId();
            echo "Created main_projects id={$newId}\n";
        }
    } else {
        $newId = (int) $newId;
        echo "main_projects already exists id={$newId}\n";
    }
}

if (tableExists($pdo, 'permissions')) {
    $st = $pdo->query("SELECT id FROM permissions WHERE name = 'Esenin text check' LIMIT 1");
    $permId = $st->fetchColumn();
    if (! $permId) {
        $pdo->prepare('INSERT INTO permissions (name, guard_name, created_at, updated_at) VALUES (?, ?, ?, ?)')
            ->execute(['Esenin text check', 'web', $now, $now]);
        $permId = (int) $pdo->lastInsertId();
        echo "Created permission Esenin text check id={$permId}\n";

        $textAnalyzerId = $pdo->query("SELECT id FROM permissions WHERE name = 'Text analyzer' LIMIT 1")->fetchColumn();
        if ($textAnalyzerId && tableExists($pdo, 'role_has_permissions')) {
            $roles = $pdo->query('SELECT role_id FROM role_has_permissions WHERE permission_id = ' . (int) $textAnalyzerId)->fetchAll(PDO::FETCH_COLUMN);
            $ins = $pdo->prepare('INSERT IGNORE INTO role_has_permissions (permission_id, role_id) VALUES (?, ?)');
            foreach ($roles as $roleId) {
                $ins->execute([$permId, $roleId]);
            }
            echo 'Assigned permission to ' . count($roles) . " roles\n";
        }
    }
}

if ($newId && tableExists($pdo, 'menu_items_position')) {
    $rows = $pdo->query('SELECT id, positions FROM menu_items_position')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (empty($row['positions'])) {
            continue;
        }
        $positions = json_decode($row['positions'], true);
        if (! is_array($positions) || positionsContainId($positions, $newId)) {
            continue;
        }
        $changed = false;
        $updated = insertAfterIdInPositions($positions, TEXT_ANALYZER_PROJECT_ID, $newId, $changed);
        if ($changed) {
            $pdo->prepare('UPDATE menu_items_position SET positions = ? WHERE id = ?')
                ->execute([json_encode($updated), $row['id']]);
        }
    }
    echo "Updated menu positions after text analyzer\n";
}

if (tableExists($pdo, 'migrations')) {
    $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)')
        ->execute([MIGRATION, 1]);
}

echo "Done. Open /esenin-text-check after re-login.\n";
