<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSendRatePerMinuteToTriggerCampaigns extends Migration
{
    public function up(): void
    {
        Schema::table('trigger_campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('send_rate_per_minute')
                ->default(10)
                ->after('coupon_expires_days');
        });
    }

    public function down(): void
    {
        Schema::table('trigger_campaigns', function (Blueprint $table) {
            $table->dropColumn('send_rate_per_minute');
        });
    }
}
