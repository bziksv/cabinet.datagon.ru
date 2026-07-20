<?php

namespace App\Http\Controllers;

use App\DomainInformation;
use App\DomainMonitoring;
use App\DomainRecordsHistory;
use App\Services\DomainRecordsService;
use App\SiteMonitoringConfig;
use App\Support\DemoCabinet;
use App\Support\DomainRecordsLimits;
use App\Support\SiteMonitoringTiming;
use App\TariffSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DomainRecordsController extends Controller
{
    /**
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        if (DemoCabinet::isCurrentUser() && ! $request->filled('history')) {
            $showcase = DemoCabinet::domainRecordsShowcasePath();
            if ($showcase) {
                return redirect($showcase);
            }
        }

        $user = Auth::user();
        $canSaveHistory = DomainRecordsLimits::canSaveHistory($user);
        $historyLimit = DomainRecordsLimits::historyLimitForUser($user);
        $savedCount = DomainRecordsLimits::savedCount($user);
        $histories = [];

        if ($user && $canSaveHistory) {
            $histories = DomainRecordsHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(30)
                ->get(['id', 'domain', 'snapshot', 'created_at']);
        }

        return view('pages.domain-records', [
            'limit' => DomainRecordsLimits::limitForUser($user),
            'remaining' => DomainRecordsLimits::remainingForUser($user),
            'canAddSiteMonitoring' => $user && $user->can('Domain monitoring'),
            'canAddDomainInformation' => $user && $user->can('Domain information'),
            'onFreeTariff' => $user ? $user->onFreeTariff() : true,
            'timingOptions' => SiteMonitoringTiming::selectOptionsForUser($user, false),
            'defaultTiming' => SiteMonitoringTiming::defaultForUser($user),
            'canSaveHistory' => $canSaveHistory,
            'historyLimit' => $historyLimit,
            'savedCount' => $savedCount,
            'histories' => $histories,
        ]);
    }

    public function lookup(Request $request, DomainRecordsService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        $domain = trim((string) $request->input('domain', ''));
        if ($domain === '') {
            return response()->json([
                'error' => 'validation',
                'message' => __('Domain records domain required'),
            ], 422);
        }

        if (! DomainRecordsLimits::canSpend(1, $user)) {
            return response()->json([
                'error' => 'limit',
                'message' => DomainRecordsLimits::limitMessage($user) ?: __('Domain records limit exhausted'),
                'remaining' => DomainRecordsLimits::remainingForUser($user),
                'limit' => DomainRecordsLimits::limitForUser($user),
            ], 403);
        }

        try {
            $result = $service->lookup($domain);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'fetch_failed',
                'message' => __('Domain records fetch failed'),
            ], 502);
        }

        if (empty($result['ok'])) {
            return response()->json([
                'error' => 'validation',
                'message' => $result['message'] ?? __('Domain records invalid domain'),
            ], 422);
        }

        DomainRecordsLimits::spend(1, $user);

        $host = $result['domain'];
        $inSiteMonitoring = DomainMonitoring::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($host) {
                $q->where('link', 'like', '%' . $host . '%');
            })
            ->exists();

        $inDomainInformation = DomainInformation::query()
            ->where('user_id', $user->id)
            ->where('domain', $host)
            ->exists();

        $historyId = null;
        $historyWarning = null;
        $save = $request->boolean('save', false);

        if ($save && DomainRecordsLimits::canSaveHistory($user)) {
            if (! DomainRecordsLimits::canSaveAnother($user)) {
                $historyWarning = DomainRecordsLimits::historyLimitMessage($user)
                    ?: __('Domain records history limit exhausted');
            } else {
                $history = DomainRecordsHistory::query()->create([
                    'user_id' => $user->id,
                    'domain' => $host,
                    'snapshot' => $result,
                ]);
                $historyId = $history->id;
            }
        }

        return response()->json([
            'ok' => true,
            'result' => $result,
            'remaining' => DomainRecordsLimits::remainingForUser($user),
            'limit' => DomainRecordsLimits::limitForUser($user),
            'history_id' => $historyId,
            'saved_count' => DomainRecordsLimits::savedCount($user),
            'history_limit' => DomainRecordsLimits::historyLimitForUser($user),
            'history_warning' => $historyWarning,
            'already' => [
                'site_monitoring' => $inSiteMonitoring,
                'domain_information' => $inDomainInformation,
            ],
            'permissions' => [
                'site_monitoring' => $user->can('Domain monitoring'),
                'domain_information' => $user->can('Domain information'),
            ],
        ]);
    }

    public function historyShow(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! DomainRecordsLimits::canSaveHistory($user)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => __('Domain records history paid only'),
            ], 403);
        }

        $row = DomainRecordsHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $row) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $row->id,
                'domain' => $row->domain,
                'snapshot' => $row->snapshot,
                'created_at' => optional($row->created_at)->format('d.m.Y H:i'),
            ],
        ]);
    }

    public function historyDestroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! DomainRecordsLimits::canSaveHistory($user)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => __('Domain records history paid only'),
            ], 403);
        }

        $deleted = DomainRecordsHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return response()->json([
            'ok' => (bool) $deleted,
            'saved_count' => DomainRecordsLimits::savedCount($user),
            'history_limit' => DomainRecordsLimits::historyLimitForUser($user),
        ]);
    }

    public function compare(Request $request, DomainRecordsService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! DomainRecordsLimits::canSaveHistory($user)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => __('Domain records history paid only'),
            ], 403);
        }

        $idA = (int) $request->input('a');
        $idB = (int) $request->input('b');
        if ($idA <= 0 || $idB <= 0 || $idA === $idB) {
            return response()->json([
                'error' => 'validation',
                'message' => __('Domain records compare pick two'),
            ], 422);
        }

        $rows = DomainRecordsHistory::query()
            ->where('user_id', $user->id)
            ->whereIn('id', [$idA, $idB])
            ->get()
            ->keyBy('id');

        if (! $rows->has($idA) || ! $rows->has($idB)) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $a = $rows->get($idA);
        $b = $rows->get($idB);
        $snapshotA = is_array($a->snapshot) ? $a->snapshot : [];
        $snapshotB = is_array($b->snapshot) ? $b->snapshot : [];

        return response()->json([
            'ok' => true,
            'a' => [
                'id' => $a->id,
                'domain' => $a->domain,
                'created_at' => optional($a->created_at)->format('d.m.Y H:i'),
                'snapshot' => $snapshotA,
            ],
            'b' => [
                'id' => $b->id,
                'domain' => $b->domain,
                'created_at' => optional($b->created_at)->format('d.m.Y H:i'),
                'snapshot' => $snapshotB,
            ],
            'diff' => $service->diffSnapshots($snapshotA, $snapshotB),
        ]);
    }

    public function ipNeighbors(Request $request, DomainRecordsService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        $ip = trim((string) $request->input('ip', ''));
        $domain = trim((string) $request->input('domain', ''));
        $result = $service->neighborsOnIp($ip, $domain);

        if (empty($result['ok'])) {
            return response()->json([
                'error' => 'validation',
                'message' => $result['message'] ?? __('Domain records invalid ip'),
                'ip' => $result['ip'] ?? $ip,
                'domains' => [],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'ip' => $result['ip'],
            'domains' => $result['domains'],
            'status' => $result['status'] ?? 'ok',
            'found_total' => $result['found_total'] ?? count($result['domains']),
            'truncated' => ! empty($result['truncated']),
            'message' => $result['message'] ?? null,
        ]);
    }

    public function addSiteMonitoring(Request $request, DomainRecordsService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->can('Domain monitoring')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => __('Domain records no site monitoring permission'),
            ], 403);
        }

        if (TariffSetting::checkDomainMonitoringLimits()) {
            return response()->json([
                'error' => 'limit',
                'message' => __('Your limits are exhausted this month'),
            ], 403);
        }

        $host = $service->normalizeHost((string) $request->input('domain', ''));
        if ($host === '') {
            return response()->json([
                'error' => 'validation',
                'message' => __('Domain records invalid domain'),
            ], 422);
        }

        $existing = DomainMonitoring::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($host) {
                $q->where('link', $host)
                    ->orWhere('link', 'http://' . $host)
                    ->orWhere('link', 'https://' . $host)
                    ->orWhere('link', 'like', '%://' . $host)
                    ->orWhere('link', 'like', '%://' . $host . '/%');
            })
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => true,
                'already' => true,
                'id' => $existing->id,
                'message' => __('Domain records already in site monitoring'),
                'redirect' => route('site.monitoring'),
            ]);
        }

        $link = 'https://' . $host;
        $timing = SiteMonitoringTiming::resolveForUser(
            $request->input('timing', SiteMonitoringTiming::defaultForUser($user)),
            $user
        );
        $waiting = (int) $request->input('waiting_time', 15);
        if (! in_array($waiting, [10, 15, 20], true)) {
            $waiting = 15;
        }

        $defaultNotify = SiteMonitoringConfig::defaultSendNotification();
        $monitoring = new DomainMonitoring([
            'project_name' => $host,
            'link' => $link,
            'phrase' => (string) $request->input('phrase', ''),
            'timing' => $timing,
            'waiting_time' => $waiting,
        ]);
        $monitoring->user_id = $user->id;

        if ($user->onFreeTariff()) {
            $monitoring->notify_telegram = 0;
            $monitoring->notify_email = 0;
        } else {
            $monitoring->notify_telegram = (int) $request->input('notify_telegram', $defaultNotify ? 1 : 0);
            $emailRequested = (int) $request->input('notify_email', 0);
            $monitoring->notify_email = $user->canReceiveSiteMonitoringEmail() ? $emailRequested : 0;
        }
        $monitoring->send_notification = (int) ($monitoring->notify_telegram || $monitoring->notify_email);
        $monitoring->save();

        return response()->json([
            'ok' => true,
            'already' => false,
            'id' => $monitoring->id,
            'message' => __('Domain records added to site monitoring'),
            'redirect' => route('site.monitoring'),
        ]);
    }

    public function addDomainInformation(Request $request, DomainRecordsService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->can('Domain information')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => __('Domain records no domain information permission'),
            ], 403);
        }

        if (TariffSetting::checkDomainInformationLimits($user)) {
            return response()->json([
                'error' => 'limit',
                'message' => __('Your limits are exhausted the number of monitored domains is exhausted'),
            ], 403);
        }

        $host = $service->normalizeHost((string) $request->input('domain', ''));
        $ascii = $this->asciiOrHost($host);
        if ($host === '' || ! DomainInformation::isValidDomain($ascii)) {
            return response()->json([
                'error' => 'validation',
                'message' => __('There is no such domain'),
            ], 422);
        }

        $existing = DomainInformation::query()
            ->where('user_id', $user->id)
            ->where('domain', $host)
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => true,
                'already' => true,
                'id' => $existing->id,
                'message' => __('Domain records already in domain information'),
                'redirect' => route('domain.information'),
            ]);
        }

        $row = new DomainInformation([
            'domain' => $host,
            'check_dns' => (int) $request->input('check_dns', 1),
            'check_registration_date' => (int) $request->input('check_registration_date', 1),
            'check_dns_email' => 0,
            'check_registration_date_email' => 0,
        ]);
        $row->user_id = $user->id;
        $row->save();

        try {
            DomainInformation::checkDomain($row, 'manual');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'already' => false,
            'id' => $row->id,
            'message' => __('Domain records added to domain information'),
            'redirect' => route('domain.information'),
        ]);
    }

    private function asciiOrHost(string $host): string
    {
        if (function_exists('idn_to_ascii')) {
            $converted = @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $host;
    }
}
