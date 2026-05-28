<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierToTelegramProxiesTable extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_proxies', function (Blueprint $table) {
            $table->string('supplier', 120)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_proxies', function (Blueprint $table) {
            $table->dropColumn('supplier');
        });
    }
}
