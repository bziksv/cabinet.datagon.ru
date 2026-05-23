<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTextAnalyzerPublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('text_analyzer_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('token', 64)->unique();
            $table->longText('payload');
            $table->string('snapshot_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['user_id', 'revoked_at']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('text_analyzer_public_shares');
    }
}
