<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYandexWebmasterTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('yandex_webmaster_user_tokens')) {
            Schema::create('yandex_webmaster_user_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('yandex_login', 191)->nullable();
                $table->unsignedBigInteger('yandex_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('yandex_webmaster_domain_hosts')) {
            Schema::create('yandex_webmaster_domain_hosts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('domain', 255);
                $table->string('host_id', 255);
                $table->string('host_url', 255)->nullable();
                $table->boolean('verified')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'domain'], 'yw_domain_hosts_user_domain_unique');
                $table->index(['user_id', 'host_id'], 'yw_domain_hosts_user_host_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_webmaster_domain_hosts');
        Schema::dropIfExists('yandex_webmaster_user_tokens');
    }
}
