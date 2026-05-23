<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRelevancePublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('relevance_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('owner_id');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')
                ->references('id')
                ->on('project_relevance_history')
                ->onDelete('cascade');

            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['project_id', 'revoked_at']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('relevance_public_shares');
    }
}
