<?php

namespace App\Http\Controllers;

use App\Mail\SeoReportClientReactionMail;
use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportSectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class SeoReportsPublicController extends Controller
{
    /**
     * @return View|RedirectResponse
     */
    public function show(Request $request, string $token)
    {
        $report = SeoReport::query()
            ->where('public_token', $token)
            ->with('project')
            ->first();

        if (!$report || !$report->project) {
            return $this->unavailable(
                __('Report unavailable'),
                __('This public link has expired or does not exist.')
            );
        }

        if ($report->status === SeoReport::STATUS_GENERATING) {
            return $this->unavailable(
                __('Generating report…'),
                __('SEO report public generating hint'),
                __('SEO report public generating wait')
            );
        }

        if ($report->status === SeoReport::STATUS_FAILED) {
            return $this->unavailable(
                __('SEO report generation failed'),
                $report->fail_reason ?: __('This public link has expired or does not exist.')
            );
        }

        if (!in_array($report->status, [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED], true)) {
            return $this->unavailable(
                __('Report unavailable'),
                __('This public link has expired or does not exist.')
            );
        }

        if (!$this->isPublishedForClient($report)) {
            return $this->unavailable(
                __('Report is not published yet'),
                __('SEO report public not published hint')
            );
        }

        $pin = trim((string) ($report->public_pin ?? ''));
        if ($pin !== '') {
            $unlocked = (string) $request->session()->get($this->pinSessionKey($token), '') === $pin;
            if (!$unlocked) {
                return view('pages.seo-reports-public-pin', [
                    'token' => $token,
                    'error' => null,
                ]);
            }
        }

        return $this->renderPublic($report, false, !empty($request->query('lite')));
    }

    /**
     * @return View|RedirectResponse
     */
    public function unlock(Request $request, string $token)
    {
        $report = SeoReport::query()
            ->where('public_token', $token)
            ->first();

        if (!$report) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        $pin = trim((string) ($report->public_pin ?? ''));
        $entered = trim((string) $request->input('pin', ''));
        if ($pin === '' || !hash_equals($pin, $entered)) {
            return view('pages.seo-reports-public-pin', [
                'token' => $token,
                'error' => __('Invalid PIN'),
            ]);
        }

        $request->session()->put($this->pinSessionKey($token), $pin);

        return redirect()->route('seo-reports.public', ['token' => $token]);
    }

    /**
     * @return View|RedirectResponse
     */
    public function present(Request $request, string $token)
    {
        $report = SeoReport::query()
            ->where('public_token', $token)
            ->with('project')
            ->first();

        if (!$report || !$report->project) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        if ($report->status === SeoReport::STATUS_GENERATING) {
            return $this->unavailable(
                __('Generating report…'),
                __('SEO report public generating hint'),
                __('SEO report public generating wait')
            );
        }

        if (!in_array($report->status, [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED], true)) {
            return $this->unavailable(
                __('Report unavailable'),
                __('This public link has expired or does not exist.')
            );
        }

        if (!$this->isPublishedForClient($report)) {
            return $this->unavailable(
                __('Report is not published yet'),
                __('SEO report public not published hint')
            );
        }

        $pin = trim((string) ($report->public_pin ?? ''));
        if ($pin !== '') {
            $unlocked = (string) $request->session()->get($this->pinSessionKey($token), '') === $pin;
            if (!$unlocked) {
                return redirect()->route('seo-reports.public', ['token' => $token]);
            }
        }

        return $this->renderPublic($report, true, false);
    }

    public function react(Request $request, string $token): JsonResponse
    {
        $report = SeoReport::query()
            ->where('public_token', $token)
            ->with('project')
            ->first();

        if (!$report || !$report->project || !$this->isPublishedForClient($report)) {
            return response()->json(['ok' => false], 404);
        }

        $pin = trim((string) ($report->public_pin ?? ''));
        if ($pin !== '') {
            $unlocked = (string) $request->session()->get($this->pinSessionKey($token), '') === $pin;
            if (!$unlocked) {
                return response()->json(['ok' => false, 'message' => 'pin'], 403);
            }
        }

        $type = (string) $request->input('type', '');
        if (!in_array($type, ['like', 'question', 'clarify'], true)) {
            return response()->json(['ok' => false], 422);
        }
        $section = trim((string) $request->input('section', ''));
        $catalog = SeoReportSectionRegistry::all();
        if ($section === '' || strlen($section) > 64 || !isset($catalog[$section])) {
            return response()->json(['ok' => false], 422);
        }
        $text = trim((string) $request->input('text', ''));
        if (mb_strlen($text) > 500) {
            $text = mb_substr($text, 0, 500);
        }

        $comments = is_array($report->comments_json) ? $report->comments_json : [];
        $reactions = is_array($comments['client_reactions'] ?? null) ? $comments['client_reactions'] : [];
        $reactions[] = [
            'section' => $section,
            'type' => $type,
            'text' => $text !== '' ? $text : null,
            'at' => now()->toIso8601String(),
        ];
        $comments['client_reactions'] = array_slice($reactions, -100);
        $report->comments_json = $comments;

        $snap = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $log = is_array($snap['audit_log'] ?? null) ? $snap['audit_log'] : [];
        $log[] = [
            'at' => now()->toIso8601String(),
            'action' => 'client_reaction',
            'type' => $type,
            'section' => $section,
        ];
        $snap['audit_log'] = array_slice($log, -50);
        $report->snapshot_json = $snap;
        $report->save();

        $typeLabels = [
            'like' => __('Looks good'),
            'question' => __('Ask a question'),
            'clarify' => __('Need clarification'),
        ];
        $sectionTitle = (string) ($catalog[$section]['title'] ?? $section);
        $typeLabel = $typeLabels[$type] ?? $type;
        $cabinetUrl = route('pages.seo-reports.report', [
            'id' => $report->project_id,
            'reportId' => $report->id,
        ]);

        $notifyTo = [];
        $managerEmail = trim((string) (
            $report->project->brandingManagerEmail()
            ?: ($report->project->manager_email ?? '')
        ));
        if ($managerEmail !== '' && filter_var($managerEmail, FILTER_VALIDATE_EMAIL)) {
            $notifyTo[] = $managerEmail;
        }
        $owner = $report->project->user ?? null;
        $ownerEmail = $owner ? trim((string) ($owner->email ?? '')) : '';
        if ($ownerEmail !== '' && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) && !in_array($ownerEmail, $notifyTo, true)) {
            // Если менеджер не указан — письмо владельцу проекта в кабинете.
            if ($notifyTo === []) {
                $notifyTo[] = $ownerEmail;
            }
        }

        foreach ($notifyTo as $email) {
            try {
                Mail::to($email)->send(new SeoReportClientReactionMail(
                    $report->project,
                    $report,
                    $sectionTitle,
                    $typeLabel,
                    $text !== '' ? $text : null,
                    $cabinetUrl
                ));
            } catch (Throwable $e) {
                Log::warning('seo report client reaction mail failed', [
                    'report_id' => $report->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => __('SEO report react sent'),
        ]);
    }

    public function approve(Request $request, string $token): RedirectResponse
    {
        $report = SeoReport::query()
            ->where('public_token', $token)
            ->first();

        if (!$report || $report->status !== SeoReport::STATUS_READY) {
            return redirect()
                ->route('seo-reports.public', ['token' => $token])
                ->with('error', __('Report cannot be approved'));
        }

        $pin = trim((string) ($report->public_pin ?? ''));
        if ($pin !== '') {
            $unlocked = (string) $request->session()->get($this->pinSessionKey($token), '') === $pin;
            if (!$unlocked) {
                return redirect()->route('seo-reports.public', ['token' => $token]);
            }
        }

        $report->status = SeoReport::STATUS_APPROVED;
        $report->approved_at = now();
        $snap = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $log = is_array($snap['audit_log'] ?? null) ? $snap['audit_log'] : [];
        $log[] = ['at' => now()->toIso8601String(), 'action' => 'approved_by_client'];
        $snap['audit_log'] = array_slice($log, -50);
        if (empty($snap['published_at'])) {
            $snap['published_at'] = now()->toIso8601String();
        }
        $report->snapshot_json = $snap;
        $report->save();

        return redirect()
            ->route('seo-reports.public', ['token' => $token])
            ->with('success', __('Report approved by client'));
    }

    private function renderPublic(SeoReport $report, bool $presentation, bool $lite = false): View
    {
        $project = $report->project;
        $snapshot = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        $states = is_array($report->section_states) ? $report->section_states : [];
        $settings = method_exists($project, 'reportSettings') ? $project->reportSettings() : (is_array($project->settings_json) ? $project->settings_json : []);
        $catalog = SeoReportSectionRegistry::all();

        $sections = [];
        foreach (SeoReportSectionRegistry::orderedKeys($settings) as $key) {
            $meta = $catalog[$key] ?? null;
            if (!$meta) {
                continue;
            }
            if ($lite && !in_array($key, ['cover', 'summary', 'kpi_goals', 'insights'], true)) {
                continue;
            }
            $state = isset($states[$key]) && is_array($states[$key]) ? $states[$key] : [];
            $enabled = array_key_exists('enabled', $state) ? (bool) $state['enabled'] : false;
            $sourceStatus = (string) ($state['source_status'] ?? SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED);
            if (!SeoReportSectionRegistry::visibleForClient($enabled, $sourceStatus)) {
                continue;
            }
            $sections[] = [
                'key' => $key,
                'title' => $meta['title'],
                'group' => $meta['group'],
            ];
        }

        return view('pages.seo-reports-public', [
            'project' => $project,
            'report' => $report,
            'snapshot' => $snapshot,
            'sections' => $sections,
            'isPublicView' => true,
            'presentation' => $presentation,
            'lite' => $lite,
            'darkTheme' => !empty($settings['public_dark_theme']),
        ]);
    }

    private function isPublishedForClient(SeoReport $report): bool
    {
        if ($report->status === SeoReport::STATUS_APPROVED) {
            return true;
        }
        $snap = is_array($report->snapshot_json) ? $report->snapshot_json : [];
        if (empty($snap['requires_publish'])) {
            return true;
        }

        return !empty($snap['published_at']);
    }

    private function unavailable(string $title, string $message, ?string $hint = null): View
    {
        return view('pages.seo-reports-public-unavailable', [
            'title' => $title,
            'message' => $message,
            'hint' => $hint,
        ]);
    }

    private function pinSessionKey(string $token): string
    {
        return 'seo_report_pin_' . $token;
    }
}
