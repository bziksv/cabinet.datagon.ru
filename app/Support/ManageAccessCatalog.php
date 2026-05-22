<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Группировка и подсказки для /manage-access.
 */
class ManageAccessCatalog
{
    public const PROTECTED_ROLES = ['admin', 'user', 'Super Admin'];

    /** @var string[] */
    private const ADMIN_PERMISSIONS = [
        'Users',
        'Main projects',
        'Meta tags',
    ];

    /** @var string[] */
    private const TOOL_PERMISSIONS = [
        'Backlink',
        'Competitor analysis',
        'Counting text length',
        'Domain information',
        'Domain monitoring',
        'Duplicates',
        'Html editor',
        'Http headers',
        'Keyword generator',
        'List comparison',
        'Password generator',
        'Roi calculator',
        'Text analyzer',
        'Unique words',
        'Utm marks',
    ];

    /**
     * @return array<string, string>
     */
    public static function permissionHints(): array
    {
        return [
            'Main projects' => 'manage_access_hint_main_projects',
            'Users' => 'manage_access_hint_users',
            'Meta tags' => 'manage_access_hint_meta_tags',
            'Domain monitoring' => 'manage_access_hint_domain_monitoring',
        ];
    }

    /**
     * @param  Collection|\Spatie\Permission\Models\Permission[]  $permissions
     * @return array<int, array{key: string, title: string, hint: string, items: array}>
     */
    public static function groupPermissions($permissions): array
    {
        $buckets = [
            'admin' => [
                'key' => 'admin',
                'title' => 'manage_access_group_admin',
                'hint' => 'manage_access_group_admin_hint',
                'items' => [],
            ],
            'tools' => [
                'key' => 'tools',
                'title' => 'manage_access_group_tools',
                'hint' => 'manage_access_group_tools_hint',
                'items' => [],
            ],
            'monitoring' => [
                'key' => 'monitoring',
                'title' => 'manage_access_group_monitoring',
                'hint' => 'manage_access_group_monitoring_hint',
                'items' => [],
            ],
            'other' => [
                'key' => 'other',
                'title' => 'manage_access_group_other',
                'hint' => 'manage_access_group_other_hint',
                'items' => [],
            ],
        ];

        foreach ($permissions as $permission) {
            $bucket = self::detectGroup($permission->name);
            $buckets[$bucket]['items'][] = $permission;
        }

        $result = [];
        foreach ($buckets as $bucket) {
            if (count($bucket['items']) === 0) {
                continue;
            }
            usort($bucket['items'], static function ($a, $b) {
                return strcasecmp($a->name, $b->name);
            });
            $result[] = $bucket;
        }

        return $result;
    }

    protected static function detectGroup(string $name): string
    {
        if (in_array($name, self::ADMIN_PERMISSIONS, true)) {
            return 'admin';
        }

        if (in_array($name, self::TOOL_PERMISSIONS, true)) {
            return 'tools';
        }

        if ($name === 'Domain monitoring' || strpos($name, '_monitoring') !== false) {
            return 'monitoring';
        }

        return 'other';
    }
}
