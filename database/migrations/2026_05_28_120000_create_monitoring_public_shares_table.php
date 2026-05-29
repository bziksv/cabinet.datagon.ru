<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMonitoringPublicSharesTable extends Migration
{
    public function up()
    {
        Schema::create('monitoring_public_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('monitoring_project_id');
            $table->string('token', 64)->unique();
            $table->longText('payload');
            $table->string('snapshot_hash', 64);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('monitoring_project_id')
                ->references('id')
                ->on('monitoring_projects')
                ->onDelete('cascade');

            $table->index(['monitoring_project_id', 'revoked_at'], 'mon_public_shares_project_revoked');
            $table->index(['user_id', 'revoked_at'], 'mon_public_shares_user_revoked');
            $table->index('expires_at', 'mon_public_shares_expires');
        });
    }

    public function down()
    {
        Schema::dropIfExists('monitoring_public_shares');
    }
}
