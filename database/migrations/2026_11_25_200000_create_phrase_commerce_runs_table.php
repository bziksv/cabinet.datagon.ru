<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePhraseCommerceRunsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('phrase_commerce_runs')) {
            return;
        }

        Schema::create('phrase_commerce_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('save_to_history')->default(true);
            $table->unsignedBigInteger('history_id')->nullable();
            $table->string('title', 255)->nullable();
            $table->json('params');
            $table->longText('results')->nullable();
            $table->unsignedInteger('phrases_total')->default(0);
            $table->unsignedInteger('phrases_done')->default(0);
            $table->string('current_phrase', 255)->nullable();
            $table->unsignedInteger('estimated_cost')->default(0);
            $table->unsignedInteger('actual_cost')->default(0);
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phrase_commerce_runs');
    }
}
