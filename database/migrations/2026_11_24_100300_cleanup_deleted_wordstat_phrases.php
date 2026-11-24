<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CleanupDeletedWordstatPhrases extends Migration
{
    // Очищаем фразы, оставшиеся со старым мягким удалением, чтобы их можно было собрать заново
    public function up()
    {
        DB::table('wordstat_phrases')->where('status', 'deleted')->delete();
    }

    public function down()
    {
        // Удалённые строки не восстанавливаются
    }
}
