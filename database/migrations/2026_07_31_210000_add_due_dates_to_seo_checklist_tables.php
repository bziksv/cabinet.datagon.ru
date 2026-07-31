<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDueDatesToSeoChecklistTables extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_checklist_template_tasks')
            && !Schema::hasColumn('seo_checklist_template_tasks', 'due_days_from_start')
        ) {
            Schema::table('seo_checklist_template_tasks', function (Blueprint $table) {
                $table->unsignedSmallInteger('due_days_from_start')->nullable()->after('repeat_rule');
            });
        }

        if (Schema::hasTable('seo_checklist_items')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                if (!Schema::hasColumn('seo_checklist_items', 'due_days_from_start')) {
                    $table->unsignedSmallInteger('due_days_from_start')->nullable()->after('repeat_rule');
                }
                if (!Schema::hasColumn('seo_checklist_items', 'due_at')) {
                    $table->timestamp('due_at')->nullable()->index()->after('due_days_from_start');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seo_checklist_template_tasks')
            && Schema::hasColumn('seo_checklist_template_tasks', 'due_days_from_start')
        ) {
            Schema::table('seo_checklist_template_tasks', function (Blueprint $table) {
                $table->dropColumn('due_days_from_start');
            });
        }

        if (Schema::hasTable('seo_checklist_items')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                if (Schema::hasColumn('seo_checklist_items', 'due_at')) {
                    $table->dropColumn('due_at');
                }
                if (Schema::hasColumn('seo_checklist_items', 'due_days_from_start')) {
                    $table->dropColumn('due_days_from_start');
                }
            });
        }
    }
}
