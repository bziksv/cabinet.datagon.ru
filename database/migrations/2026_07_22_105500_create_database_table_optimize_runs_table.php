<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDatabaseTableOptimizeRunsTable extends Migration
{
    public function up(): void
    {
        Schema::create('database_table_optimize_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('table_name', 64)->index();
            $table->string('status', 16)->default('queued'); // queued|running|ok|failed
            $table->string('mode', 16)->default('sync'); // sync|queue
            $table->string('triggered_by', 32)->default('ui'); // ui|cron|artisan
            $table->decimal('size_before_mb', 12, 2)->nullable();
            $table->decimal('size_after_mb', 12, 2)->nullable();
            $table->decimal('freed_mb', 12, 2)->nullable();
            $table->decimal('data_free_before_mb', 12, 2)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_table_optimize_runs');
    }
}
