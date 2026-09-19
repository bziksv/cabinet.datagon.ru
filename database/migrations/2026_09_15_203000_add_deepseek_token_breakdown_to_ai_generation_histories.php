<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeepseekTokenBreakdownToAiGenerationHistories extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ai_generation_histories')) {
            return;
        }

        Schema::table('ai_generation_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_generation_histories', 'prompt_tokens')) {
                $table->unsignedInteger('prompt_tokens')->default(0)->after('used_tokens');
            }
            if (!Schema::hasColumn('ai_generation_histories', 'completion_tokens')) {
                $table->unsignedInteger('completion_tokens')->default(0)->after('prompt_tokens');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('ai_generation_histories')) {
            return;
        }

        Schema::table('ai_generation_histories', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('ai_generation_histories', 'completion_tokens')) {
                $cols[] = 'completion_tokens';
            }
            if (Schema::hasColumn('ai_generation_histories', 'prompt_tokens')) {
                $cols[] = 'prompt_tokens';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
}
