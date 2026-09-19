<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationApiTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('integration_api_keys')) {
            Schema::create('integration_api_keys', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name', 120);
                $table->string('prefix', 16)->index();
                $table->string('key_hash', 64)->unique();
                $table->json('scopes')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('integration_analyses')) {
            Schema::create('integration_analyses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('api_key_id')->nullable()->index();
                $table->string('progress_hash', 64)->index();
                $table->unsignedBigInteger('history_id')->nullable()->index();
                $table->string('status', 32)->default('queued')->index();
                $table->string('url', 2048);
                $table->string('phrase', 50);
                $table->json('request_payload')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('api_key_id')->references('id')->on('integration_api_keys')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('integration_batches')) {
            Schema::create('integration_batches', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('api_key_id')->nullable()->index();
                $table->string('status', 32)->default('queued')->index();
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('done_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('api_key_id')->references('id')->on('integration_api_keys')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('integration_batch_items')) {
            Schema::create('integration_batch_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('batch_id')->index();
                $table->string('external_id', 120)->nullable()->index();
                $table->string('url', 2048);
                $table->string('phrase', 50);
                $table->string('status', 32)->default('queued')->index();
                $table->uuid('analysis_public_id')->nullable()->index();
                $table->unsignedBigInteger('history_id')->nullable()->index();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->foreign('batch_id')->references('id')->on('integration_batches')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('integration_batch_items');
        Schema::dropIfExists('integration_batches');
        Schema::dropIfExists('integration_analyses');
        Schema::dropIfExists('integration_api_keys');
    }
}
