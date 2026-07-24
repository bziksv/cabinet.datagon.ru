<?php

namespace App\Http\Controllers;

use App\DomainInformation;
use App\DomainMonitoring;
use App\LinkTracking;
use App\ProjectTracking;
use App\Services\Backlink\BacklinkChecker;
use App\Support\BacklinkHtmlMatcher;
use App\User;
use Illuminate\Support\Facades\Log;
use PHPUnit\Exception;

class CroneController extends Controller
{

    /**
     * @var array $result
     */
    public $result;

    /**
     * @param $timing
     * @return void
     */
    public function checkLinkCrone($timing)
    {
        try {
            $projects = DomainMonitoring::where('timing', '=', $timing)->get();
            foreach ($projects as $project) {
                DomainMonitoring::httpCheck($project);
            }
        } catch (Exception $exception) {
            Log::debug('scan error', [$exception->getMessage()]);
        }
    }

    /**
     * method for cron
     */
    public function checkDomains()
    {
        $projects = DomainInformation::all();

        foreach ($projects as $project) {
            DomainInformation::checkDomain($project);
        }
    }

    /**
     * api method for cron
     */
    public function scanBrokenLinks()
    {
        try {
            $checker = app(BacklinkChecker::class);
            $links = LinkTracking::with('project.user')->where('broken', '=', 1)->get();
            $telegramByUserProject = [];

            foreach ($links->chunk(5) as $chunk) {
                foreach ($chunk as $link) {
                    $stillBroken = $checker->checkAndSave($link);
                    $link->refresh();

                    if ($stillBroken) {
                        if (! (bool) $link->mail_sent) {
                            $user = $link->project ? $link->project->user : null;
                            $project = $link->project;
                            if ($user && $project) {
                                if ((bool) $project->notify_email) {
                                    $user->sendBrokenLinkAlerts((string) $link->status, $link, $project);
                                }
                                $projectId = $link->project_tracking_id;
                                if ((bool) $project->notify_telegram) {
                                    if (! isset($telegramByUserProject[$user->id][$projectId])) {
                                        $telegramByUserProject[$user->id][$projectId] = [
                                            'project' => $link->project,
                                            'count' => 0,
                                        ];
                                    }
                                    $telegramByUserProject[$user->id][$projectId]['count']++;
                                }
                            }
                            $link->mail_sent = (bool) ($project && $project->notify_email);
                            $link->save();
                        }
                    } else {
                        if ((bool) $link->mail_sent) {
                            $link->mail_sent = false;
                            $link->save();
                        }
                    }
                }
            }

            foreach ($telegramByUserProject as $userId => $projects) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }
                foreach ($projects as $data) {
                    if ($data['count'] > 0 && $data['project']) {
                        $user->sendBrokenLinkProjectTelegram($data['project'], $data['count'], false);
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::debug('scan broken link', [$exception]);
        }
    }


    /**
     * api method for cron
     */
    public function scanLinks()
    {
        try {
            $checker = app(BacklinkChecker::class);
            $links = LinkTracking::query()->orderBy('id')->get();
            foreach ($links->chunk(5) as $chunk) {
                foreach ($chunk as $link) {
                    $checker->checkAndSave($link);
                }
            }
        } catch (\Exception $exception) {
            Log::debug('scan link', [$exception]);
        }
    }

    /**
     * @param $page_url
     * @param $link_url
     * @param $anchor
     * @param bool $nofollow
     * @param bool $noindex
     * @return void
     */
    public function analyseLink($page_url, $link_url, $anchor, bool $nofollow = false, bool $noindex = false)
    {
        $this->result = [];
        $html = $this->curlInit($page_url);
        if ($html == false) {
            $this->result['error'] = __('The donor site does not exist');

            return;
        }

        $hit = BacklinkHtmlMatcher::find((string) $html, (string) $link_url, (string) $anchor);
        if (! ($hit['found'] ?? false)) {
            $this->result['error'] = __('link not found or anchor does not match');

            return;
        }

        $this->result['link'] = ! empty($hit['anchorless'])
            ? __('Link found (anchorless)')
            : __('link found, anchor matches');

        if ($noindex) {
            if (! empty($hit['in_comment_noindex']) || $this->hasTagNoindexAround($html, $hit['node_html'] ?? '')) {
                // Раньше писали в error и ломали счётчик — теперь только пометка в статусе.
                $this->result['noindex'] = __('the link is placed in noindex');
            } else {
                $this->result['noindex'] = __('the link is not placed in noindex');
            }
        }

        if ($nofollow) {
            if (! empty($hit['has_nofollow'])) {
                $this->result['nofollow'] = __('link have attribute nofollow');
            } else {
                $this->result['nofollow'] = __('link not have attribute nofollow');
            }
        }
    }

    private function hasTagNoindexAround(string $html, string $anchorHtml): bool
    {
        if ($anchorHtml === '') {
            return false;
        }
        $pos = mb_stripos($html, $anchorHtml);
        if ($pos === false) {
            return false;
        }
        $before = mb_substr($html, max(0, $pos - 120), 120);
        $after = mb_substr($html, $pos + mb_strlen($anchorHtml), 120);

        return (bool) (
            preg_match('#<noindex[^>]*>\s*$#iu', $before)
            && preg_match('#^\s*</noindex>#iu', $after)
        );
    }

    public function curlInit($page_url)
    {
        return BacklinkHtmlMatcher::fetchHtml((string) $page_url);
    }

    /**
     * @deprecated логика в BacklinkHtmlMatcher::find — оставлено для совместимости вызовов
     */
    public function searchNoindex($html, $link_url, $anchor)
    {
        $hit = BacklinkHtmlMatcher::find((string) $html, (string) $link_url, (string) $anchor);
        if (($hit['found'] ?? false) && ! empty($hit['in_comment_noindex'])) {
            $this->result['error'] = __('the link is placed in noindex');
        } elseif ($hit['found'] ?? false) {
            $this->result['noindex'] = __('the link is not placed in noindex');
        }
    }

    public function searchLinksOnPage($html, $link_url, $anchor): ?array
    {
        $hit = BacklinkHtmlMatcher::find((string) $html, (string) $link_url, (string) $anchor);
        if ($hit['found'] ?? false) {
            $this->result['link'] = ! empty($hit['anchorless'])
                ? __('Link found (anchorless)')
                : __('link found, anchor matches');

            return [$hit];
        }
        $this->result['error'] = __('link not found or anchor does not match');

        return null;
    }

    /**
     * @param $target
     * @param $broken
     * @param $sendMail
     */
    public function saveResult($target, $broken, $sendMail = null)
    {
        $target->status = implode(', ', $this->result);
        $target->last_check = date('Y-m-d H:i:s');
        if (isset($sendMail)) {
            $target->mail_sent = $sendMail;
        }
        $target->broken = $broken ? 1 : 0;
        $target->save();
        BacklinkChecker::recountProject((int) $target->project_tracking_id);
    }

    /**
     * @param $project_tracking_id
     * @return void
     */
    public function increment($project_tracking_id)
    {
        $article = ProjectTracking::find($project_tracking_id);
        $article->increment('total_broken_link');
    }

    /**
     * @param $project_tracking_id
     * @return void
     */
    public function decrement($project_tracking_id)
    {
        $article = ProjectTracking::find($project_tracking_id);
        if ($article->total_broken_link != 0) {
            $article->decrement('total_broken_link');
        }
    }

    /**
     * @param $link
     */
    public function searchNofollow($link)
    {
        if (preg_match('/rel*=*[\'"]?nofollow[\'"]?/i ', $link[0])) {
            $this->result['error'] = __('the nofollow property is present in the rel attribute');
        } else {
            $this->result['nofollow'] = __('nofollow is missing');
        }
    }
}
