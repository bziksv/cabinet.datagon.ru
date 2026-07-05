<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заглушка checklist_projects для legacy lk.redbox.su (monitoring/show.blade.php).
 * Пустая таблица — модалка «Связать с чеклистом» без 500, список чеклистов пустой.
 */
class RestoreChecklistProjectsStub extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('checklist_projects')) {
            return;
        }

        Schema::create('checklist_projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('icon')->default('');
            $table->string('url')->index();
            $table->boolean('archive')->default(0)->index();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_projects');
    }
}
