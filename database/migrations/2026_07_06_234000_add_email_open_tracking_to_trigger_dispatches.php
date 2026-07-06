<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailOpenTrackingToTriggerDispatches extends Migration
{
    public function up(): void
    {
        Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('promo_code_id');
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->unsignedInteger('open_count')->default(0)->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
            $table->dropColumn(['tracking_token', 'opened_at', 'open_count']);
        });
    }
}
