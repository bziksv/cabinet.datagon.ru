<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReportBrandColor;
use App\SeoReports\SeoReportKpiGoals;
use App\SeoReports\SeoReportMetricRegistry;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use App\SeoReports\SeoReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SeoReportTemplateService
{
    public const DEFAULT_TITLE = 'Основной шаблон';

    public function ensureDefaultForUser(int $userId): ?SeoReportTemplate
    {
        if (!Schema::hasTable('seo_report_templates')) {
            return null;
        }

        $existing = SeoReportTemplate::query()
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->orderBy('id')
            ->first();
        if ($existing) {
            $this->normalizeDefaultTemplate($existing);

            return $existing->fresh();
        }

        $any = SeoReportTemplate::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->first();
        if ($any) {
            $this->clearDefaultFlag($userId);
            $any->is_default = true;
            $any->save();
            $this->normalizeDefaultTemplate($any);

            return $any->fresh();
        }

        return $this->createFromPreset($userId, 'complex', self::DEFAULT_TITLE, true);
    }

    /**
     * Default template = full catalog. Migrated per-project stubs get renamed + seeded once.
     */
    public function normalizeDefaultTemplate(SeoReportTemplate $template): void
    {
        if (!$template->is_default) {
            return;
        }

        $settings = $template->reportSettings();
        $dirty = false;
        $title = trim((string) $template->title);
        if ($title === ''
            || preg_match('/^Шаблон\s*·/u', $title)
            || $title === 'Шаблон агентства'
            || $title === 'Основной шаблон'
        ) {
            if ($title !== self::DEFAULT_TITLE) {
                $template->title = self::DEFAULT_TITLE;
                $dirty = true;
            }
        }

        if (empty($settings['full_catalog_seeded'])) {
            $template->section_toggles = SeoReportSectionRegistry::togglesForPreset('complex');
            $settings['section_order'] = SeoReportSectionRegistry::defaultOrder();
            $settings['metric_toggles'] = SeoReportMetricRegistry::defaults();
            $settings['full_catalog_seeded'] = true;
            if (empty($settings['description'])) {
                $settings['description'] = 'Базовый шаблон со всеми блоками. Подключайте к проектам или копируйте для особых клиентов.';
            }
            $template->settings_json = $settings;
            $dirty = true;
        } elseif (empty($settings['metric_toggles'])) {
            $settings['metric_toggles'] = SeoReportMetricRegistry::defaults();
            $template->settings_json = $settings;
            $dirty = true;
        }

        if ($dirty) {
            $template->save();
        }
    }

    public function createFromPreset(int $userId, string $preset, ?string $title = null, bool $isDefault = false): SeoReportTemplate
    {
        if ($isDefault) {
            $this->clearDefaultFlag($userId);
        }

        if (!in_array($preset, ['seo_only', 'seo_ads', 'complex', 'full', 'mvp'], true)) {
            $preset = 'complex';
        }
        if ($preset === 'mvp') {
            $preset = 'seo_only';
        }
        if ($preset === 'full') {
            $preset = 'complex';
        }

        $settings = [
            'default_period' => 'prev_month',
            'auto_compare' => true,
            'traffic_mode' => 'all',
            'kpi_goals' => SeoReportKpiGoals::normalizeInput([]),
            'section_order' => SeoReportSectionRegistry::defaultOrder(),
            'metric_toggles' => SeoReportMetricRegistry::defaults(),
            'auto_generate' => false,
            'remind_missing' => false,
            'confirmed_sources_only' => false,
            'public_dark_theme' => false,
            'enable_ai_summary' => false,
        ];
        if ($isDefault || $preset === 'complex') {
            $settings['full_catalog_seeded'] = true;
            if ($isDefault) {
                $settings['description'] = 'Базовый шаблон со всеми блоками. Подключайте к проектам или копируйте для особых клиентов.';
            }
        }

        return SeoReportTemplate::query()->create([
            'user_id' => $userId,
            'title' => $title ?: ($isDefault ? self::DEFAULT_TITLE : $this->presetTitle($preset)),
            'is_default' => $isDefault,
            'section_toggles' => SeoReportSectionRegistry::togglesForPreset($preset),
            'settings_json' => $settings,
            'brand_color' => '#1d4ed8',
        ]);
    }

    public function duplicate(SeoReportTemplate $source, ?string $title = null): SeoReportTemplate
    {
        $copy = $source->replicate(['is_default']);
        $copy->title = $title ?: ($source->title . ' (копия)');
        $copy->is_default = false;

        if ($source->agency_logo_path) {
            $copy->agency_logo_path = $this->copyPublicFile($source->agency_logo_path, 'seo-report-templates/tmp');
        }
        if ($source->manager_avatar_path) {
            $copy->manager_avatar_path = $this->copyPublicFile($source->manager_avatar_path, 'seo-report-templates/tmp');
        }
        $copy->save();

        if ($copy->agency_logo_path || $copy->manager_avatar_path) {
            $this->relocateAssets($copy);
        }

        return $copy->fresh();
    }

    /**
     * Apply request fields to a template (report shape + branding).
     */
    public function applyRequest(SeoReportTemplate $template, Request $request): void
    {
        $catalog = SeoReportSectionRegistry::all();
        $toggles = $template->resolvedSectionToggles();
        $posted = $request->input('sections', []);
        if (!is_array($posted)) {
            $posted = [];
        }
        foreach ($catalog as $key => $_meta) {
            $toggles[$key] = !empty($posted[$key]);
        }

        $settings = $template->reportSettings();
        $settings['description'] = trim((string) $request->input('description', '')) ?: null;
        $settings['default_period'] = in_array($request->input('default_period'), ['prev_month', 'last_30', 'custom'], true)
            ? (string) $request->input('default_period')
            : 'prev_month';
        $settings['auto_compare'] = $request->boolean('auto_compare');
        $settings['traffic_mode'] = $request->input('traffic_mode') === 'search_only' ? 'search_only' : 'all';
        $settings['kpi_goals'] = SeoReportKpiGoals::normalizeInput($request->input('kpi_goals'));
        $order = $request->input('section_order', []);
        if (is_array($order) && $order !== []) {
            $settings['section_order'] = SeoReportSectionRegistry::orderedKeys(['section_order' => $order]);
        }
        $settings['metric_toggles'] = $this->mergeMetricToggles(
            $settings['metric_toggles'] ?? null,
            $request->input('metric_toggles')
        );
        $settings['auto_generate'] = $request->boolean('auto_generate');
        $settings['remind_missing'] = $request->boolean('remind_missing');
        $settings['confirmed_sources_only'] = $request->boolean('confirmed_sources_only');
        $settings['public_dark_theme'] = $request->boolean('public_dark_theme');
        $settings['enable_ai_summary'] = $request->boolean('enable_ai_summary');

        $brandRaw = trim((string) $request->input('brand_color', ''));
        $brandColor = $brandRaw !== '' ? SeoReportBrandColor::normalize($brandRaw) : null;

        $makeDefault = $request->boolean('is_default');
        if ($makeDefault) {
            $this->clearDefaultFlag((int) $template->user_id, (int) $template->id);
        }

        $template->fill([
            'title' => trim((string) $request->input('title', '')) ?: $template->title,
            'is_default' => $makeDefault,
            'agency_name' => trim((string) $request->input('agency_name', '')) ?: null,
            'agency_address' => trim((string) $request->input('agency_address', '')) ?: null,
            'agency_email' => trim((string) $request->input('agency_email', '')) ?: null,
            'agency_phone' => trim((string) $request->input('agency_phone', '')) ?: null,
            'brand_color' => $brandColor,
            'manager_name' => trim((string) $request->input('manager_name', '')) ?: null,
            'manager_phone' => trim((string) $request->input('manager_phone', '')) ?: null,
            'manager_email' => trim((string) $request->input('manager_email', '')) ?: null,
            'section_toggles' => $toggles,
            'settings_json' => $settings,
        ]);

        if ($request->boolean('clear_agency_logo')) {
            $this->deletePublicFile($template->agency_logo_path);
            $template->agency_logo_path = null;
        } elseif ($request->hasFile('agency_logo')) {
            $request->validate(['agency_logo' => 'file|mimes:png,jpg,jpeg,webp,gif,svg|max:1024']);
            $this->deletePublicFile($template->agency_logo_path);
            $path = $request->file('agency_logo')->storeAs(
                'seo-report-templates/' . ($template->id ?: 'new'),
                'agency-logo.' . $request->file('agency_logo')->getClientOriginalExtension(),
                'public'
            );
            $template->agency_logo_path = $path ?: null;
        }

        if ($request->boolean('clear_manager_avatar')) {
            $this->deletePublicFile($template->manager_avatar_path);
            $template->manager_avatar_path = null;
        } elseif ($request->hasFile('manager_avatar')) {
            $request->validate(['manager_avatar' => 'file|mimes:png,jpg,jpeg,webp,gif|max:1024']);
            $this->deletePublicFile($template->manager_avatar_path);
            $path = $request->file('manager_avatar')->storeAs(
                'seo-report-templates/' . ($template->id ?: 'new'),
                'manager-avatar.' . $request->file('manager_avatar')->getClientOriginalExtension(),
                'public'
            );
            $template->manager_avatar_path = $path ?: null;
        }

        $template->save();
        $this->relocateAssets($template);

        // Always keep exactly one default for the user.
        if (!$template->is_default) {
            $this->ensureDefaultForUser((int) $template->user_id);
        }
    }

    public function clearDefaultFlag(int $userId, ?int $exceptId = null): void
    {
        $q = SeoReportTemplate::query()->where('user_id', $userId)->where('is_default', true);
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }
        $q->update(['is_default' => false]);
    }

    /**
     * @param mixed $existing
     * @param mixed $posted
     * @return array<string, array<string, bool>>
     */
    private function mergeMetricToggles($existing, $posted): array
    {
        $defaults = SeoReportMetricRegistry::defaults();
        $existing = is_array($existing) ? $existing : [];
        $posted = is_array($posted) ? $posted : [];

        foreach ($defaults as $section => $metrics) {
            // Posted only when section is currently selected in the builder.
            $src = array_key_exists($section, $posted) && is_array($posted[$section])
                ? $posted[$section]
                : (is_array($existing[$section] ?? null) ? $existing[$section] : []);
            foreach ($metrics as $key => $_on) {
                if (array_key_exists($key, $src)) {
                    $defaults[$section][$key] = !empty($src[$key]) && $src[$key] !== '0' && $src[$key] !== 0;
                }
            }
        }

        return $defaults;
    }

    private function presetTitle(string $preset): string
    {
        foreach (SeoReportSectionRegistry::presetCards() as $card) {
            if (($card['key'] ?? '') === $preset) {
                return (string) $card['title'];
            }
        }

        return 'Шаблон отчёта';
    }

    private function copyPublicFile(string $path, string $dir): ?string
    {
        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }
            $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
            $dest = trim($dir, '/') . '/' . uniqid('asset_', true) . '.' . $ext;
            Storage::disk('public')->copy($path, $dest);

            return $dest;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function relocateAssets(SeoReportTemplate $template): void
    {
        $dir = 'seo-report-templates/' . $template->id;
        $changed = false;
        foreach (['agency_logo_path', 'manager_avatar_path'] as $field) {
            $path = (string) ($template->{$field} ?? '');
            if ($path === '' || strpos($path, $dir . '/') === 0) {
                continue;
            }
            try {
                if (!Storage::disk('public')->exists($path)) {
                    continue;
                }
                $base = basename($path);
                $dest = $dir . '/' . $base;
                Storage::disk('public')->copy($path, $dest);
                Storage::disk('public')->delete($path);
                $template->{$field} = $dest;
                $changed = true;
            } catch (\Throwable $e) {
                // keep old path
            }
        }
        if ($changed) {
            $template->save();
        }
    }

    private function deletePublicFile(?string $path): void
    {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
