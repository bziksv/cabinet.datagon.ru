<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStagesJsonToSeoChecklistTemplates extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_templates') && !Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
            Schema::table('seo_checklist_templates', function (Blueprint $table) {
                $table->json('stages_json')->nullable()->after('description');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('seo_checklist_templates') && Schema::hasColumn('seo_checklist_templates', 'stages_json')) {
            Schema::table('seo_checklist_templates', function (Blueprint $table) {
                $table->dropColumn('stages_json');
            });
        }
    }
}
