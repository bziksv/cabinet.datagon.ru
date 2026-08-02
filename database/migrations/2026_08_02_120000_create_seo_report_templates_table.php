<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSeoReportTemplatesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('seo_report_templates')) {
            Schema::create('seo_report_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('title');
                $table->boolean('is_default')->default(false)->index();
                $table->string('agency_name')->nullable();
                $table->string('agency_address')->nullable();
                $table->string('agency_email')->nullable();
                $table->string('agency_phone', 64)->nullable();
                $table->string('agency_logo_path')->nullable();
                $table->string('brand_color', 16)->nullable();
                $table->string('manager_name')->nullable();
                $table->string('manager_phone', 64)->nullable();
                $table->string('manager_email')->nullable();
                $table->string('manager_avatar_path')->nullable();
                $table->json('section_toggles')->nullable();
                $table->json('settings_json')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('seo_report_projects') && !Schema::hasColumn('seo_report_projects', 'template_id')) {
            Schema::table('seo_report_projects', function (Blueprint $table) {
                $table->unsignedBigInteger('template_id')->nullable()->after('user_id')->index();
            });
        }

        if (!Schema::hasTable('seo_report_projects') || !Schema::hasTable('seo_report_templates')) {
            return;
        }

        $now = now();
        $projects = DB::table('seo_report_projects')->orderBy('id')->get();
        $byUser = [];
        foreach ($projects as $project) {
            $uid = (int) $project->user_id;
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = [];
            }
            $byUser[$uid][] = $project;
        }

        foreach ($byUser as $userId => $rows) {
            $defaultId = null;
            foreach ($rows as $project) {
                $settings = json_decode((string) ($project->settings_json ?: '{}'), true);
                if (!is_array($settings)) {
                    $settings = [];
                }
                $templateSettings = [
                    'default_period' => $settings['default_period'] ?? 'prev_month',
                    'auto_compare' => !empty($settings['auto_compare']),
                    'traffic_mode' => ($settings['traffic_mode'] ?? 'all') === 'search_only' ? 'search_only' : 'all',
                    'kpi_goals' => $settings['kpi_goals'] ?? [],
                    'section_order' => $settings['section_order'] ?? [],
                    'auto_generate' => !empty($settings['auto_generate']),
                    'remind_missing' => !empty($settings['remind_missing']),
                    'confirmed_sources_only' => !empty($settings['confirmed_sources_only']),
                    'public_dark_theme' => !empty($settings['public_dark_theme']),
                    'enable_ai_summary' => !empty($settings['enable_ai_summary']),
                ];

                $isDefault = !empty($settings['agency_default_template']) || $defaultId === null;
                $title = !empty($settings['agency_default_template'])
                    ? 'Шаблон агентства'
                    : ('Шаблон · ' . (string) $project->domain);

                $templateId = DB::table('seo_report_templates')->insertGetId([
                    'user_id' => $userId,
                    'title' => $title,
                    'is_default' => $isDefault ? 1 : 0,
                    'agency_name' => $project->agency_name,
                    'agency_address' => $project->agency_address,
                    'agency_email' => $project->agency_email,
                    'agency_phone' => $project->agency_phone,
                    'agency_logo_path' => $project->agency_logo_path,
                    'brand_color' => $project->brand_color,
                    'manager_name' => $project->manager_name,
                    'manager_phone' => $project->manager_phone,
                    'manager_email' => $project->manager_email,
                    'manager_avatar_path' => $project->manager_avatar_path ?? null,
                    'section_toggles' => $project->section_toggles,
                    'settings_json' => json_encode($templateSettings, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($isDefault) {
                    if ($defaultId !== null) {
                        DB::table('seo_report_templates')->where('id', $defaultId)->update(['is_default' => 0]);
                    }
                    $defaultId = $templateId;
                }

                DB::table('seo_report_projects')->where('id', $project->id)->update([
                    'template_id' => $templateId,
                ]);
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('seo_report_projects') && Schema::hasColumn('seo_report_projects', 'template_id')) {
            Schema::table('seo_report_projects', function (Blueprint $table) {
                $table->dropColumn('template_id');
            });
        }
        Schema::dropIfExists('seo_report_templates');
    }
}
