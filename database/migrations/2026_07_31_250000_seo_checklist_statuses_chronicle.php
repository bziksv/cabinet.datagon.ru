<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeoChecklistStatusesChronicle extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_items')) {
            // legacy blocked → clarify
            DB::table('seo_checklist_items')
                ->where('status', 'blocked')
                ->update(['status' => 'clarify']);
        }

        if (!Schema::hasTable('seo_checklist_activity_logs')) {
            Schema::create('seo_checklist_activity_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('item_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 32)->index(); // status_change|note
                $table->json('meta_json')->nullable();
                $table->timestamps();
                $table->index(['project_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('seo_checklist_note_reads')) {
            Schema::create('seo_checklist_note_reads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('note_id')->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'note_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('seo_checklist_note_reads');
        Schema::dropIfExists('seo_checklist_activity_logs');
    }
}
