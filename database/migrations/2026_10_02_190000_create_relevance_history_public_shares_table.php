<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRelevanceHistoryPublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('relevance_history_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('history_id');
            $table->unsignedBigInteger('owner_id');
            $table->string('token', 64)->unique();
            $table->unsignedInteger('ttl_days')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['history_id', 'revoked_at']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('relevance_history_public_shares');
    }
}
