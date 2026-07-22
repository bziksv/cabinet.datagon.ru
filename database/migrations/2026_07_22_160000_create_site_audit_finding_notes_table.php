<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Комментарий + статус «исправлено» на finding (project-level, между краулами).
 */
class CreateSiteAuditFindingNotesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_audit_finding_notes')) {
            return;
        }

        Schema::create('site_audit_finding_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('code', 64)->index();
            $table->string('url_hash', 64);
            $table->string('url', 2048)->nullable();
            $table->string('status', 16)->default('open'); // open|fixed
            $table->string('comment', 1000)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code', 'url_hash'], 'site_audit_finding_notes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audit_finding_notes');
    }
}
