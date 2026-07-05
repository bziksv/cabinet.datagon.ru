<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMonitoringSchedulePromptSnoozedUntilToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('monitoring_schedule_prompt_snoozed_until')
                ->nullable()
                ->after('telegram_prompt_snoozed_until');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monitoring_schedule_prompt_snoozed_until');
        });
    }
}
