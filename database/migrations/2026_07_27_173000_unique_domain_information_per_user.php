<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UniqueDomainInformationPerUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Оставляем самую раннюю запись на пару user_id + domain (без учёта регистра).
        $keepIds = DB::table('domain_information')
            ->selectRaw('MIN(id) as id')
            ->groupBy(DB::raw('user_id'), DB::raw('LOWER(domain)'))
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            DB::table('domain_information')
                ->whereNotIn('id', $keepIds->all())
                ->delete();
        }

        DB::statement('UPDATE domain_information SET domain = LOWER(domain)');

        Schema::table('domain_information', function (Blueprint $table) {
            $table->unique(['user_id', 'domain'], 'domain_information_user_domain_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('domain_information', function (Blueprint $table) {
            $table->dropUnique('domain_information_user_domain_unique');
        });
    }
}
