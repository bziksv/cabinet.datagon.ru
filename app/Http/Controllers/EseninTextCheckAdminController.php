<?php

namespace App\Http\Controllers;

use App\Services\EseninTextCheckService;
use App\Support\Esenin\Providers\LanguageToolClient;
use App\Support\Esenin\Providers\TurgenevClient;
use App\Support\EseninTextCheckAdminStats;
use App\Support\EseninTextCheckSettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EseninTextCheckAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    /**
     * @return View|RedirectResponse
     */
    public function settings(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->saveSettings($request);
        }

        $settings = EseninTextCheckSettingsRegistry::allForAdmin();
        $registry = EseninTextCheckAdminStats::snapshot();
        $stats = $registry['summary'];

        $providerStatus = [
            'turgenev' => TurgenevClient::balance(),
            'languagetool' => [
                'ok' => LanguageToolClient::isAvailable(),
                'error' => LanguageToolClient::isAvailable() ? null : __('Esenin admin provider unavailable'),
            ],
        ];

        return view('esenin-text-check.settings', compact(
            'settings',
            'registry',
            'stats',
            'providerStatus'
        ));
    }

    private function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module_max_chars' => 'required|integer|min:1000|max:100000',
            'module_cost_per_check' => 'required|integer|min:1|max:100',
            'module_default_mode' => 'required|in:' . implode(',', array_keys(EseninTextCheckService::MODES)),
            'module_analyzer_version' => 'required|integer|min:1|max:999',
            'module_max_versions_per_session' => 'required|integer|min:1|max:50',
            'module_max_saved_sessions' => 'required|integer|min:1|max:500',
            'module_autosave_debounce_ms' => 'required|integer|min:500|max:30000',
            'module_public_share_ttl_days' => 'required|string|max:120',
            'demo_max_runs_per_day' => 'required|integer|min:1|max:100',
            'demo_max_chars' => 'required|integer|min:100|max:50000',
            'demo_min_chars' => 'required|integer|min:10|max:5000',
            'demo_full_max_chars' => 'required|integer|min:1000|max:100000',
            'provider_languagetool_language' => 'required|string|max:16',
            'provider_languagetool_url' => 'required|string|max:255',
            'provider_languagetool_timeout' => 'required|integer|min:3|max:120',
            'provider_turgenev_url' => 'required|string|max:255',
            'provider_turgenev_key' => 'nullable|string|max:128',
            'provider_turgenev_score_blend_percent' => 'required|integer|min:0|max:100',
            'provider_turgenev_timeout' => 'required|integer|min:5|max:120',
            'provider_opencorpora_url' => 'required|string|max:255',
            'provider_opencorpora_timeout' => 'required|integer|min:3|max:120',
        ]);

        $ttlRaw = preg_replace('/\s+/', '', (string) $validated['module_public_share_ttl_days']);
        $ttlParts = array_filter(array_map('trim', explode(',', (string) $ttlRaw)), static function ($part) {
            return $part !== '';
        });
        $ttlDays = array_values(array_map('intval', $ttlParts));
        if ($ttlDays === []) {
            return redirect()
                ->route('pages.esenin-text-check.settings')
                ->withErrors(['module_public_share_ttl_days' => __('Esenin admin share ttl invalid')])
                ->withInput();
        }

        $values = [
            'module.max_chars' => (string) $validated['module_max_chars'],
            'module.cost_per_check' => (string) $validated['module_cost_per_check'],
            'module.default_mode' => (string) $validated['module_default_mode'],
            'module.analyzer_version' => (string) $validated['module_analyzer_version'],
            'module.max_versions_per_session' => (string) $validated['module_max_versions_per_session'],
            'module.max_saved_sessions' => (string) $validated['module_max_saved_sessions'],
            'module.autosave_debounce_ms' => (string) $validated['module_autosave_debounce_ms'],
            'module.public_share_ttl_days' => json_encode($ttlDays, JSON_UNESCAPED_UNICODE),
            'demo.max_runs_per_day' => (string) $validated['demo_max_runs_per_day'],
            'demo.max_chars' => (string) $validated['demo_max_chars'],
            'demo.min_chars' => (string) $validated['demo_min_chars'],
            'demo.full_max_chars' => (string) $validated['demo_full_max_chars'],
            'provider.languagetool.enabled' => $request->has('provider_languagetool_enabled') ? '1' : '0',
            'provider.languagetool.url' => rtrim((string) $validated['provider_languagetool_url'], '/'),
            'provider.languagetool.language' => (string) $validated['provider_languagetool_language'],
            'provider.languagetool.mother_tongue' => (string) ($request->input('provider_languagetool_mother_tongue') ?: $validated['provider_languagetool_language']),
            'provider.languagetool.timeout' => (string) $validated['provider_languagetool_timeout'],
            'provider.turgenev.enabled' => $request->has('provider_turgenev_enabled') ? '1' : '0',
            'provider.turgenev.url' => (string) $validated['provider_turgenev_url'],
            'provider.turgenev.score_blend_percent' => (string) $validated['provider_turgenev_score_blend_percent'],
            'provider.turgenev.timeout' => (string) $validated['provider_turgenev_timeout'],
            'provider.opencorpora.enabled' => $request->has('provider_opencorpora_enabled') ? '1' : '0',
            'provider.opencorpora.url' => (string) $validated['provider_opencorpora_url'],
            'provider.opencorpora.timeout' => (string) $validated['provider_opencorpora_timeout'],
            'learning.enabled' => $request->has('learning_enabled') ? '1' : '0',
            'learning.report_fetch_enabled' => $request->has('learning_report_fetch_enabled') ? '1' : '0',
        ];

        if ($request->filled('provider_turgenev_key')) {
            $values['provider.turgenev.key'] = (string) $validated['provider_turgenev_key'];
        }

        EseninTextCheckSettingsRegistry::bulkSet($values);
        EseninTextCheckSettingsRegistry::flushCache();

        flash()->overlay(__('Settings updated'), ' ')->success();

        return redirect()->route('pages.esenin-text-check.settings');
    }
}
