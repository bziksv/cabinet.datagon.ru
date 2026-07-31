<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeoChecklistTables extends Migration
{
    public function up(): void
    {
        Schema::create('seo_checklist_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('code', 64)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('seo_checklist_template_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('template_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('code', 96);
            $table->string('stage_key', 48)->index();
            $table->unsignedSmallInteger('stage_sort')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('title');
            $table->text('help')->nullable();
            $table->string('role', 24)->default('owner'); // owner|pm|shared|any
            $table->boolean('is_important')->default(false);
            $table->boolean('allows_subtasks')->default(false);
            $table->string('repeat_rule', 32)->nullable(); // monthly|weekly|null
            $table->json('links_json')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'code']);
        });

        Schema::create('seo_checklist_projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('template_id')->index();
            $table->string('domain', 255);
            $table->string('title')->nullable();
            $table->string('status', 24)->default('active'); // active|archived
            $table->unsignedBigInteger('pm_user_id')->nullable()->index();
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            $table->unsignedSmallInteger('progress_done')->default(0);
            $table->unsignedSmallInteger('progress_total')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'domain']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('seo_checklist_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('code', 96);
            $table->string('stage_key', 48)->index();
            $table->unsignedSmallInteger('stage_sort')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('title');
            $table->text('help')->nullable();
            $table->string('role', 24)->default('owner');
            $table->boolean('is_important')->default(false);
            $table->boolean('allows_subtasks')->default(false);
            $table->string('repeat_rule', 32)->nullable();
            $table->json('links_json')->nullable();
            $table->string('status', 24)->default('todo'); // todo|doing|done|skip|blocked
            $table->unsignedBigInteger('assignee_user_id')->nullable()->index();
            $table->timestamp('done_at')->nullable();
            $table->unsignedBigInteger('done_by')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'stage_key', 'sort']);
        });

        Schema::create('seo_checklist_item_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('item_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_checklist_item_notes');
        Schema::dropIfExists('seo_checklist_items');
        Schema::dropIfExists('seo_checklist_projects');
        Schema::dropIfExists('seo_checklist_template_tasks');
        Schema::dropIfExists('seo_checklist_templates');
    }
}
