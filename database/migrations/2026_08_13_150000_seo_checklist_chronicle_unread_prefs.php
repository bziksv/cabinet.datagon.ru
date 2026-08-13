<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SeoChecklistChronicleUnreadPrefs extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_user_preferences')) {
            Schema::table('seo_checklist_user_preferences', function (Blueprint $table) {
                if (!Schema::hasColumn('seo_checklist_user_preferences', 'unread_notes')) {
                    $table->boolean('unread_notes')->default(true)->after('module_title');
                }
                if (!Schema::hasColumn('seo_checklist_user_preferences', 'unread_review')) {
                    $table->boolean('unread_review')->default(false)->after('unread_notes');
                }
                if (!Schema::hasColumn('seo_checklist_user_preferences', 'unread_created')) {
                    $table->boolean('unread_created')->default(false)->after('unread_review');
                }
            });
        }

        if (!Schema::hasTable('seo_checklist_activity_reads')) {
            Schema::create('seo_checklist_activity_reads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('activity_id')->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'activity_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('seo_checklist_activity_reads');

        if (Schema::hasTable('seo_checklist_user_preferences')) {
            Schema::table('seo_checklist_user_preferences', function (Blueprint $table) {
                foreach (['unread_notes', 'unread_review', 'unread_created'] as $col) {
                    if (Schema::hasColumn('seo_checklist_user_preferences', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}
