<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlignDomainRecordsTariffLabel extends Migration
{
    public function up()
    {
        DB::table('tariff_settings')
            ->where('code', 'DomainRecords')
            ->update(['name' => 'Записи домена (проверки / сохранения)']);
    }

    public function down()
    {
        DB::table('tariff_settings')
            ->where('code', 'DomainRecords')
            ->update(['name' => 'Записи домена (проверки)']);
    }
}
