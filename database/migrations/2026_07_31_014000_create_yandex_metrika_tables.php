<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYandexMetrikaTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('yandex_metrika_user_tokens')) {
            Schema::create('yandex_metrika_user_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('yandex_login', 191)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('yandex_metrika_domain_counters')) {
            Schema::create('yandex_metrika_domain_counters', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('domain', 255);
                $table->unsignedBigInteger('counter_id');
                $table->string('counter_name', 255)->nullable();
                $table->string('counter_site', 255)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'domain'], 'ym_domain_counters_user_domain_unique');
                $table->index(['user_id', 'counter_id'], 'ym_domain_counters_user_counter_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_metrika_domain_counters');
        Schema::dropIfExists('yandex_metrika_user_tokens');
    }
}
