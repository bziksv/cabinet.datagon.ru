<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeatureIdeasTables extends Migration
{
    public function up(): void
    {
        Schema::create('feature_ideas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedBigInteger('moderated_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('moderator_note', 500)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('moderated_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'votes_count', 'approved_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('feature_idea_votes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('feature_idea_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('feature_idea_id')
                ->references('id')
                ->on('feature_ideas')
                ->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['feature_idea_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_idea_votes');
        Schema::dropIfExists('feature_ideas');
    }
}
