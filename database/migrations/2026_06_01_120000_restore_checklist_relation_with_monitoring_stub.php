<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Восстановление связки мониторинг ↔ чеклист для legacy-кода на lk.redbox.su
 * (запрос ChecklistMonitoringRelation до деплоя без /checklist).
 *
 * Без FK: checklist_projects уже удалена (drop_checklist_module).
 */
class RestoreChecklistRelationWithMonitoringStub extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('checklist_relation_with_monitoring')) {
            return;
        }

        Schema::create('checklist_relation_with_monitoring', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('checklist_id');
            $table->unsignedBigInteger('monitoring_id');
            $table->index('monitoring_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_relation_with_monitoring');
    }
}
