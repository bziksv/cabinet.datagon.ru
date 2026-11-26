<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiteAuditChecklistLinksTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('site_audit_checklist_links')) {
            return;
        }

        Schema::create('site_audit_checklist_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_audit_project_id')->unique();
            $table->unsignedBigInteger('checklist_project_id')->index();
            $table->boolean('enabled')->default(true);
            $table->json('severities_json')->nullable();
            $table->string('stage_mode', 16)->default('smart'); // smart | single
            $table->string('fallback_stage_key', 64)->default('audits');
            $table->string('default_role', 16)->default('owner');
            $table->unsignedSmallInteger('due_days_critical')->default(3);
            $table->unsignedSmallInteger('due_days_important')->default(7);
            $table->unsignedSmallInteger('due_days_other')->default(14);
            $table->unsignedBigInteger('last_synced_crawl_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_summary_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('site_audit_checklist_links');
    }
}
