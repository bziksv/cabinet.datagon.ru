<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSeoChecklistTimeTracking extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_items') && !Schema::hasColumn('seo_checklist_items', 'time_spent_seconds')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                $table->unsignedInteger('time_spent_seconds')->default(0)->after('done_by');
            });
        }

        // На случай частично провального прогона без FK
        Schema::dropIfExists('seo_checklist_item_time_logs');

        Schema::create('seo_checklist_item_time_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'ended_at']);
            $table->index(['user_id', 'ended_at']);
            $table->foreign('item_id')
                ->references('id')
                ->on('seo_checklist_items')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seo_checklist_item_time_logs');

        if (Schema::hasTable('seo_checklist_items') && Schema::hasColumn('seo_checklist_items', 'time_spent_seconds')) {
            Schema::table('seo_checklist_items', function (Blueprint $table) {
                $table->dropColumn('time_spent_seconds');
            });
        }
    }
}
