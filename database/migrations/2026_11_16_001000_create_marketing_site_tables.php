<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Маркетинг сайта» (админка): этапы плана, задачи этапов, контент-план (термины словаря + статьи блога с ТЗ).
 */
class CreateMarketingSiteTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('marketing_stages')) {
            Schema::create('marketing_stages', function (Blueprint $table) {
                $table->increments('id');
                $table->string('slug', 64)->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('goal')->nullable();
                $table->string('priority', 8)->default('P1');
                $table->string('status', 24)->default('planned');
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketing_tasks')) {
            Schema::create('marketing_tasks', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('stage_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 24)->default('planned');
                $table->string('priority', 8)->default('P1');
                $table->text('links')->nullable();
                $table->date('done_at')->nullable();
                $table->string('commit_ref')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['stage_id', 'sort']);
                $table->foreign('stage_id')->references('id')->on('marketing_stages')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('marketing_content_items')) {
            Schema::create('marketing_content_items', function (Blueprint $table) {
                $table->increments('id');
                $table->string('type', 16);
                $table->string('title');
                $table->string('slug')->nullable();
                $table->string('aliases', 500)->nullable();
                $table->string('category')->nullable();
                $table->string('priority', 8)->default('P2');
                $table->string('status', 24)->default('idea');
                $table->boolean('is_top')->default(false);

                // ТЗ
                $table->string('main_query')->nullable();
                $table->text('secondary_queries')->nullable();
                $table->string('intent', 64)->nullable();
                $table->string('format', 64)->nullable();
                $table->text('outline')->nullable();
                $table->text('lsi_words')->nullable();
                $table->string('lsi_source')->nullable();
                $table->date('lsi_analyzed_at')->nullable();
                $table->string('target_length', 128)->nullable();
                $table->string('modules', 500)->nullable();
                $table->text('related_terms')->nullable();
                $table->string('url', 500)->nullable();
                $table->text('notes')->nullable();

                $table->unsignedInteger('plan_number')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->unique(['type', 'title']);
                $table->index(['type', 'status']);
                $table->index(['type', 'priority']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('marketing_content_items');
        Schema::dropIfExists('marketing_tasks');
        Schema::dropIfExists('marketing_stages');
    }
}
