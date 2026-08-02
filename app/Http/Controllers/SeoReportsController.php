<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSeoReportJob;
use App\Mail\SeoReportProjectShareMail;
use App\Mail\SeoReportShareMail;
use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportBindings;
use App\SeoReports\SeoReportBrandColor;
use App\SeoReports\SeoReportKpiGoals;
use App\SeoReports\SeoReportPhraseLibrary;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use App\SeoReports\SeoReportTemplate;
use App\User;
use Illuminate\Support\Str;
use App\Services\SeoReports\SeoReportExportService;
use App\Services\SeoReports\SeoReportExternalAdsCollector;
use App\Services\SeoReports\SeoReportPresetDemoFactory;
use App\Services\SeoReports\SeoReportTemplateService;
use App\Services\YandexMetrika\YandexMetrikaService;
use App\Support\HomeUserSites;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SeoReportsController extends Controller
{
    /** @var SeoReportExportService */
    private $export;

    /** @var YandexMetrikaService */
    private $metrika;

    public function __construct(SeoReportExportService $export, YandexMetrikaService $metrika)
    {
        $this->export = $export;
        $this->metrika = $metrika;
    }

    /**
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        $userId = (int) Auth::id();

        $owned = SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->withCount('reports')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $sharedIds = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('seo_report_project_user')) {
            $sharedIds = DB::table('seo_report_project_user')
                ->where('user_id', $userId)
                ->pluck('seo_report_project_id')
                ->all();
        }
        $shared = $sharedIds === []
            ? collect()
            : SeoReportProject::query()
                ->whereIn('id', $sharedIds)
                ->where('status', 'active')
                ->withCount('reports')
                ->orderByDesc('updated_at')
                ->get();

        $projects = $owned->concat($shared)->unique('id')->values();

        $archived = SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('status', 'archived')
            ->withCount('reports')
            ->orderByDesc('updated_at')
            ->get();

        $ownDomains = $owned->pluck('domain')->all();
        $availableDomains = $this->availableDomains($userId, $ownDomains);

        $metrikaByDomain = [];
        foreach (SeoReportBindings::metrikaBindingsForUser($userId) as $binding) {
            $metrikaByDomain[(string) $binding->domain] = $binding;
        }
        $monitoringById = [];
        foreach (SeoReportBindings::monitoringOptionsForUser($userId) as $option) {
            $monitoringById[(int) $option['id']] = $option;
        }

        $domainHints = [];
        foreach ($availableDomains as $domain) {
            $metrikaId = SeoReportBindings::resolveMetrikaCounterId($userId, $domain);
            $monitoringId = SeoReportBindings::resolveMonitoringProjectId($userId, $domain);
            $metrikaLabel = '';
            if ($metrikaId) {
                $binding = $metrikaByDomain[$domain] ?? null;
                $counterName = $binding ? trim((string) ($binding->counter_name ?? '')) : '';
                $metrikaLabel = $counterName !== ''
                    ? $counterName . ' · ' . $domain
                    : $domain;
                $metrikaLabel .= ' · #' . $metrikaId;
            }
            $monitoringLabel = '';
            if ($monitoringId && isset($monitoringById[$monitoringId])) {
                $monitoringLabel = (string) $monitoringById[$monitoringId]['label'];
            } elseif ($monitoringId) {
                $monitoringLabel = $domain;
            }
            $domainHints[$domain] = [
                'metrika' => $metrikaId,
                'metrika_label' => $metrikaLabel,
                'monitoring' => $monitoringId,
                'monitoring_label' => $monitoringLabel,
            ];
        }

        $missingMonth = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $missingTo = $missingMonth->copy()->endOfMonth()->startOfDay();
        $missingReports = [];
        foreach ($projects as $project) {
            $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
            if (empty($settings['remind_missing'])) {
                continue;
            }
            $has = SeoReport::query()
                ->where('project_id', $project->id)
                ->whereDate('period_from', $missingMonth->toDateString())
                ->whereDate('period_to', $missingTo->toDateString())
                ->whereNull('archived_from_report_id')
                ->whereIn('status', [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED])
                ->exists();
            if (!$has) {
                $missingReports[] = $project;
            }
        }

        $reportTemplates = [];
        if (Schema::hasTable('seo_report_templates')) {
            app(SeoReportTemplateService::class)->ensureDefaultForUser($userId);
            $reportTemplates = SeoReportTemplate::query()
                ->where('user_id', $userId)
                ->orderByDesc('is_default')
                ->orderBy('title')
                ->orderBy('id')
                ->get();
        }

        return view('pages.seo-reports', [
            'projects' => $projects,
            'archived' => $archived,
            'availableDomains' => $availableDomains,
            'domainHints' => $domainHints,
            'sectionCatalog' => SeoReportSectionRegistry::mvpSections(),
            'kpiGoalTypes' => SeoReportKpiGoals::TYPES,
            'missingReports' => $missingReports,
            'missingMonthLabel' => $missingMonth->format('Y-m'),
            'sharedProjectIds' => $shared->pluck('id')->all(),
            'presetCards' => SeoReportSectionRegistry::presetCards(),
            'reportTemplates' => $reportTemplates,
        ]);
    }

    public function templates(SeoReportTemplateService $templates)
    {
        $userId = (int) Auth::id();
        $templates->ensureDefaultForUser($userId);

        $list = SeoReportTemplate::query()
            ->where('user_id', $userId)
            ->withCount('projects')
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        $ownedCount = SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->count();

        return view('pages.seo-reports-templates', [
            'templates' => $list,
            'presets' => SeoReportSectionRegistry::presetCards(),
            'projectsCount' => $ownedCount,
        ]);
    }

    public function storeTemplate(Request $request, SeoReportTemplateService $templates): RedirectResponse
    {
        $userId = (int) Auth::id();
        // Extra templates are never the default — default is ensured separately with full catalog.
        $templates->ensureDefaultForUser($userId);
        $preset = (string) $request->input('preset', 'complex');
        if (!in_array($preset, ['seo_only', 'seo_ads', 'complex'], true)) {
            $preset = 'complex';
        }
        $title = trim((string) $request->input('title', '')) ?: null;
        $template = $templates->createFromPreset($userId, $preset, $title, false);

        return redirect()
            ->route('pages.seo-reports.templates.edit', ['id' => $template->id])
            ->with('success', __('Report template created'));
    }

    /**
     * @return View|RedirectResponse
     */
    public function editTemplate(int $id)
    {
        $template = $this->findOwnedTemplate($id);
        if (!$template) {
            return redirect()->route('pages.seo-reports.templates')->with('error', __('Template not found'));
        }

        $settings = $template->reportSettings();

        return view('pages.seo-reports-template-edit', [
            'template' => $template,
            'toggles' => $template->resolvedSectionToggles(),
            'sectionCatalog' => SeoReportSectionRegistry::all(),
            'settings' => $settings,
            'kpiGoals' => SeoReportKpiGoals::fromSettings($settings),
            'projectsCount' => $template->projects()->count(),
        ]);
    }

    public function updateTemplate(Request $request, int $id, SeoReportTemplateService $templates): RedirectResponse
    {
        $template = $this->findOwnedTemplate($id);
        if (!$template) {
            return redirect()->route('pages.seo-reports.templates')->with('error', __('Template not found'));
        }

        $templates->applyRequest($template, $request);

        return redirect()
            ->route('pages.seo-reports.templates.edit', ['id' => $template->id])
            ->with('success', __('Saved'));
    }

    public function duplicateTemplate(int $id, SeoReportTemplateService $templates): RedirectResponse
    {
        $template = $this->findOwnedTemplate($id);
        if (!$template) {
            return redirect()->route('pages.seo-reports.templates')->with('error', __('Template not found'));
        }

        $copy = $templates->duplicate($template);

        return redirect()
            ->route('pages.seo-reports.templates.edit', ['id' => $copy->id])
            ->with('success', __('Report template copied'));
    }

    public function destroyTemplate(int $id, SeoReportTemplateService $templates): RedirectResponse
    {
        $template = $this->findOwnedTemplate($id);
        if (!$template) {
            return redirect()->route('pages.seo-reports.templates')->with('error', __('Template not found'));
        }

        $userId = (int) Auth::id();
        $linked = $template->projects()->count();
        if ($linked > 0) {
            return redirect()
                ->route('pages.seo-reports.templates')
                ->with('error', __('Cannot delete template used by projects'));
        }

        $wasDefault = (bool) $template->is_default;
        $template->delete();
        if ($wasDefault) {
            $templates->ensureDefaultForUser($userId);
        }

        return redirect()
            ->route('pages.seo-reports.templates')
            ->with('success', __('Deleted'));
    }

    public function store(Request $request, SeoReportTemplateService $templates): RedirectResponse
    {
        $userId = (int) Auth::id();

        if ($blocked = $this->tariffBlocksNewProject($userId)) {
            return $blocked;
        }

        $domain = HomeUserSites::normalizeDomain((string) $request->input('domain', ''));
        if ($domain === '') {
            return redirect()
                ->route('pages.seo-reports')
                ->with('error', __('Enter a domain'));
        }

        $exists = SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->exists();
        if ($exists) {
            return redirect()
                ->route('pages.seo-reports')
                ->with('error', __('SEO report project already exists'));
        }

        $reportTemplate = null;
        $templateId = (int) $request->input('template_id', 0);
        $forceNewTemplate = $request->boolean('force_new_template');

        if (!$forceNewTemplate && $templateId > 0) {
            $reportTemplate = SeoReportTemplate::query()
                ->where('user_id', $userId)
                ->where('id', $templateId)
                ->first();
        }

        if (!$reportTemplate) {
            $preset = (string) $request->input('template', 'seo_only');
            if (!in_array($preset, ['mvp', 'seo_only', 'seo_ads', 'complex', 'full'], true)) {
                $preset = 'seo_only';
            }
            if ($preset === 'mvp') {
                $preset = 'seo_only';
            }
            if ($preset === 'full') {
                $preset = 'complex';
            }

            if ($forceNewTemplate) {
                $reportTemplate = $templates->createFromPreset(
                    $userId,
                    $preset,
                    'Шаблон · ' . $domain,
                    false
                );
            } else {
                $reportTemplate = $templates->ensureDefaultForUser($userId);
            }
        }

        // Project-only settings (integrations / delivery); report shape lives on template.
        $projectSettings = [
            'metrika_goal_ids' => [],
            'auto_email' => false,
            'auto_email_to' => null,
            'auto_email_message' => null,
            'auto_email_cc_manager' => false,
            'mirror_domains' => [],
        ];

        $project = SeoReportProject::query()->create([
            'user_id' => $userId,
            'template_id' => $reportTemplate ? $reportTemplate->id : null,
            'domain' => $domain,
            'title' => trim((string) $request->input('title', '')) ?: null,
            'status' => 'active',
            'section_toggles' => null,
            'settings_json' => $projectSettings,
        ]);

        SeoReportBindings::applyAutoBindings($project);

        if ($request->filled('metrika_counter_id')) {
            $project->metrika_counter_id = (int) $request->input('metrika_counter_id') ?: null;
        }
        if ($request->filled('monitoring_project_id')) {
            $project->monitoring_project_id = (int) $request->input('monitoring_project_id') ?: null;
        }
        $project->save();

        return redirect()
            ->route('pages.seo-reports.show', ['id' => $project->id])
            ->with('success', __('SEO report project created'));
    }

    /**
     * @return View|RedirectResponse
     */
    public function show(int $id)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $userId = (int) Auth::id();
        $canEdit = $project->canEditBy($userId);
        $isOwner = $project->isOwnedBy($userId);

        $reports = SeoReport::query()
            ->where('project_id', $project->id)
            ->orderByDesc('period_to')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $toggles = $project->resolvedSectionToggles();
        $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
        $catalog = SeoReportSectionRegistry::all();
        $sections = [];
        foreach (SeoReportSectionRegistry::orderedKeys($settings) as $key) {
            $meta = $catalog[$key] ?? null;
            if (!$meta) {
                continue;
            }
            $enabled = !empty($toggles[$key]);
            $sourceStatus = $this->guessSourceStatus($project, (string) $meta['source']);
            $sections[] = [
                'key' => $key,
                'title' => $meta['title'],
                'group' => $meta['group'],
                'source' => $meta['source'],
                'enabled' => $enabled,
                'source_status' => $sourceStatus,
                'client_visible' => SeoReportSectionRegistry::visibleForClient($enabled, $sourceStatus),
                'mvp' => !empty($meta['mvp']),
            ];
        }

        return view('pages.seo-reports-show', [
            'project' => $project,
            'reports' => $reports,
            'sections' => $sections,
            'generationWarnings' => $this->generationWarnings($project),
            'canEdit' => $canEdit,
            'isOwner' => $isOwner,
            'shareRole' => $project->shareRoleFor($userId),
            'sharedUsers' => ($isOwner && \Illuminate\Support\Facades\Schema::hasTable('seo_report_project_user'))
                ? $project->sharedUsers()->orderBy('email')->get()
                : collect(),
        ]);
    }

    /**
     * @return View|RedirectResponse
     */
    public function settings(int $id, SeoReportTemplateService $templates)
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $userId = (int) Auth::id();
        $templates->ensureDefaultForUser($userId);
        if (!$project->template_id) {
            $default = $templates->ensureDefaultForUser($userId);
            if ($default) {
                $project->template_id = $default->id;
                $project->save();
            }
        }

        $settings = is_array($project->settings_json) ? $project->settings_json : [];
        $goals = [];
        if ($project->metrika_counter_id && $this->metrika->isConnected($userId)) {
            $goals = $this->metrika->listGoals($userId, (int) $project->metrika_counter_id) ?: [];
        }

        $templateList = SeoReportTemplate::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        return view('pages.seo-reports-settings', [
            'project' => $project,
            'settings' => $settings,
            'templates' => $templateList,
            'attachedTemplate' => $project->resolvedTemplate(),
            'metrikaBindings' => SeoReportBindings::metrikaBindingsForUser($userId),
            'monitoringOptions' => SeoReportBindings::monitoringOptionsForUser($userId),
            'metrikaGoals' => $goals,
            'selectedGoalIds' => isset($settings['metrika_goal_ids']) && is_array($settings['metrika_goal_ids'])
                ? array_map('intval', $settings['metrika_goal_ids'])
                : [],
        ]);
    }

    public function updateSettings(Request $request, int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $userId = (int) Auth::id();
        $templateId = (int) $request->input('template_id', 0);
        $template = $templateId > 0
            ? SeoReportTemplate::query()->where('user_id', $userId)->where('id', $templateId)->first()
            : null;

        $settings = is_array($project->settings_json) ? $project->settings_json : [];
        $goalIds = $request->input('metrika_goal_ids', []);
        $settings['metrika_goal_ids'] = is_array($goalIds)
            ? array_values(array_filter(array_map('intval', $goalIds)))
            : [];
        $settings['gsc_property'] = trim((string) $request->input('gsc_property', '')) ?: null;
        $settings['webmaster_host'] = trim((string) $request->input('webmaster_host', '')) ?: null;
        $settings['auto_email'] = $request->boolean('auto_email');
        $settings['auto_email_to'] = trim((string) $request->input('auto_email_to', '')) ?: null;
        $settings['auto_email_message'] = trim((string) $request->input('auto_email_message', '')) ?: null;
        $settings['auto_email_cc_manager'] = $request->boolean('auto_email_cc_manager');
        $settings['vk_ads_token'] = trim((string) $request->input('vk_ads_token', '')) ?: null;
        $settings['vk_ads_account'] = trim((string) $request->input('vk_ads_account', '')) ?: null;
        $settings['meta_ads_token'] = trim((string) $request->input('meta_ads_token', '')) ?: null;
        $settings['meta_ads_account'] = trim((string) $request->input('meta_ads_account', '')) ?: null;
        $settings['vk_smm_token'] = trim((string) $request->input('vk_smm_token', '')) ?: null;
        $settings['vk_smm_group_id'] = trim((string) $request->input('vk_smm_group_id', '')) ?: null;
        $mirrors = preg_split('/[\s,;]+/', (string) $request->input('mirror_domains', '')) ?: [];
        $settings['mirror_domains'] = array_values(array_filter(array_map(static function ($d) {
            $d = HomeUserSites::normalizeDomain((string) $d);

            return $d !== '' ? $d : null;
        }, $mirrors)));

        // Drop legacy template keys from project settings (live on template now).
        foreach (SeoReportProject::TEMPLATE_SETTING_KEYS as $key) {
            unset($settings[$key]);
        }
        unset($settings['agency_default_template']);

        $project->fill([
            'title' => trim((string) $request->input('title', '')) ?: null,
            'template_id' => $template ? $template->id : $project->template_id,
            'metrika_counter_id' => $request->filled('metrika_counter_id') ? (int) $request->input('metrika_counter_id') : null,
            'monitoring_project_id' => $request->filled('monitoring_project_id') ? (int) $request->input('monitoring_project_id') : null,
            'settings_json' => $settings,
        ]);
        $project->save();

        return redirect()
            ->route('pages.seo-reports.settings', ['id' => $project->id])
            ->with('success', __('Saved'));
    }

    public function storeReport(Request $request, int $id): RedirectResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        if ($deny = $this->denyReadOnly($project)) {
            return $deny;
        }
        if ($blocked = $this->tariffBlocksGeneration((int) Auth::id())) {
            return $blocked;
        }

        $preset = (string) $request->input('period_preset', 'prev_month');
        [$from, $to, $cFrom, $cTo] = $this->resolvePeriod($preset, $request);

        $report = SeoReport::query()->create([
            'project_id' => $project->id,
            'user_id' => (int) Auth::id(),
            'status' => SeoReport::STATUS_GENERATING,
            'period_from' => $from,
            'period_to' => $to,
            'compare_from' => $cFrom,
            'compare_to' => $cTo,
            'section_states' => $this->buildInitialSectionStates($project),
            'public_pin' => $this->normalizePin($request->input('public_pin')),
        ]);
        $report->ensurePublicToken();
        $project->touch();

        $this->dispatchGenerate($report);

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('SEO report generation started'));
    }

    public function regenerate(int $id, int $reportId): RedirectResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        if ($deny = $this->denyReadOnly($project)) {
            return $deny;
        }
        if ($blocked = $this->tariffBlocksGeneration((int) Auth::id())) {
            return $blocked;
        }

        $report = SeoReport::query()
            ->where('project_id', $project->id)
            ->where('id', $reportId)
            ->first();
        if (!$report) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Report not found'));
        }

        // Keep previous snapshot as an archived version before overwrite.
        if ($this->isReportReady($report) && !empty($report->snapshot_json)) {
            $archive = $report->replicate(['public_token', 'approved_at']);
            $archive->archived_from_report_id = (int) $report->id;
            $archive->public_token = Str::random(40);
            $archive->public_pin = $report->public_pin;
            $archive->status = SeoReport::STATUS_READY;
            $snap = is_array($archive->snapshot_json) ? $archive->snapshot_json : [];
            $snap['version_meta'] = [
                'archived_from' => (int) $report->id,
                'archived_at' => now()->toIso8601String(),
            ];
            $archive->snapshot_json = $snap;
            $archive->save();
        }

        $report->status = SeoReport::STATUS_GENERATING;
        $report->fail_reason = null;
        $report->approved_at = null;
        $report->save();
        $this->dispatchGenerate($report);

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('SEO report generation started'));
    }

    /**
     * @return View|RedirectResponse
     */
    public function compare(Request $request, int $id)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $ready = SeoReport::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED])
            ->whereNull('archived_from_report_id')
            ->orderByDesc('period_to')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $leftId = (int) $request->input('a');
        $rightId = (int) $request->input('b');
        $left = $leftId > 0 ? $ready->firstWhere('id', $leftId) : null;
        $right = $rightId > 0 ? $ready->firstWhere('id', $rightId) : null;
        if (!$left && $ready->count() >= 1) {
            $left = $ready->get(0);
        }
        if (!$right && $ready->count() >= 2) {
            $right = $ready->get(1);
        }

        return view('pages.seo-reports-compare', [
            'project' => $project,
            'readyReports' => $ready,
            'left' => $left,
            'right' => $right,
            'leftSnapshot' => $left && is_array($left->snapshot_json) ? $left->snapshot_json : [],
            'rightSnapshot' => $right && is_array($right->snapshot_json) ? $right->snapshot_json : [],
        ]);
    }

    /**
     * @return BinaryFileResponse|RedirectResponse|\Illuminate\Http\Response
     */
    public function positionsCsv(int $id, int $reportId)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        $report = $this->findReportOrFail($project, $reportId);
        if (!$report || !$this->isReportReady($report)) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $reportId])
                ->with('error', __('Report is not ready yet'));
        }

        $snapshot = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $positions = is_array($snapshot['positions'] ?? null) ? $snapshot['positions'] : [];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['bucket', 'query', 'engine', 'pos_from', 'pos_to', 'delta', 'url']);
        foreach ($positions['phrases']['improved'] ?? [] as $row) {
            fputcsv($fh, [
                'improved',
                $row['query'] ?? '',
                $row['engine'] ?? '',
                $row['pos_from'] ?? '',
                $row['pos_to'] ?? '',
                $row['delta'] ?? '',
                $row['url'] ?? '',
            ]);
        }
        foreach ($positions['phrases']['worsened'] ?? [] as $row) {
            fputcsv($fh, [
                'worsened',
                $row['query'] ?? '',
                $row['engine'] ?? '',
                $row['pos_from'] ?? '',
                $row['pos_to'] ?? '',
                $row['delta'] ?? '',
                $row['url'] ?? '',
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        $filename = 'positions-' . preg_replace('/[^a-z0-9\-\.]+/i', '-', $project->domain)
            . '-' . optional($report->period_to)->format('Y-m') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function reportStatus(int $id, int $reportId): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return response()->json(['ok' => false], 404);
        }

        $report = SeoReport::query()
            ->where('project_id', $project->id)
            ->where('id', $reportId)
            ->first();
        if (!$report) {
            return response()->json(['ok' => false], 404);
        }

        $snapshot = is_array($report->snapshot_json) ? $report->snapshot_json : [];

        return response()->json([
            'ok' => true,
            'status' => $report->status,
            'label' => $report->statusLabel(),
            'fail_reason' => $report->fail_reason,
            'progress' => $snapshot['progress'] ?? new \stdClass(),
            'ready' => in_array($report->status, [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED], true),
            'failed' => $report->status === SeoReport::STATUS_FAILED,
        ]);
    }

    /**
     * @return Response|RedirectResponse
     */
    public function pdf(int $id, int $reportId)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $report = $this->findReportOrFail($project, $reportId);
        if (!$report) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Report not found'));
        }
        if (!$this->isReportReady($report)) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
                ->with('error', __('Report is not ready yet'));
        }

        $pdf = $this->export->makePdf($project, $report);

        return $pdf->download($this->export->pdfFilename($project, $report));
    }

    /**
     * @return BinaryFileResponse|RedirectResponse
     */
    public function downloadPack(int $id, int $reportId)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $report = $this->findReportOrFail($project, $reportId);
        if (!$report || !$this->isReportReady($report)) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $reportId])
                ->with('error', __('Report is not ready yet'));
        }

        try {
            $pack = $this->export->buildPack($project, $report);
        } catch (\Throwable $e) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $reportId])
                ->with('error', __('Could not build report pack'));
        }

        return response()
            ->download($pack['path'], $pack['filename'], ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function sendEmail(Request $request, int $id, int $reportId): RedirectResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        if ($deny = $this->denyReadOnly($project)) {
            return $deny;
        }

        $report = $this->findReportOrFail($project, $reportId);
        if (!$report || !$this->isReportReady($report)) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $reportId])
                ->with('error', __('Report is not ready yet'));
        }

        $emailsRaw = (string) $request->input('emails', '');
        $emails = preg_split('/[\s,;]+/', $emailsRaw) ?: [];
        $emails = array_values(array_unique(array_filter(array_map(static function ($e) {
            $e = trim(mb_strtolower((string) $e));

            return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
        }, $emails))));

        if ($emails === []) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
                ->with('error', __('Enter at least one email'));
        }

        $report->ensurePublicToken();
        $publicUrl = route('seo-reports.public', ['token' => $report->public_token]);
        $messageText = trim((string) $request->input('message', '')) ?: null;
        $sender = Auth::user();
        $senderName = $sender
            ? (trim(($sender->name ?? '') . ' ' . ($sender->last_name ?? '')) ?: $sender->email)
            : null;

        foreach ($emails as $email) {
            Mail::to($email)->send(new SeoReportShareMail(
                $project,
                $report,
                $publicUrl,
                $messageText,
                $senderName
            ));
        }

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('Report link sent'));
    }

    private function findReportOrFail(SeoReportProject $project, int $reportId): ?SeoReport
    {
        return SeoReport::query()
            ->where('project_id', $project->id)
            ->where('id', $reportId)
            ->first();
    }

    private function isReportReady(SeoReport $report): bool
    {
        return in_array($report->status, [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED], true);
    }

    private function dispatchGenerate(SeoReport $report): void
    {
        $job = new GenerateSeoReportJob((int) $report->id);
        if (config('seo-reports.sync') || config('queue.default') === 'sync') {
            // Локально / без воркера — сразу в запросе
            $this->dispatchNow($job);
            return;
        }

        $this->dispatch($job);
    }

    public function updateTexts(Request $request, int $id, int $reportId): RedirectResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        if ($deny = $this->denyReadOnly($project)) {
            return $deny;
        }

        $report = SeoReport::query()
            ->where('project_id', $project->id)
            ->where('id', $reportId)
            ->first();
        if (!$report) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Report not found'));
        }

        $report->summary_text = trim((string) $request->input('summary_text', '')) ?: null;
        $report->work_done_text = trim((string) $request->input('work_done_text', '')) ?: null;
        $report->work_plan_text = trim((string) $request->input('work_plan_text', '')) ?: null;

        $comments = is_array($report->comments_json) ? $report->comments_json : [];
        $comments['traffic'] = trim((string) $request->input('comment_traffic', '')) ?: null;
        $comments['positions'] = trim((string) $request->input('comment_positions', '')) ?: null;
        $comments['conversions'] = trim((string) $request->input('comment_conversions', '')) ?: null;
        $comments['gsc'] = trim((string) $request->input('comment_gsc', '')) ?: null;
        $comments['webmaster'] = trim((string) $request->input('comment_webmaster', '')) ?: null;
        $comments['recommendations'] = trim((string) $request->input('recommendations_text', '')) ?: null;
        $workflow = (string) $request->input('workflow_status', '');
        if (in_array($workflow, ['draft', 'review', 'client'], true)) {
            $comments['workflow_status'] = $workflow;
        }
        $report->comments_json = $comments;
        $report->save();

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('Saved'));
    }

    public function batchGenerate(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        if ($blocked = $this->tariffBlocksGeneration($userId)) {
            return $blocked;
        }
        $ids = $request->input('project_ids', []);
        if (!is_array($ids) || $ids === []) {
            return redirect()->route('pages.seo-reports')->with('error', __('Select projects'));
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));

        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $cFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $cTo = $cFrom->copy()->endOfMonth()->startOfDay();

        $count = 0;
        $projects = SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereIn('id', $ids)
            ->get();

        foreach ($projects as $project) {
            $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
            $exists = SeoReport::query()
                ->where('project_id', $project->id)
                ->whereDate('period_from', $from->toDateString())
                ->whereDate('period_to', $to->toDateString())
                ->whereNull('archived_from_report_id')
                ->whereIn('status', [
                    SeoReport::STATUS_READY,
                    SeoReport::STATUS_APPROVED,
                    SeoReport::STATUS_GENERATING,
                ])
                ->exists();
            if ($exists && !$request->boolean('force')) {
                continue;
            }

            $report = SeoReport::query()->create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'status' => SeoReport::STATUS_GENERATING,
                'period_from' => $from,
                'period_to' => $to,
                'compare_from' => !empty($settings['auto_compare']) ? $cFrom : null,
                'compare_to' => !empty($settings['auto_compare']) ? $cTo : null,
                'section_states' => $this->buildInitialSectionStates($project),
            ]);
            $report->ensurePublicToken();
            $this->dispatchGenerate($report);
            $count++;
        }

        return redirect()
            ->route('pages.seo-reports')
            ->with('success', __('Batch generate started') . ': ' . $count);
    }

    public function createDemo(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $projectId = (int) $request->input('project_id');
        $project = $this->findOwnedProject($projectId);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $preset = (string) $request->input('template', 'seo_only');
        if (!in_array($preset, ['seo_only', 'seo_ads', 'complex'], true)) {
            $preset = 'seo_only';
        }
        $demo = app(SeoReportPresetDemoFactory::class)->make($preset);

        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $report = SeoReport::query()->create([
            'project_id' => $project->id,
            'user_id' => $userId,
            'status' => SeoReport::STATUS_READY,
            'period_from' => $from,
            'period_to' => $to,
            'compare_from' => $from->copy()->subMonthNoOverflow()->startOfMonth(),
            'compare_to' => $from->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay(),
            'section_states' => $this->buildInitialSectionStates($project),
            'summary_text' => $demo['report']->summary_text,
            'work_done_text' => $demo['report']->work_done_text,
            'work_plan_text' => $demo['report']->work_plan_text,
            'generated_at' => now(),
            'comments_json' => ['workflow_status' => 'client'],
        ]);
        $report->ensurePublicToken();
        $snap = $demo['snapshot'];
        $snap['cover']['title'] = 'SEO-отчёт · ' . $project->domain;
        $snap['cover']['domain'] = $project->domain;
        $snap['published_at'] = now()->toIso8601String();
        $snap['is_demo'] = true;
        $report->snapshot_json = $snap;
        $report->save();

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('Demo report created'));
    }

    /**
     * Полноценный HTML демо-отчёт по пресету (без записи в БД).
     */
    public function presetDemo(string $preset): View
    {
        if (!in_array($preset, ['seo_only', 'seo_ads', 'complex'], true)) {
            abort(404);
        }

        $demo = app(SeoReportPresetDemoFactory::class)->make($preset);

        return view('pages.seo-reports-preset-demo', [
            'project' => $demo['project'],
            'report' => $demo['report'],
            'snapshot' => $demo['snapshot'],
            'sections' => $demo['sections'],
            'preset' => $demo['preset'],
            'presetTitle' => $demo['preset_title'],
            'templateId' => null,
        ]);
    }

    /**
     * HTML демо по конкретному шаблону (секции + брендинг шаблона).
     *
     * @return View|RedirectResponse
     */
    public function templateDemo(int $id)
    {
        $template = $this->findOwnedTemplate($id);
        if (!$template) {
            return redirect()->route('pages.seo-reports.templates')->with('error', __('Template not found'));
        }

        $demo = app(SeoReportPresetDemoFactory::class)->makeFromTemplate($template);

        return view('pages.seo-reports-preset-demo', [
            'project' => $demo['project'],
            'report' => $demo['report'],
            'snapshot' => $demo['snapshot'],
            'sections' => $demo['sections'],
            'preset' => $demo['preset'],
            'presetTitle' => $demo['preset_title'],
            'templateId' => $demo['template_id'],
        ]);
    }

    public function updateShare(Request $request, int $id, int $reportId): RedirectResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        if ($deny = $this->denyReadOnly($project)) {
            return $deny;
        }

        $report = SeoReport::query()
            ->where('project_id', $project->id)
            ->where('id', $reportId)
            ->first();
        if (!$report) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Report not found'));
        }

        $report->ensurePublicToken();
        $report->public_pin = $this->normalizePin($request->input('public_pin'));
        $report->save();

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('Public link settings saved'));
    }

    /**
     * @return View|RedirectResponse
     */
    public function showReport(int $id, int $reportId)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $report = SeoReport::query()
            ->where('project_id', $project->id)
            ->where('id', $reportId)
            ->first();
        if (!$report) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Report not found'));
        }

        $states = is_array($report->section_states) ? $report->section_states : [];
        $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
        $catalog = SeoReportSectionRegistry::all();
        $sections = [];
        foreach (SeoReportSectionRegistry::orderedKeys($settings) as $key) {
            $meta = $catalog[$key] ?? null;
            if (!$meta) {
                continue;
            }
            $state = isset($states[$key]) && is_array($states[$key]) ? $states[$key] : [];
            $enabled = array_key_exists('enabled', $state)
                ? (bool) $state['enabled']
                : !empty($project->resolvedSectionToggles()[$key]);
            $sourceStatus = (string) ($state['source_status'] ?? SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED);
            $sections[] = [
                'key' => $key,
                'title' => $meta['title'],
                'group' => $meta['group'],
                'enabled' => $enabled,
                'source_status' => $sourceStatus,
                'client_visible' => SeoReportSectionRegistry::visibleForClient($enabled, $sourceStatus),
                'message' => $state['message'] ?? null,
            ];
        }

        $kpiHistoryReports = SeoReport::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED])
            ->whereNull('archived_from_report_id')
            ->orderByDesc('period_to')
            ->limit(6)
            ->get(['id', 'period_to', 'snapshot_json']);

        return view('pages.seo-reports-report', [
            'project' => $project,
            'report' => $report,
            'sections' => $sections,
            'snapshot' => is_array($report->snapshot_json) ? $report->snapshot_json : [],
            'kpiHistoryReports' => $kpiHistoryReports,
            'canEdit' => $project->canEditBy((int) Auth::id()),
            'isOwner' => $project->isOwnedBy((int) Auth::id()),
            'publicUrl' => $report->public_token
                ? route('seo-reports.public', ['token' => $report->public_token])
                : null,
            'presentUrl' => $report->public_token
                ? route('seo-reports.public.present', ['token' => $report->public_token])
                : null,
            'statusUrl' => route('pages.seo-reports.report.status', [
                'id' => $project->id,
                'reportId' => $report->id,
            ]),
            'pdfUrl' => route('pages.seo-reports.report.pdf', [
                'id' => $project->id,
                'reportId' => $report->id,
            ]),
            'qrUrl' => $report->public_token
                ? ('https://api.qrserver.com/v1/create-qr-code/?size=140x140&data='
                    . rawurlencode(route('seo-reports.public', ['token' => $report->public_token])))
                : null,
            'packUrl' => route('pages.seo-reports.report.pack', [
                'id' => $project->id,
                'reportId' => $report->id,
            ]),
            'phraseLibrary' => SeoReportPhraseLibrary::groups(),
        ]);
    }

    public function publish(int $id, int $reportId): RedirectResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        if ($deny = $this->denyReadOnly($project)) {
            return $deny;
        }
        $report = $this->findReportOrFail($project, $reportId);
        if (!$report || !$this->isReportReady($report)) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $reportId])
                ->with('error', __('Report is not ready yet'));
        }

        $snap = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $snap['published_at'] = now()->toIso8601String();
        $report->snapshot_json = $snap;
        $report->ensurePublicToken();
        $report->save();

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('Report published'));
    }

    public function cloneReport(int $id, int $reportId): RedirectResponse
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        if ($deny = $this->denyReadOnly($project)) {
            return $deny;
        }
        $source = $this->findReportOrFail($project, $reportId);
        if (!$source) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Report not found'));
        }

        $preset = 'prev_month';
        [$from, $to, $cFrom, $cTo] = $this->resolvePeriod($preset, request());

        $report = SeoReport::query()->create([
            'project_id' => $project->id,
            'user_id' => (int) Auth::id(),
            'status' => SeoReport::STATUS_GENERATING,
            'period_from' => $from,
            'period_to' => $to,
            'compare_from' => $cFrom,
            'compare_to' => $cTo,
            'section_states' => $this->buildInitialSectionStates($project),
            'summary_text' => $source->summary_text,
            'work_done_text' => $source->work_done_text,
            'work_plan_text' => $source->work_plan_text,
            'comments_json' => $source->comments_json,
            'public_pin' => $source->public_pin,
        ]);
        $report->ensurePublicToken();
        $project->touch();
        $this->dispatchGenerate($report);

        return redirect()
            ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id])
            ->with('success', __('Report cloned with new period'));
    }

    public function archive(int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $project->status = 'archived';
        $project->save();

        return redirect()
            ->route('pages.seo-reports')
            ->with('success', __('SEO report project archived'));
    }

    public function restore(int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $project->status = 'active';
        $project->save();

        return redirect()
            ->route('pages.seo-reports.show', ['id' => $project->id])
            ->with('success', __('SEO report project restored'));
    }

    /**
     * @return list<string>
     */
    private function generationWarnings(SeoReportProject $project): array
    {
        $warnings = [];
        $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
        if (!$project->metrika_counter_id) {
            $warnings[] = __('Yandex Metrika is not connected');
        }
        if (!$project->monitoring_project_id) {
            $warnings[] = __('Monitoring project is not linked');
        }
        $goalIds = isset($settings['metrika_goal_ids']) && is_array($settings['metrika_goal_ids'])
            ? $settings['metrika_goal_ids']
            : [];
        if ($project->metrika_counter_id && $goalIds === [] && !empty($project->resolvedSectionToggles()['conversions'])) {
            $warnings[] = __('Select Metrika goals in project settings');
        }
        $kpi = SeoReportKpiGoals::fromSettings($settings);
        $hasKpi = false;
        foreach ($kpi as $g) {
            if (!empty($g['enabled'])) {
                $hasKpi = true;
                break;
            }
        }
        if (!$hasKpi) {
            $warnings[] = __('No KPI goals');
        }
        $toggles = $project->resolvedSectionToggles();
        foreach (['direct' => __('Yandex Direct is not connected — open settings after OAuth is available'),
            'google_ads' => __('Google Ads is not connected — cabinet OAuth will be added later'),
            'vk_ads' => __('VK Ads is not connected'),
            'meta_ads' => __('Meta Ads is not connected')] as $key => $msg) {
            if (!empty($toggles[$key])) {
                $warnings[] = $msg;
            }
        }

        return $warnings;
    }

    /**
     * @return array<string,mixed>
     */
    private function demoSnapshot(SeoReportProject $project, SeoReport $report): array
    {
        $series = [];
        $day = optional($report->period_from)->copy() ?: Carbon::today()->startOfMonth();
        $end = optional($report->period_to)->copy() ?: Carbon::today();
        $i = 0;
        while ($day->lte($end) && $i < 31) {
            $series[$day->format('Y-m-d')] = 120 + ($i % 7) * 18 + ($i * 3);
            $day->addDay();
            $i++;
        }

        return [
            'cover' => [
                'title' => 'SEO-отчёт · ' . $project->domain,
                'period_label' => optional($report->period_from)->format('d.m.Y')
                    . ' — ' . optional($report->period_to)->format('d.m.Y'),
                'agency' => [
                    'name' => $project->brandingAgencyName(),
                    'brand_color' => SeoReportBrandColor::normalize($project->brandingColor()),
                ],
                'manager' => [
                    'name' => $project->brandingManagerName(),
                    'email' => $project->brandingManagerEmail(),
                    'phone' => $project->brandingManagerPhone(),
                ],
            ],
            'quality' => 'partial',
            'progress' => ['metrika' => 'ok', 'monitoring' => 'ok'],
            'traffic' => [
                'mode' => 'all',
                'kpis' => [
                    'visits' => ['value' => 12450, 'delta_pct' => 8.4],
                    'users' => ['value' => 9820, 'delta_pct' => 6.1],
                    'bounce_rate' => ['value' => 34.2, 'delta_pct' => -2.1],
                    'page_depth' => ['value' => 2.4, 'delta_pct' => 1.2],
                    'avg_visit_duration' => ['value' => 145, 'delta_pct' => 3.0],
                ],
                'series_users' => $series,
                'channels' => [
                    ['name' => 'Search engine traffic', 'visits' => 7200],
                    ['name' => 'Direct traffic', 'visits' => 2100],
                    ['name' => 'Social network traffic', 'visits' => 980],
                ],
                'landings' => [
                    ['name' => '/', 'visits' => 3200, 'visits_delta_pct' => 12],
                    ['name' => '/services', 'visits' => 1450, 'visits_delta_pct' => 28],
                ],
                'landings_social' => [
                    ['name' => '/promo', 'visits' => 420],
                ],
                'ecommerce' => [
                    'available' => false,
                    'note' => __('Ecommerce metrics require Metrika ecommerce tracking'),
                ],
            ],
            'positions' => [
                'summary' => ['top3' => 12, 'top10' => 48, 'top30' => 120, 'top100' => 210, 'diff_top10' => '+6'],
                'dynamics' => ['improved' => 34, 'unchanged' => 80, 'worsened' => 18, 'pairs' => true],
                'top_baskets' => [
                    ['label' => 'TOP-10', 'value' => 48, 'diff' => '+6'],
                    ['label' => 'TOP-30', 'value' => 120, 'diff' => '+11'],
                ],
                'groups' => [
                    ['id' => 1, 'name' => 'Коммерция', 'words' => 80],
                    ['id' => 2, 'name' => 'Инфо', 'words' => 40],
                ],
                'competitors' => [
                    'count' => 2,
                    'urls' => ['competitor-a.example', 'competitor-b.example'],
                ],
                'note' => __('Demo data'),
            ],
            'conversions' => [
                'goals' => [[
                    'id' => 1,
                    'name' => 'Заявка',
                    'reaches' => ['value' => 186, 'delta_pct' => 4.2],
                    'conversion_rate' => ['value' => 1.5, 'delta_pct' => -0.2],
                    'cost_per_conversion' => null,
                ]],
                'social_goals' => [[
                    'id' => 1,
                    'name' => 'Заявка',
                    'reaches' => 12,
                    'conversion_rate' => 1.1,
                ]],
            ],
            'insights' => [
                'Визиты за период: 12 450 (+8,4% к прошлому периоду)',
                'Позиции: ↑34 / →80 / ↓18 запросов',
            ],
            'recommendations' => [
                ['priority' => 'P2', 'text' => 'Быстрые победы: усилить title на запросах 8–20.'],
                ['priority' => 'P3', 'text' => 'Проверить конверсию посадочной /services.'],
            ],
            'scorecard' => [
                ['key' => 'visits', 'label' => __('Visits'), 'value' => '12 450', 'delta' => '+8,4%', 'delta_class' => 'is-up'],
                ['key' => 'top10', 'label' => 'TOP-10', 'value' => '48', 'delta' => '+6', 'delta_class' => 'is-up'],
            ],
            'requires_publish' => false,
        ];
    }

    private function findOwnedProject(int $id): ?SeoReportProject
    {
        return SeoReportProject::query()
            ->where('id', $id)
            ->where('user_id', (int) Auth::id())
            ->first();
    }

    private function findOwnedTemplate(int $id): ?SeoReportTemplate
    {
        return SeoReportTemplate::query()
            ->where('id', $id)
            ->where('user_id', (int) Auth::id())
            ->first();
    }

    private function findAccessibleProject(int $id): ?SeoReportProject
    {
        $userId = (int) Auth::id();
        $project = SeoReportProject::query()->where('id', $id)->first();
        if (!$project || !$project->isAccessibleBy($userId)) {
            return null;
        }

        return $project;
    }

    private function denyReadOnly(SeoReportProject $project): ?RedirectResponse
    {
        if ($project->canEditBy((int) Auth::id())) {
            return null;
        }

        return redirect()
            ->route('pages.seo-reports.show', ['id' => $project->id])
            ->with('error', __('Read-only access'));
    }

    /**
     * @return array{kpis:array<string,float|null>,queries:list<array<string,mixed>>,pages:list<array<string,mixed>>}|null
     */
    private function parseSearchConsoleCsv(string $path): ?array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return null;
        }
        $header = fgetcsv($fh, 0, ',');
        if ($header === false) {
            // try semicolon
            rewind($fh);
            $header = fgetcsv($fh, 0, ';');
        }
        if (!is_array($header) || $header === []) {
            fclose($fh);

            return null;
        }
        // strip BOM
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $map = [];
        foreach ($header as $i => $col) {
            $key = mb_strtolower(trim((string) $col));
            $map[$key] = $i;
        }
        $pick = static function (array $map, array $aliases) {
            foreach ($aliases as $a) {
                if (isset($map[$a])) {
                    return $map[$a];
                }
            }

            return null;
        };
        $iQuery = $pick($map, ['query', 'запрос', 'top queries', 'queries']);
        $iPage = $pick($map, ['page', 'страница', 'top pages', 'landing page', 'url']);
        $iClicks = $pick($map, ['clicks', 'клики']);
        $iImpr = $pick($map, ['impressions', 'показы']);
        $iCtr = $pick($map, ['ctr']);
        $iPos = $pick($map, ['position', 'позиция', 'avg. position', 'average position']);
        if ($iClicks === null && $iImpr === null) {
            fclose($fh);

            return null;
        }

        $queries = [];
        $pages = [];
        $sumClicks = 0.0;
        $sumImpr = 0.0;
        $posWeighted = 0.0;
        while (($row = fgetcsv($fh, 0, strpos(implode(',', $header), ';') !== false ? ';' : ',')) !== false) {
            if (!is_array($row)) {
                continue;
            }
            $clicks = $iClicks !== null ? (float) str_replace(['%', ',', ' '], ['', '.', ''], (string) ($row[$iClicks] ?? 0)) : 0.0;
            $impr = $iImpr !== null ? (float) str_replace(['%', ',', ' '], ['', '.', ''], (string) ($row[$iImpr] ?? 0)) : 0.0;
            $ctr = $iCtr !== null ? (float) str_replace(['%', ',', ' '], ['', '.', ''], (string) ($row[$iCtr] ?? 0)) : null;
            $pos = $iPos !== null ? (float) str_replace(['%', ',', ' '], ['', '.', ''], (string) ($row[$iPos] ?? 0)) : null;
            if ($ctr === null && $impr > 0) {
                $ctr = round($clicks / $impr * 100, 2);
            }
            $sumClicks += $clicks;
            $sumImpr += $impr;
            if ($pos !== null && $impr > 0) {
                $posWeighted += $pos * $impr;
            }
            $item = [
                'clicks' => $clicks,
                'impressions' => $impr,
                'ctr' => $ctr,
                'position' => $pos,
            ];
            if ($iQuery !== null && trim((string) ($row[$iQuery] ?? '')) !== '') {
                $queries[] = $item + ['name' => trim((string) $row[$iQuery])];
            } elseif ($iPage !== null && trim((string) ($row[$iPage] ?? '')) !== '') {
                $pages[] = $item + ['name' => trim((string) $row[$iPage])];
            }
        }
        fclose($fh);

        return [
            'kpis' => [
                'clicks' => $sumClicks,
                'impressions' => $sumImpr,
                'ctr' => $sumImpr > 0 ? round($sumClicks / $sumImpr * 100, 2) : null,
                'position' => $sumImpr > 0 ? round($posWeighted / $sumImpr, 2) : null,
            ],
            'queries' => $queries,
            'pages' => $pages,
        ];
    }

    public function share(Request $request, int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }

        $email = trim(mb_strtolower((string) $request->input('email', '')));
        $role = $request->input('role') === SeoReportProject::SHARE_ROLE_EDIT
            ? SeoReportProject::SHARE_ROLE_EDIT
            : SeoReportProject::SHARE_ROLE_READ;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Enter a valid email'));
        }

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('User with this email not found'));
        }
        if ((int) $user->id === (int) $project->user_id) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Cannot share with project owner'));
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('seo_report_project_user')) {
            return redirect()
                ->route('pages.seo-reports.show', ['id' => $project->id])
                ->with('error', __('Sharing is unavailable until migration is applied'));
        }

        $project->sharedUsers()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
        Mail::to($user)->send(new SeoReportProjectShareMail($project, $role));

        return redirect()
            ->route('pages.seo-reports.show', ['id' => $project->id])
            ->with('success', __('Access granted'));
    }

    public function unshare(Request $request, int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        $userId = (int) $request->input('user_id');
        if ($userId > 0 && \Illuminate\Support\Facades\Schema::hasTable('seo_report_project_user')) {
            $project->sharedUsers()->detach($userId);
        }

        return redirect()
            ->route('pages.seo-reports.show', ['id' => $project->id])
            ->with('success', __('Access revoked'));
    }

    public function importSearchConsole(Request $request, int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        $source = $request->input('source') === 'webmaster' ? 'webmaster' : 'gsc';
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $parsed = $this->parseSearchConsoleCsv($request->file('csv')->getRealPath());
        if ($parsed === null || ($parsed['queries'] === [] && $parsed['pages'] === [])) {
            return redirect()
                ->route('pages.seo-reports.settings', ['id' => $project->id])
                ->with('error', __('Could not parse CSV'));
        }

        $settings = is_array($project->settings_json) ? $project->settings_json : [];
        $settings[$source . '_import'] = [
            'imported_at' => now()->toIso8601String(),
            'kpis' => $parsed['kpis'],
            'queries' => array_slice($parsed['queries'], 0, 50),
            'pages' => array_slice($parsed['pages'], 0, 50),
        ];
        $project->settings_json = $settings;
        $project->save();

        return redirect()
            ->route('pages.seo-reports.settings', ['id' => $project->id])
            ->with('success', __('Search console CSV imported'));
    }

    public function importExternalAds(Request $request, int $id): RedirectResponse
    {
        $project = $this->findOwnedProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        $source = (string) $request->input('source');
        if (!in_array($source, ['vk_ads', 'meta_ads', 'vk_smm'], true)) {
            return redirect()
                ->route('pages.seo-reports.settings', ['id' => $project->id])
                ->with('error', __('Unknown import source'));
        }
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $parsed = app(SeoReportExternalAdsCollector::class)
            ->parseCsv($request->file('csv')->getRealPath(), $source);
        if ($parsed === null) {
            return redirect()
                ->route('pages.seo-reports.settings', ['id' => $project->id])
                ->with('error', __('Could not parse CSV'));
        }

        $settings = is_array($project->settings_json) ? $project->settings_json : [];
        $settings[$source . '_import'] = $parsed;
        $project->settings_json = $settings;
        $project->save();

        return redirect()
            ->route('pages.seo-reports.settings', ['id' => $project->id])
            ->with('success', __('Ads / SMM CSV imported'));
    }

    /**
     * @param array<int, string> $exclude
     * @return array<int, string>
     */
    private function availableDomains(int $userId, array $exclude): array
    {
        $sitesPayload = HomeUserSites::forUser($userId);
        $out = [];
        foreach (($sitesPayload['sites'] ?? []) as $site) {
            $domain = (string) ($site['domain'] ?? '');
            if ($domain !== '' && !in_array($domain, $exclude, true)) {
                $out[] = $domain;
            }
        }

        return $out;
    }

    private function guessSourceStatus(SeoReportProject $project, string $source): string
    {
        if ($source === 'manual' || $source === 'computed') {
            return SeoReportSectionRegistry::SOURCE_STATUS_MANUAL;
        }
        if ($source === 'metrika') {
            return $project->metrika_counter_id
                ? SeoReportSectionRegistry::SOURCE_STATUS_OK
                : SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED;
        }
        if ($source === 'monitoring') {
            return $project->monitoring_project_id
                ? SeoReportSectionRegistry::SOURCE_STATUS_OK
                : SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED;
        }
        if ($source === 'gsc' || $source === 'webmaster') {
            $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
            $import = $settings[$source . '_import'] ?? null;
            if (is_array($import) && (!empty($import['queries']) || !empty($import['pages']) || !empty($import['kpis']))) {
                return SeoReportSectionRegistry::SOURCE_STATUS_OK;
            }
        }
        if (in_array($source, ['vk_ads', 'meta_ads', 'vk_smm'], true)) {
            $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
            $import = $settings[$source . '_import'] ?? null;
            if (is_array($import) && (
                !empty($import['kpis'])
                || !empty($import['campaigns'])
                || !empty($import['ads'])
                || !empty($import['top_posts'])
            )) {
                return SeoReportSectionRegistry::SOURCE_STATUS_OK;
            }
            if ($source === 'vk_smm'
                && trim((string) ($settings['vk_smm_token'] ?? '')) !== ''
                && trim((string) ($settings['vk_smm_group_id'] ?? '')) !== ''
            ) {
                return SeoReportSectionRegistry::SOURCE_STATUS_OK;
            }
        }

        return SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED;
    }

    /**
     * @return array<string, array{enabled:bool,source_status:string}>
     */
    private function buildInitialSectionStates(SeoReportProject $project): array
    {
        $toggles = $project->resolvedSectionToggles();
        $out = [];
        foreach (SeoReportSectionRegistry::all() as $key => $meta) {
            $enabled = !empty($toggles[$key]);
            $out[$key] = [
                'enabled' => $enabled,
                'source_status' => $this->guessSourceStatus($project, (string) $meta['source']),
            ];
        }

        return $out;
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:?Carbon,3:?Carbon}
     */
    private function resolvePeriod(string $preset, Request $request): array
    {
        if ($preset === 'last_30') {
            $to = Carbon::today()->subDay();
            $from = $to->copy()->subDays(29);
            $cTo = $from->copy()->subDay();
            $cFrom = $cTo->copy()->subDays(29);

            return [$from, $to, $cFrom, $cTo];
        }

        if ($preset === 'custom'
            && $request->filled('period_from')
            && $request->filled('period_to')
        ) {
            $from = Carbon::parse((string) $request->input('period_from'))->startOfDay();
            $to = Carbon::parse((string) $request->input('period_to'))->startOfDay();
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            $days = $from->diffInDays($to) + 1;
            $cTo = $from->copy()->subDay();
            $cFrom = $cTo->copy()->subDays($days - 1);

            return [$from, $to, $cFrom, $cTo];
        }

        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $cFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $cTo = $cFrom->copy()->endOfMonth()->startOfDay();

        return [$from, $to, $cFrom, $cTo];
    }

    private function normalizePin($value): ?string
    {
        $pin = preg_replace('/\D+/', '', (string) $value);
        if ($pin === null || $pin === '') {
            return null;
        }

        return substr($pin, 0, 8);
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

    private function tariffBlocksNewProject(int $userId): ?RedirectResponse
    {
        $user = Auth::user();
        if (!$user || (int) $user->id !== $userId) {
            return null;
        }

        $tariff = $user->tariff();
        if (!$tariff) {
            return null;
        }

        $asArray = $tariff->getAsArray();
        if (!isset($asArray['settings']['SeoReportProjects'])) {
            return null;
        }

        $limit = (int) ($asArray['settings']['SeoReportProjects']['value'] ?? 0);
        $count = SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->count();

        if ($count < $limit) {
            return null;
        }

        $message = $asArray['settings']['SeoReportProjects']['message']
            ?? __('SEO report projects tariff limit reached');

        return redirect()
            ->route('pages.seo-reports')
            ->with('error', $message);
    }

    private function tariffBlocksGeneration(int $userId): ?RedirectResponse
    {
        $user = Auth::user();
        if (!$user || (int) $user->id !== $userId) {
            return null;
        }
        $tariff = $user->tariff();
        if (!$tariff) {
            return null;
        }
        $asArray = $tariff->getAsArray();
        if (!isset($asArray['settings']['SeoReportGenerations'])) {
            return null;
        }
        $limit = (int) ($asArray['settings']['SeoReportGenerations']['value'] ?? 0);
        $used = SeoReport::query()
            ->where('user_id', $userId)
            ->whereNull('archived_from_report_id')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->where(function ($q) {
                $q->whereNull('snapshot_json')
                    ->orWhere('snapshot_json', 'not like', '%"is_demo":true%');
            })
            ->count();

        if ($used < $limit) {
            return null;
        }

        $message = $asArray['settings']['SeoReportGenerations']['message']
            ?? __('SEO report generations tariff limit reached');

        return redirect()
            ->route('pages.seo-reports')
            ->with('error', $message);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
     */
    public function docx(int $id, int $reportId)
    {
        $project = $this->findAccessibleProject($id);
        if (!$project) {
            return redirect()->route('pages.seo-reports')->with('error', __('Project not found'));
        }
        $report = $this->findReportOrFail($project, $reportId);
        if (!$report || !$this->isReportReady($report)) {
            return redirect()
                ->route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $reportId])
                ->with('error', __('Report is not ready yet'));
        }

        $path = $this->export->makeDocx($project, $report);

        return response()->download(
            $path,
            $this->export->docxFilename($project, $report),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }
}
