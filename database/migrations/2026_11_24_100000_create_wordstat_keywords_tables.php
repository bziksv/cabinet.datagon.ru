<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWordstatKeywordsTables extends Migration
{
    public function up(): void
    {
        Schema::create('wordstat_projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('domain', 255);
            $table->string('title', 255)->nullable();
            $table->json('region_ids')->nullable();
            $table->string('device', 16)->default('all');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wordstat_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id')->index();
            $table->string('name', 255);
            $table->string('page_url', 2048)->nullable();
            $table->string('source', 16)->default('manual');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::create('wordstat_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->json('settings');
            $table->string('status', 16)->default('queued')->index();
            $table->unsignedInteger('seeds_total')->default(0);
            $table->unsignedInteger('seeds_done')->default(0);
            $table->unsignedInteger('requests_estimated')->default(0);
            $table->unsignedInteger('requests_used')->default(0);
            $table->unsignedInteger('phrases_added')->default(0);
            $table->string('error', 1000)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wordstat_run_seeds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('group_id');
            $table->string('phrase', 400);
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('requests_used')->default(0);
            $table->string('error', 1000)->nullable();
            $table->timestamps();
            $table->index(['run_id', 'status']);
        });

        Schema::create('wordstat_phrases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('group_id')->index();
            $table->string('phrase', 400);
            $table->string('phrase_key', 191);
            $table->unsignedBigInteger('freq')->nullable();
            $table->unsignedBigInteger('freq_exact')->nullable();
            $table->string('source', 16);
            $table->unsignedBigInteger('seed_phrase_id')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('status', 16)->default('active');
            $table->boolean('is_commercial')->nullable();
            $table->unsignedBigInteger('run_id')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'phrase_key']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('wordstat_presets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('title', 255);
            $table->json('settings');
            $table->timestamps();
        });

        Schema::create('wordstat_request_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('run_id')->nullable();
            $table->string('kind', 16)->default('words');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordstat_request_log');
        Schema::dropIfExists('wordstat_presets');
        Schema::dropIfExists('wordstat_phrases');
        Schema::dropIfExists('wordstat_run_seeds');
        Schema::dropIfExists('wordstat_runs');
        Schema::dropIfExists('wordstat_groups');
        Schema::dropIfExists('wordstat_projects');
    }
}
