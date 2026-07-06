<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsPausedToTriggerCampaigns extends Migration
{
    public function up(): void
    {
        Schema::table('trigger_campaigns', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('trigger_campaigns', function (Blueprint $table) {
            $table->dropColumn('is_paused');
        });
    }
}
