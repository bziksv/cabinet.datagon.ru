<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FK CASCADE на users для чек-листа и SEO-отчётов (после чистки сирот).
 * Явная purge-логика в InactiveUsersPurge остаётся запасным контуром.
 */
class AddUserCascadeFkSeoChecklistAndReports extends Migration
{
    public function up(): void
    {
        $this->purgeOrphanUserRefs();

        $this->addUserFkCascade('seo_report_projects', 'user_id', 'sr_projects_user_fk');
        $this->addUserFkCascade('seo_reports', 'user_id', 'seo_reports_user_fk');
        $this->addUserFkCascade('seo_report_templates', 'user_id', 'sr_templates_user_fk');

        $this->addUserFkCascade('seo_checklist_projects', 'user_id', 'sc_projects_user_fk');
        $this->addUserFkCascade('seo_checklist_teams', 'user_id', 'sc_teams_user_fk');
        $this->addUserFkCascade('seo_checklist_team_members', 'user_id', 'sc_team_members_user_fk');
        $this->addUserFkCascade('seo_checklist_user_preferences', 'user_id', 'sc_prefs_user_fk');
        $this->addUserFkCascade('seo_checklist_item_notes', 'user_id', 'sc_notes_user_fk');
        $this->widenUserIdColumn('seo_checklist_item_time_logs', 'user_id');
        $this->addUserFkCascade('seo_checklist_item_time_logs', 'user_id', 'sc_time_user_fk');
        $this->addUserFkCascade('seo_checklist_activity_logs', 'user_id', 'sc_activity_user_fk');
        $this->addUserFkCascade('seo_checklist_note_reads', 'user_id', 'sc_note_reads_user_fk');

        // Шаблоны: user_id nullable (системные)
        $this->addUserFkCascade('seo_checklist_templates', 'user_id', 'sc_templates_user_fk', true);

        $this->addUserFkSetNull('seo_checklist_projects', 'pm_user_id', 'sc_projects_pm_fk');
        $this->addUserFkSetNull('seo_checklist_projects', 'owner_user_id', 'sc_projects_owner_fk');
        $this->addUserFkSetNull('seo_checklist_items', 'assignee_user_id', 'sc_items_assignee_fk');
        if (Schema::hasTable('seo_checklist_items') && Schema::hasColumn('seo_checklist_items', 'done_by')) {
            $this->addUserFkSetNull('seo_checklist_items', 'done_by', 'sc_items_done_by_fk');
        }
    }

    public function down(): void
    {
        foreach ([
            'seo_report_projects' => ['sr_projects_user_fk'],
            'seo_reports' => ['seo_reports_user_fk'],
            'seo_report_templates' => ['sr_templates_user_fk'],
            'seo_checklist_projects' => ['sc_projects_user_fk', 'sc_projects_pm_fk', 'sc_projects_owner_fk'],
            'seo_checklist_teams' => ['sc_teams_user_fk'],
            'seo_checklist_team_members' => ['sc_team_members_user_fk'],
            'seo_checklist_user_preferences' => ['sc_prefs_user_fk'],
            'seo_checklist_item_notes' => ['sc_notes_user_fk'],
            'seo_checklist_item_time_logs' => ['sc_time_user_fk'],
            'seo_checklist_activity_logs' => ['sc_activity_user_fk'],
            'seo_checklist_note_reads' => ['sc_note_reads_user_fk'],
            'seo_checklist_templates' => ['sc_templates_user_fk'],
            'seo_checklist_items' => ['sc_items_assignee_fk', 'sc_items_done_by_fk'],
        ] as $table => $fks) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $fks) {
                foreach ($fks as $fk) {
                    try {
                        $blueprint->dropForeign($fk);
                    } catch (\Throwable $e) {
                        // уже нет
                    }
                }
            });
        }
    }

    private function purgeOrphanUserRefs(): void
    {
        $pairs = [
            ['seo_report_projects', 'user_id', false],
            ['seo_reports', 'user_id', false],
            ['seo_report_templates', 'user_id', false],
            ['seo_checklist_projects', 'user_id', false],
            ['seo_checklist_teams', 'user_id', false],
            ['seo_checklist_team_members', 'user_id', false],
            ['seo_checklist_user_preferences', 'user_id', false],
            ['seo_checklist_item_notes', 'user_id', false],
            ['seo_checklist_item_time_logs', 'user_id', false],
            ['seo_checklist_activity_logs', 'user_id', false],
            ['seo_checklist_note_reads', 'user_id', false],
            ['seo_checklist_templates', 'user_id', true],
            ['seo_checklist_projects', 'pm_user_id', true],
            ['seo_checklist_projects', 'owner_user_id', true],
            ['seo_checklist_items', 'assignee_user_id', true],
            ['seo_checklist_items', 'done_by', true],
        ];

        foreach ($pairs as [$table, $column, $nullable]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            if ($nullable) {
                DB::table($table)
                    ->whereNotNull($column)
                    ->whereRaw($column . ' NOT IN (SELECT id FROM users)')
                    ->update([$column => null]);
            } else {
                DB::table($table)
                    ->whereRaw($column . ' NOT IN (SELECT id FROM users)')
                    ->delete();
            }
        }
    }

    private function widenUserIdColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $col = DB::selectOne('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` WHERE Field = ?', [$column]);
        if ($col === null) {
            return;
        }
        $type = strtolower((string) ($col->Type ?? ''));
        if (strpos($type, 'bigint') !== false) {
            return;
        }

        // users.id = bigint unsigned — выравниваем тип перед FK
        DB::statement(
            'ALTER TABLE `' . str_replace('`', '``', $table) . '` MODIFY `' . str_replace('`', '``', $column) . '` BIGINT UNSIGNED NOT NULL'
        );
    }

    private function addUserFkCascade(string $table, string $column, string $fkName, bool $nullable = false): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || $this->fkExists($table, $fkName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $fkName) {
                $blueprint->foreign($column, $fkName)->references('id')->on('users')->onDelete('cascade');
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function addUserFkSetNull(string $table, string $column, string $fkName): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || $this->fkExists($table, $fkName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $fkName) {
                $blueprint->foreign($column, $fkName)->references('id')->on('users')->onDelete('set null');
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function fkExists(string $table, string $fkName): bool
    {
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $fkName, 'FOREIGN KEY']
        );

        return $row !== null;
    }
}
