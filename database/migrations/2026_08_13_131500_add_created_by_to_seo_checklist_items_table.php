<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreatedByToSeoChecklistItemsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_checklist_items')) {
            return;
        }
        if (Schema::hasColumn('seo_checklist_items', 'created_by')) {
            return;
        }

        Schema::table('seo_checklist_items', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('done_by')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('seo_checklist_items') || !Schema::hasColumn('seo_checklist_items', 'created_by')) {
            return;
        }

        Schema::table('seo_checklist_items', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
}
