<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationDispatchLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatch_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_id', 64)->index();
            $table->string('channel', 16);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('source', 32)->default('system');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['event_id', 'channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatch_logs');
    }
}
