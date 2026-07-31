<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSkipWeekendsAndExtendRepeat extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_templates') && !Schema::hasColumn('seo_checklist_templates', 'skip_weekends')) {
            Schema::table('seo_checklist_templates', function (Blueprint $table) {
                $table->boolean('skip_weekends')->default(false)->after('is_system');
            });
        }

        if (Schema::hasTable('seo_checklist_projects') && !Schema::hasColumn('seo_checklist_projects', 'skip_weekends')) {
            Schema::table('seo_checklist_projects', function (Blueprint $table) {
                $table->boolean('skip_weekends')->default(false)->after('status');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('seo_checklist_templates') && Schema::hasColumn('seo_checklist_templates', 'skip_weekends')) {
            Schema::table('seo_checklist_templates', function (Blueprint $table) {
                $table->dropColumn('skip_weekends');
            });
        }

        if (Schema::hasTable('seo_checklist_projects') && Schema::hasColumn('seo_checklist_projects', 'skip_weekends')) {
            Schema::table('seo_checklist_projects', function (Blueprint $table) {
                $table->dropColumn('skip_weekends');
            });
        }
    }
}
