<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTariffExpiringToTriggerDispatches extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('trigger_campaign_dispatches', 'dispatch_key')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->string('dispatch_key', 96)->nullable()->after('user_id');
            });
        }

        if (!Schema::hasColumn('trigger_campaign_dispatches', 'tariff_pay_id')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->unsignedBigInteger('tariff_pay_id')->nullable()->after('promo_code_id');
            });
        }

        DB::table('trigger_campaign_dispatches')
            ->whereNull('dispatch_key')
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('trigger_campaign_dispatches')
                        ->where('id', $row->id)
                        ->update(['dispatch_key' => 'user:' . $row->user_id]);
                }
            });

        if (!$this->indexExists('trigger_campaign_dispatches', 'trigger_campaign_dispatches_user_id_index')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->index('user_id', 'trigger_campaign_dispatches_user_id_index');
            });
        }

        if (!$this->indexExists('trigger_campaign_dispatches', 'trigger_campaign_dispatches_campaign_id_index')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->index('trigger_campaign_id', 'trigger_campaign_dispatches_campaign_id_index');
            });
        }

        if (!$this->foreignKeyExists('trigger_campaign_dispatches', 'trigger_campaign_dispatches_tariff_pay_id_foreign')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->foreign('tariff_pay_id')
                    ->references('id')
                    ->on('tariff_pays')
                    ->onDelete('set null');
            });
        }

        if ($this->foreignKeyExists('trigger_campaign_dispatches', 'trigger_campaign_dispatches_promo_code_id_foreign')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->dropForeign(['promo_code_id']);
            });
        }

        DB::statement('ALTER TABLE trigger_campaign_dispatches MODIFY promo_code_id BIGINT UNSIGNED NULL');

        if (!$this->foreignKeyExists('trigger_campaign_dispatches', 'trigger_campaign_dispatches_promo_code_id_foreign')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->foreign('promo_code_id')
                    ->references('id')
                    ->on('promo_codes')
                    ->onDelete('set null');
            });
        }

        if ($this->indexExists('trigger_campaign_dispatches', 'trigger_campaign_user_test_unique')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->dropUnique('trigger_campaign_user_test_unique');
            });
        }

        if (!$this->indexExists('trigger_campaign_dispatches', 'trigger_campaign_dispatch_key_unique')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->unique(['trigger_campaign_id', 'dispatch_key', 'is_test'], 'trigger_campaign_dispatch_key_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('trigger_campaign_dispatches', 'trigger_campaign_dispatch_key_unique')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->dropUnique('trigger_campaign_dispatch_key_unique');
            });
        }

        if (!$this->indexExists('trigger_campaign_dispatches', 'trigger_campaign_user_test_unique')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->unique(['trigger_campaign_id', 'user_id', 'is_test'], 'trigger_campaign_user_test_unique');
            });
        }

        if ($this->foreignKeyExists('trigger_campaign_dispatches', 'trigger_campaign_dispatches_tariff_pay_id_foreign')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->dropForeign(['tariff_pay_id']);
            });
        }

        if ($this->foreignKeyExists('trigger_campaign_dispatches', 'trigger_campaign_dispatches_promo_code_id_foreign')) {
            Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
                $table->dropForeign(['promo_code_id']);
            });
        }

        DB::statement('ALTER TABLE trigger_campaign_dispatches MODIFY promo_code_id BIGINT UNSIGNED NOT NULL');

        Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
            $table->foreign('promo_code_id')
                ->references('id')
                ->on('promo_codes')
                ->onDelete('cascade');
        });

        Schema::table('trigger_campaign_dispatches', function (Blueprint $table) {
            if (Schema::hasColumn('trigger_campaign_dispatches', 'dispatch_key')) {
                $table->dropColumn('dispatch_key');
            }
            if (Schema::hasColumn('trigger_campaign_dispatches', 'tariff_pay_id')) {
                $table->dropColumn('tariff_pay_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?',
            [$index]
        );

        return count($rows) > 0;
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $database = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $foreignKey, 'FOREIGN KEY']
        );

        return count($rows) > 0;
    }
}
