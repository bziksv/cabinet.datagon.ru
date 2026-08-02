<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeoReportsTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_report_projects')) {
            Schema::create('seo_report_projects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('domain', 255);
                $table->string('title')->nullable();
                $table->string('status', 24)->default('active'); // active|archived
                $table->string('agency_name')->nullable();
                $table->string('agency_address')->nullable();
                $table->string('agency_email')->nullable();
                $table->string('agency_phone', 64)->nullable();
                $table->string('agency_logo_path')->nullable();
                $table->string('brand_color', 16)->nullable();
                $table->string('manager_name')->nullable();
                $table->string('manager_phone', 64)->nullable();
                $table->string('manager_email')->nullable();
                $table->unsignedInteger('metrika_counter_id')->nullable();
                $table->unsignedBigInteger('monitoring_project_id')->nullable()->index();
                $table->json('section_toggles')->nullable(); // section_key => bool
                $table->json('settings_json')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'domain']);
                $table->index(['user_id', 'status']);
            });
        }

        if (!Schema::hasTable('seo_reports')) {
            Schema::create('seo_reports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('status', 32)->default('draft'); // draft|generating|ready|failed|approved_by_client
                $table->date('period_from');
                $table->date('period_to');
                $table->date('compare_from')->nullable();
                $table->date('compare_to')->nullable();
                $table->string('public_token', 64)->nullable()->unique();
                $table->string('public_pin', 16)->nullable();
                $table->json('snapshot_json')->nullable();
                $table->json('section_states')->nullable(); // section_key => {enabled, source_status, ...}
                $table->json('comments_json')->nullable();
                $table->text('summary_text')->nullable();
                $table->text('work_done_text')->nullable();
                $table->text('work_plan_text')->nullable();
                $table->string('fail_reason')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['project_id', 'status']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_reports');
        Schema::dropIfExists('seo_report_projects');
    }
}
