<?php

namespace App\Http\Controllers;

use App\BacklinkConfig;
use App\Jobs\Backlink\CheckProjectBacklinksJob;
use App\LinkTracking;
use App\ProjectTracking;
use App\Services\Backlink\BacklinkChecker;
use App\Support\BacklinkAdminStats;
use App\Support\BacklinkHtmlMatcher;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;

class BacklinkController extends Controller
{
    protected $result;

    protected $error = null;

    protected $node = false;

    protected $noIndex = null;

    protected $noFollow = null;

    public function __construct()
    {
        $this->middleware(['permission:Backlink']);
    }

    public function index()
    {
        if (\App\Support\DemoCabinet::isCurrentUser()) {
            $showcase = \App\Support\DemoCabinet::backlinkShowcasePath();
            if ($showcase) {
                return redirect($showcase);
            }
        }

        $backlinks = ProjectTracking::where('user_id', '=', Auth::id())->get();
        foreach ($backlinks as $project) {
            $count = BacklinkChecker::recountProject((int) $project->id);
            $project->total_broken_link = $count;
        }
        $user = Auth::user();
        $onFreeTariff = $user->onFreeTariff();
        $telegramConnected = $user->isTelegramConnected();
        $backlinkEmailAvailable = $user->canReceiveBacklinkEmail();
        $admin = User::isUserAdmin();

        if (count($backlinks) === 0) {
            return $this->createView();
        }

        return view('backlink.index', compact(
            'backlinks',
            'onFreeTariff',
            'telegramConnected',
            'backlinkEmailAvailable',
            'admin'
        ));
    }

    public function createView()
    {
        /** @var User $user */
        $user = Auth::user();
        if ($tariff = $user->tariff()) {
            $count = ProjectTracking::where('user_id', '=', $user->id)->count();
            $tariff = $tariff->getAsArray();
            if (array_key_exists('BacklinkProject', $tariff['settings'])) {

                if ($count >= $tariff['settings']['BacklinkProject']['value']) {
                    if ($tariff['settings']['BacklinkProject']['message'])
                        flash()->overlay($tariff['settings']['BacklinkProject']['message'], __('Error'))->error();

                    return redirect('backlink');
                }
            }
        }

        $monitoring = $this->getMonitoringOptions();
        $onFreeTariff = $user->onFreeTariff();
        $telegramConnected = $user->isTelegramConnected();
        $backlinkEmailAvailable = $user->canReceiveBacklinkEmail();
        $admin = User::isUserAdmin();

        return view('backlink.create', compact('monitoring', 'onFreeTariff', 'telegramConnected', 'backlinkEmailAvailable', 'admin'));
    }

    public function addLinkView($id)
    {
        $project = ProjectTracking::findOrFail($id);

        if ($project->user_id !== Auth::id() && !User::isUserAdmin()) {
            return abort(403);
        }

        $user = Auth::user();
        $onFreeTariff = $user->onFreeTariff();
        $telegramConnected = $user->isTelegramConnected();

        return view('backlink.add-backlink', compact('id', 'project', 'onFreeTariff', 'telegramConnected'));
    }

    public function remove($id): RedirectResponse
    {
        ProjectTracking::destroy($id);
        flash()->overlay(__('Tracking was successfully deleted'), ' ')->success();

        return Redirect::route('backlink');
    }

    public function show($id)
    {
        $project = ProjectTracking::findOrFail($id);

        if ($project->user_id !== Auth::id() && !User::isUserAdmin()) {
            return abort(403);
        }

        $monitoring = $this->getMonitoringOptions();
        $user = Auth::user();
        $onFreeTariff = $user->onFreeTariff();
        $telegramConnected = $user->isTelegramConnected();
        $checkProgress = CheckProjectBacklinksJob::progress((int) $project->id);
        $schedule = config('cabinet-backlink.schedule', []);
        $project->total_broken_link = BacklinkChecker::recountProject((int) $project->id);

        return view('backlink.show', compact(
            'project',
            'monitoring',
            'onFreeTariff',
            'telegramConnected',
            'checkProgress',
            'schedule'
        ));
    }

    public function storeLink(Request $request): RedirectResponse
    {
        if (isset($request->countRows)) {
            $this->simplifiedCreate($request);
        } else {
            $phrases = $this->getParams($request->params);

            if ($this->isParserError($phrases)) {
                flash()->overlay(__('Invalid format'), ' ')->error();
                return Redirect::back();
            }
            $this->expressCreate($request, $phrases);
        }
        flash()->overlay(__('Tracking was successfully created') . ' ' . __('Backlink schedule after upload'), ' ')->success();

        return Redirect::route('backlink');
    }

    public function editLink(Request $request): JsonResponse
    {
        $allowed = ['site_donor', 'link', 'anchor', 'nofollow', 'noindex'];
        $name = (string) $request->input('name', '');
        if (! in_array($name, $allowed, true)) {
            return response()->json([], 400);
        }

        $option = $request->input('option');
        // Пустой анкор = безанкорная ссылка.
        if ($name === 'anchor') {
            LinkTracking::where('id', $request->id)->update([
                'anchor' => (string) ($option ?? ''),
            ]);

            return response()->json([]);
        }

        if (strlen((string) $option) > 0) {
            LinkTracking::where('id', $request->id)->update([
                $name => $option,
            ]);

            return response()->json([]);
        }

        return response()->json([], 400);
    }

    public function editBacklink(Request $request): JsonResponse
    {
        $allowed = ['project_name', 'notify_telegram', 'notify_email'];
        $field = (string) $request->input('name', '');
        if (!in_array($field, $allowed, true)) {
            return response()->json([], 400);
        }

        if ($field === 'notify_email') {
            $user = Auth::user();
            if (!$user->canReceiveBacklinkEmail()) {
                return response()->json(['message' => __('Backlink free tariff email notice title')], 403);
            }
        }

        $projectId = (int) $request->input('id');
        if ($projectId < 1) {
            return response()->json([], 400);
        }

        $value = $request->input('option');
        if (in_array($field, ['notify_telegram', 'notify_email'], true)) {
            $value = (int) $value;
        } elseif ($field === 'project_name' && strlen((string) $value) < 1) {
            return response()->json([], 400);
        }

        $updated = ProjectTracking::query()
            ->where('id', $projectId)
            ->where('user_id', Auth::id())
            ->update([
                $field => $value,
            ]);

        if ($updated < 1) {
            return response()->json([], 404);
        }

        return response()->json([]);
    }

    public function removeLink($id): RedirectResponse
    {
        $link = LinkTracking::findOrFail($id);
        $projectId = (int) $link->project_tracking_id;
        $project = ProjectTracking::findOrFail($projectId);
        if ($project->total_link > 0) {
            $project->decrement('total_link');
        }
        LinkTracking::destroy($id);
        BacklinkChecker::recountProject($projectId);
        flash()->overlay(__('Link was successfully deleted'), ' ')->success();

        return Redirect::route('show.backlink', $projectId);
    }

    public function store(Request $request): RedirectResponse
    {
        if (isset($request->countRows)) {

            if ($this->checkLinks((int)$request->countRows))
                return redirect()->route('backlink');

            $this->simplifiedCreate($request);
        } else {

            $phrases = $this->getParams($request->params);

            if ($this->isParserError($phrases)) {
                flash()->overlay(__('Invalid format'), ' ')->error();
                return Redirect::refresh();
            }

            if ($this->checkLinks(count($phrases)))
                return redirect()->route('backlink');

            $this->expressCreate($request, $phrases);
        }

        flash()->overlay(__('Tracking was successfully created') . ' ' . __('Backlink schedule after upload'), ' ')->success();

        return Redirect::route('backlink');
    }

    protected function checkLinks(int $count): bool
    {
        /** @var User $user */
        $user = Auth::user();
        if ($tariff = $user->tariff()) {
            $tariff = $tariff->getAsArray();
            if (array_key_exists('BacklinkLinks', $tariff['settings'])) {

                if ($count > $tariff['settings']['BacklinkLinks']['value']) {

                    if ($tariff['settings']['BacklinkLinks']['message'])
                        flash()->overlay($tariff['settings']['BacklinkLinks']['message'], __('Error'))->error();

                    return true;
                }
            }
        }

        return false;
    }

    public function expressCreate($request, $phrases)
    {
        if (empty($request->id)) {
            $projectTracking = new ProjectTracking();
            $projectTracking->user_id = Auth::id();
            $projectTracking->monitoring_project_id = $request->input('monitoring_project_id', null);
            $projectTracking->project_name = $request->project_name;
            $projectTracking->total_link = count($phrases);
            $projectTracking->save();
        } else {
            $project = ProjectTracking::find($request->id);
            $project->increment('total_link');
        }

        foreach ($phrases as $phrase) {
            $params = explode("::", $phrase);
            $anchor = (string) ($params[2] ?? '');
            // «-» или «*» в экспресс-формате = безанкорная.
            if ($anchor === '-' || $anchor === '*') {
                $anchor = '';
            }
            $tracking = new LinkTracking([
                'project_tracking_id' => empty($request->id)
                    ? $projectTracking->id
                    : $request->id,
                'site_donor' => $params[0],
                'link' => $params[1],
                'anchor' => $anchor,
                'nofollow' => $params[3],
                'noindex' => $params[4],
            ]);

            $tracking->save();
        }
    }

    public function simplifiedCreate($request)
    {
        $request = $request->all();
        if (empty($request['id'])) {
            $projectTracking = new ProjectTracking();
            $projectTracking->user_id = Auth::id();
            $projectTracking->monitoring_project_id = $request['monitoring_project_id'];
            $projectTracking->project_name = $request['project_name'];
            $projectTracking->total_link = (integer)$request['countRows'];
            $projectTracking->save();
        } else {
            $project = ProjectTracking::find($request['id']);
            $project->increment('total_link');
        }
        for ($i = 1; $i <= (integer)$request['countRows']; $i++) {
            $anchorless = (string) ($request['anchorless_' . $i] ?? '0') === '1';
            $anchor = $anchorless ? '' : (string) ($request['anchor_' . $i] ?? '');
            $tracking = new LinkTracking([
                'project_tracking_id' => empty($request['id'])
                    ? $projectTracking->id
                    : $request['id'],
                'site_donor' => $request['site_donor_' . $i],
                'link' => $request['link_' . $i],
                'anchor' => $anchor,
                'nofollow' => $request['nofollow_' . $i],
                'noindex' => $request['noindex_' . $i],
            ]);

            $tracking->save();
        }
    }

    public function getParams($params)
    {
        return explode("\r\n", $params);
    }

    public function isParserError($phrases): bool
    {
        foreach ($phrases as $phrase) {
            $linkParams = explode("::", $phrase);
            if (count($linkParams) !== 5) {
                return true;
            }
        }
        return false;
    }

    public function checkLink($id): RedirectResponse
    {
        $site = LinkTracking::with('project')->findOrFail($id);
        if (! $site->project || ($site->project->user_id !== Auth::id() && ! User::isUserAdmin())) {
            abort(403);
        }

        app(BacklinkChecker::class)->checkAndSave($site);

        return Redirect::route('show.backlink', $site->project_tracking_id);
    }

    /**
     * Поставить в очередь проверку всех ссылок проекта.
     */
    public function checkProject($id): RedirectResponse
    {
        $project = ProjectTracking::findOrFail($id);
        if ($project->user_id !== Auth::id() && ! User::isUserAdmin()) {
            abort(403);
        }

        $progress = CheckProjectBacklinksJob::progress((int) $project->id);
        $busy = in_array(($progress['status'] ?? ''), ['queued', 'running'], true);
        if ($busy) {
            flash()->overlay(__('Backlink check project already running'), ' ')->warning();

            return Redirect::route('show.backlink', $project->id);
        }

        $total = LinkTracking::where('project_tracking_id', $project->id)->count();
        if ($total === 0) {
            flash()->overlay(__('Backlink empty links'), ' ')->error();

            return Redirect::route('show.backlink', $project->id);
        }

        Cache::put(CheckProjectBacklinksJob::cacheKey((int) $project->id), [
            'status' => 'queued',
            'total' => $total,
            'done' => 0,
            'started_at' => date('Y-m-d H:i:s'),
        ], 7200);

        CheckProjectBacklinksJob::dispatch((int) $project->id);

        flash()->overlay(__('Backlink check project queued', ['count' => $total]), ' ')->success();

        return Redirect::route('show.backlink', $project->id);
    }

    public function checkProjectStatus($id): JsonResponse
    {
        $project = ProjectTracking::findOrFail($id);
        if ($project->user_id !== Auth::id() && ! User::isUserAdmin()) {
            abort(403);
        }

        $progress = CheckProjectBacklinksJob::progress((int) $project->id) ?: [
            'status' => 'idle',
            'total' => 0,
            'done' => 0,
        ];

        return response()->json($progress);
    }

    public function analyseLink($project)
    {
        // Совместимость со старыми вызовами — делегируем в сервис.
        app(BacklinkChecker::class)->checkAndSave($project);
    }

    private function searchLink($html, $project)
    {
        // unused — логика в BacklinkChecker
    }

    public function curlInit($page_url)
    {
        return BacklinkHtmlMatcher::fetchHtml((string) $page_url);
    }

    public function saveResult($target, $broken)
    {
        // unused — логика в BacklinkChecker
    }

    public function increment($project_tracking_id)
    {
        $article = ProjectTracking::find($project_tracking_id);
        $article->increment('total_broken_link');
    }

    public function decrement($project_tracking_id)
    {
        $article = ProjectTracking::find($project_tracking_id);
        if ($article->total_broken_link != 0) {
            $article->decrement('total_broken_link');
        }
    }

    private function getMonitoringOptions(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $options = ['' => __('Backlink monitoring placeholder')];

        foreach ($user->monitoringProjects as $item) {
            $options[$item['id']] = $item['name'];
        }

        return $options;
    }

    public function config()
    {
        if (!User::isUserAdmin()) {
            abort(403);
        }

        $registry = BacklinkAdminStats::snapshot();

        return view('backlink.config', [
            'admin' => true,
            'config' => BacklinkConfig::instance(),
            'stats' => $registry['summary'],
            'registry' => $registry,
        ]);
    }

    public function editConfig(Request $request): RedirectResponse
    {
        if (!User::isUserAdmin()) {
            abort(403);
        }

        $request->validate([
            'default_notify_telegram' => 'nullable|boolean',
            'default_notify_email' => 'nullable|boolean',
            'email_notifications_enabled' => 'nullable|boolean',
            'telegram_notifications_enabled' => 'nullable|boolean',
        ]);

        BacklinkConfig::instance()->update([
            'default_notify_telegram' => $request->has('default_notify_telegram'),
            'default_notify_email' => $request->has('default_notify_email'),
            'email_notifications_enabled' => $request->has('email_notifications_enabled'),
            'telegram_notifications_enabled' => $request->has('telegram_notifications_enabled'),
        ]);

        flash()->overlay(__('Settings updated'), ' ')->success();

        return Redirect::route('backlink.config');
    }
}
