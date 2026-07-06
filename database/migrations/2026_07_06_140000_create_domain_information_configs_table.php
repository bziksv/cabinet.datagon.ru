<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDomainInformationConfigsTable extends Migration
{
    public function up(): void
    {
        Schema::create('domain_information_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('expiration_alert_days')->default(20);
            $table->boolean('default_check_dns')->default(false);
            $table->boolean('default_check_registration_date')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('telegram_notifications_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('domain_information_configs')->insert([
            'expiration_alert_days' => (int) config('cabinet-domain-information.notifications.expiration_alert_days', 20),
            'default_check_dns' => (bool) config('cabinet-domain-information.notifications.default_check_dns', false),
            'default_check_registration_date' => (bool) config('cabinet-domain-information.notifications.default_check_registration_date', false),
            'email_notifications_enabled' => (bool) config('cabinet-domain-information.notifications.email_enabled', true),
            'telegram_notifications_enabled' => (bool) config('cabinet-domain-information.notifications.telegram_enabled', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_information_configs');
    }
}
