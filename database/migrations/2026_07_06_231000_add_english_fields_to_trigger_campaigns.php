<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddEnglishFieldsToTriggerCampaigns extends Migration
{
    public function up(): void
    {
        Schema::table('trigger_campaigns', function (Blueprint $table) {
            $table->string('email_subject_en')->nullable()->after('email_subject');
            $table->text('email_intro_en')->nullable()->after('email_intro');
            $table->text('email_body_en')->nullable()->after('email_body');
        });

        DB::table('trigger_campaigns')
            ->where('slug', 'inactive_180_days')
            ->update([
                'email_subject_en' => 'We miss you — 500 ₽ gift for Titlo',
                'email_intro_en' => 'It has been a while! Sign in to your cabinet — we prepared a personal promo code for you.',
                'email_body_en' => "Titlo has new tools: position monitoring, competitor analysis, and balance top-ups with bonuses.\n\nYour personal promo code is below — enter it on the Balance page and funds will be credited immediately, with no payment required.",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('trigger_campaigns', function (Blueprint $table) {
            $table->dropColumn(['email_subject_en', 'email_intro_en', 'email_body_en']);
        });
    }
}
