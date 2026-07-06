<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFromNameEnToSmtpSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('smtp_settings', function (Blueprint $table) {
            $table->string('from_name_en', 120)->nullable()->after('from_name');
        });
    }

    public function down(): void
    {
        Schema::table('smtp_settings', function (Blueprint $table) {
            $table->dropColumn('from_name_en');
        });
    }
}
