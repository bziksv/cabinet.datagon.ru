<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSmtpSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->boolean('enabled')->default(false);
            $table->string('provider_label', 120)->nullable();
            $table->string('driver', 32)->default('smtp');
            $table->string('host', 255)->nullable();
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption', 16)->nullable();
            $table->string('username', 255)->nullable();
            $table->text('password')->nullable();
            $table->string('from_address', 255)->nullable();
            $table->string('from_name', 120)->nullable();
            $table->timestamps();
        });

        $this->seedFromEnvIfEmpty();
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_settings');
    }

    private function seedFromEnvIfEmpty(): void
    {
        $host = trim((string) env('MAIL_HOST', ''));
        if ($host === '') {
            return;
        }

        $now = now();
        DB::table('smtp_settings')->insert([
            'id' => 'default',
            'enabled' => false,
            'provider_label' => 'Из .env',
            'driver' => (string) env('MAIL_DRIVER', 'smtp'),
            'host' => $host,
            'port' => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION') ?: null,
            'username' => env('MAIL_USERNAME') ?: null,
            'password' => null,
            'from_address' => env('MAIL_FROM_ADDRESS') ?: null,
            'from_name' => env('MAIL_FROM_NAME') ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
