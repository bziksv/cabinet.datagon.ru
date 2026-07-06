<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotifyChannelsToProjectTrackingTable extends Migration
{
    public function up(): void
    {
        Schema::table('project_tracking', function (Blueprint $table) {
            $table->boolean('notify_telegram')->default(false)->after('total_link');
            $table->boolean('notify_email')->default(false)->after('notify_telegram');
        });
    }

    public function down(): void
    {
        Schema::table('project_tracking', function (Blueprint $table) {
            $table->dropColumn(['notify_telegram', 'notify_email']);
        });
    }
}
