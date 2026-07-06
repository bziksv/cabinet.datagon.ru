<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBacklinkConfigsTable extends Migration
{
    public function up(): void
    {
        Schema::create('backlink_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('default_notify_telegram')->default(false);
            $table->boolean('default_notify_email')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('telegram_notifications_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('backlink_configs')->insert([
            'default_notify_telegram' => (bool) config('cabinet-backlink.notifications.default_notify_telegram', false),
            'default_notify_email' => (bool) config('cabinet-backlink.notifications.default_notify_email', false),
            'email_notifications_enabled' => (bool) config('cabinet-backlink.notifications.email_enabled', true),
            'telegram_notifications_enabled' => (bool) config('cabinet-backlink.notifications.telegram_enabled', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('backlink_configs');
    }
}
