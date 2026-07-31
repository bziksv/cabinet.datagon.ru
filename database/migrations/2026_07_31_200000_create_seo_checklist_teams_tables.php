<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeoChecklistTeamsTables extends Migration
{
    public function up(): void
    {
        Schema::create('seo_checklist_teams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_checklist_team_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            // owner|auditor|pm|participant
            $table->string('role', 24)->default('participant');
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
            $table->index(['team_id', 'role']);
            $table->index(['user_id', 'role']);
        });

        Schema::table('seo_checklist_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('seo_checklist_projects', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->index()->after('template_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('seo_checklist_projects') && Schema::hasColumn('seo_checklist_projects', 'team_id')) {
            Schema::table('seo_checklist_projects', function (Blueprint $table) {
                $table->dropColumn('team_id');
            });
        }
        Schema::dropIfExists('seo_checklist_team_members');
        Schema::dropIfExists('seo_checklist_teams');
    }
}
