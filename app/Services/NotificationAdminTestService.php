<?php

namespace App\Services;

use App\Notifications\BrokenDomainNotification;
use App\Notifications\BrokenLinkNotification;
use App\Notifications\MonitoringLimitExhaustedNotification;
use App\Notifications\RegisterPasswordEmail;
use App\Notifications\RepairDomainNotification;
use App\Notifications\sendNotificationAboutChangeDNS;
use App\Notifications\sendNotificationAboutExpirationRegistrationPeriod;
use App\ProjectTracking;
use App\Support\NotificationLocale;
use App\TelegramBot;
use App\User;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class NotificationAdminTestService
{
    private const TELEGRAM_EVENTS = [
        'profile-telegram-test',
        'backlink-telegram-summary',
        'site-mon-broken',
        'site-mon-repaired',
        'site-mon-repeat',
        'domain-dns-changed',
        'domain-expiration',
        'meta-tags-changed',
        'cluster-done',
        'monitoring-limit-exhausted',
    ];

    private const EMAIL_EVENTS = [
        'profile-password-reset',
        'backlink-email-link',
        'site-mon-broken',
        'site-mon-repaired',
        'site-mon-repeat',
        'domain-dns-changed',
        'domain-expiration',
        'monitoring-limit-exhausted',
        'auth-verify-email',
    ];

    private const MODAL_EVENTS = [
        'modal-telegram-connect',
        'modal-monitoring-schedule',
        'monitoring-positions-note',
        'news-badge',
    ];

    public function supportsTelegram(string $eventId): bool
    {
        return in_array($eventId, self::TELEGRAM_EVENTS, true);
    }

    public function supportsEmail(string $eventId): bool
    {
        return in_array($eventId, self::EMAIL_EVENTS, true);
    }

    public function supportsModalPreview(string $eventId): bool
    {
        return in_array($eventId, self::MODAL_EVENTS, true);
    }

    public function isKnownEvent(string $eventId): bool
    {
        return $this->findEventConfig($eventId) !== null;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendTelegram(string $eventId, User $user, ?string $locale = null): array
    {
        if (!$this->supportsTelegram($eventId)) {
            return ['ok' => false, 'message' => __('Users notify test unsupported')];
        }

        if (!$user->isTelegramConnected()) {
            return ['ok' => false, 'message' => __('Users notify test no telegram')];
        }

        if (empty(config('app.telegram_bot_token'))) {
            return ['ok' => false, 'message' => __('Telegram proxy admin no token')];
        }

        $this->applyLocale($user, $locale);
        $activeLocale = app()->getLocale();

        try {
            switch ($eventId) {
                case 'profile-telegram-test':
                    $sent = (new TelegramBotService((int) $user->chat_id))->sendMsg(
                        $this->testPrefix($activeLocale) . __('Profile telegram test notify passed'),
                        null,
                        [
                            'event_id' => 'profile-telegram-test',
                            'user_id' => (int) $user->id,
                            'source' => 'admin_test',
                        ]
                    );
                    break;

                case 'backlink-telegram-summary':
                    $project = $this->mockBacklinkProject($user);
                    $sent = TelegramBot::brokenLinkProjectNotification($project, $user->chat_id, 3, true);
                    break;

                case 'site-mon-broken':
                    TelegramBot::brokenDomainNotification(
                        $this->mockSiteMonitoringProject($user),
                        $user->chat_id,
                        'site-mon-broken',
                        'admin_test'
                    );
                    $sent = true;
                    break;

                case 'site-mon-repeat':
                    TelegramBot::brokenDomainNotification(
                        $this->mockSiteMonitoringProject($user),
                        $user->chat_id,
                        'site-mon-repeat',
                        'admin_test'
                    );
                    $sent = true;
                    break;

                case 'site-mon-repaired':
                    TelegramBot::repairedDomainNotification(
                        $this->mockSiteMonitoringProject($user),
                        $user->chat_id,
                        'admin_test'
                    );
                    $sent = true;
                    break;

                case 'domain-dns-changed':
                    TelegramBot::sendNotificationAboutChangeDNS(
                        $this->mockDomainInformationProject($user),
                        $user->chat_id,
                        'ns1.old-demo.ru, ns2.old-demo.ru',
                        'admin_test'
                    );
                    $sent = true;
                    break;

                case 'domain-expiration':
                    TelegramBot::sendNotificationAboutExpirationRegistrationPeriod(
                        $this->mockDomainInformationProject($user),
                        $user->chat_id,
                        14,
                        'admin_test'
                    );
                    $sent = true;
                    break;

                case 'meta-tags-changed':
                    $model = (object) ['name' => 'demo-test.example.ru'];
                    $linkCompare = url('/meta-tags');
                    $text = $this->testPrefix($activeLocale) . view('meta-tags.telegram', [
                        'model' => $model,
                        'link_compare' => $linkCompare,
                    ])->render();
                    $sent = (new TelegramBotService((int) $user->chat_id))->sendMsg($text, null, [
                        'event_id' => 'meta-tags-changed',
                        'user_id' => (int) $user->id,
                        'source' => 'admin_test',
                    ]);
                    break;

                case 'cluster-done':
                    $sent = (new TelegramBotService((int) $user->chat_id))->sendMsg(
                        $this->clusterDoneMessage($activeLocale),
                        null,
                        [
                            'event_id' => 'cluster-done',
                            'user_id' => (int) $user->id,
                            'source' => 'admin_test',
                        ]
                    );
                    break;

                case 'monitoring-limit-exhausted':
                    $sent = (new TelegramBotService((int) $user->chat_id))->sendMsg(
                        $this->testPrefix($activeLocale) . __('Monitoring limit exhausted telegram notify'),
                        null,
                        [
                            'event_id' => 'monitoring-limit-exhausted',
                            'user_id' => (int) $user->id,
                            'source' => 'admin_test',
                        ]
                    );
                    break;

                default:
                    return ['ok' => false, 'message' => __('Users notify test unsupported')];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if (empty($sent)) {
            return ['ok' => false, 'message' => TelegramBotService::$lastError ?: __('Unknown error')];
        }

        return [
            'ok' => true,
            'message' => __('Users notify test tg sent', [
                'lang' => strtoupper($activeLocale),
            ]),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendEmail(string $eventId, User $user, ?string $locale = null): array
    {
        if (!$this->supportsEmail($eventId)) {
            return ['ok' => false, 'message' => __('Users notify test unsupported')];
        }

        if (empty($user->email)) {
            return ['ok' => false, 'message' => __('Users notify test no email')];
        }

        $this->applyLocale($user, $locale);

        try {
            switch ($eventId) {
                case 'profile-password-reset':
                    $request = Request::create('/', 'POST', ['password' => 'DemoPass123']);
                    $user->notify(new RegisterPasswordEmail($request, $user));
                    break;

                case 'backlink-email-link':
                    $user->notify(new BrokenLinkNotification(
                        '404 Not Found (TEST)',
                        $this->mockBrokenLink()
                    ));
                    break;

                case 'site-mon-broken':
                    $user->notify(new BrokenDomainNotification($this->mockSiteMonitoringProject($user)));
                    break;

                case 'site-mon-repeat':
                    $user->notify(new BrokenDomainNotification($this->mockSiteMonitoringProject($user), 'site-mon-repeat'));
                    break;

                case 'site-mon-repaired':
                    $user->notify(new RepairDomainNotification($this->mockSiteMonitoringProject($user)));
                    break;

                case 'domain-dns-changed':
                    $user->notify(new sendNotificationAboutChangeDNS($this->mockDomainInformationProject($user)));
                    break;

                case 'domain-expiration':
                    $user->notify(new sendNotificationAboutExpirationRegistrationPeriod(
                        $this->mockDomainInformationProject($user),
                        14
                    ));
                    break;

                case 'monitoring-limit-exhausted':
                    $user->notify(new MonitoringLimitExhaustedNotification());
                    break;

                case 'auth-verify-email':
                    $user->notify(new VerifyEmail());
                    break;

                default:
                    return ['ok' => false, 'message' => __('Users notify test unsupported')];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => __('Users notify test email sent', [
                'email' => $user->email,
                'lang' => strtoupper(app()->getLocale()),
            ]),
        ];
    }

    public function renderEmailPreview(string $eventId, User $user, ?string $locale = null): string
    {
        if (!$this->supportsEmail($eventId)) {
            abort(404);
        }

        $this->applyLocale($user, $locale);

        switch ($eventId) {
            case 'profile-password-reset':
                $request = Request::create('/', 'POST', ['password' => 'DemoPass123']);
                $mail = (new RegisterPasswordEmail($request, $user))->toMail($user);
                break;

            case 'backlink-email-link':
                $mail = (new BrokenLinkNotification('404 Not Found (TEST)', $this->mockBrokenLink()))->toMail($user);
                break;

            case 'site-mon-broken':
            case 'site-mon-repeat':
                $mail = (new BrokenDomainNotification($this->mockSiteMonitoringProject($user)))->toMail($user);
                break;

            case 'site-mon-repaired':
                $mail = (new RepairDomainNotification($this->mockSiteMonitoringProject($user)))->toMail($user);
                break;

            case 'domain-dns-changed':
                $mail = (new sendNotificationAboutChangeDNS($this->mockDomainInformationProject($user)))->toMail($user);
                break;

            case 'domain-expiration':
                $mail = (new sendNotificationAboutExpirationRegistrationPeriod(
                    $this->mockDomainInformationProject($user),
                    14
                ))->toMail($user);
                break;

            case 'monitoring-limit-exhausted':
                $mail = (new MonitoringLimitExhaustedNotification())->toMail($user);
                break;

            case 'auth-verify-email':
                $mail = (new VerifyEmail())->toMail($user);
                break;

            default:
                abort(404);
        }

        return $mail->render();
    }

    /**
     * @return array{title: string, html: string}
     */
    public function renderModalPreview(string $eventId, User $user, ?string $locale = null): array
    {
        if (!$this->supportsModalPreview($eventId)) {
            abort(404);
        }

        $this->applyLocale($user, $locale);

        switch ($eventId) {
            case 'modal-telegram-connect':
                return [
                    'title' => __('Connect Telegram bot'),
                    'html' => view('admin.notifications.partials.previews.modal-telegram-connect', [
                        'telegramBotSubscribeUrl' => $user->telegramBotSubscribeUrl(),
                    ])->render(),
                ];

            case 'modal-monitoring-schedule':
            case 'monitoring-positions-note':
                return [
                    'title' => __('Monitoring schedule paid prompt title'),
                    'html' => view('admin.notifications.partials.previews.modal-monitoring-schedule')->render(),
                ];

            case 'news-badge':
                return [
                    'title' => __('Users notify event news title'),
                    'html' => view('admin.notifications.partials.previews.news-badge')->render(),
                ];

            default:
                abort(404);
        }
    }

    private function applyLocale(User $user, ?string $locale): void
    {
        $lang = $locale ?: ($user->lang ?: 'ru');
        if (!in_array($lang, ['ru', 'en'], true)) {
            $lang = 'ru';
        }

        NotificationLocale::override($locale !== null ? $lang : null);
        App::setLocale($lang);
    }

    private function findEventConfig(string $eventId): ?array
    {
        foreach ((array) config('cabinet-users-notifications.events', []) as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (($item['id'] ?? '') === $eventId) {
                    return $item;
                }
            }
        }

        return null;
    }

    private function testPrefix(?string $locale = null): string
    {
        $tag = strtoupper((string) ($locale ?: app()->getLocale() ?: 'ru'));
        if (!in_array($tag, ['RU', 'EN'], true)) {
            $tag = 'RU';
        }

        return '<b>[TEST ' . $tag . ']</b> ';
    }

    private function mockSiteMonitoringProject(User $user): object
    {
        return (object) [
            'user_id' => (int) $user->id,
            'project_name' => 'demo-test.example.ru',
            'link' => 'https://demo-test.example.ru/',
            'last_check' => Carbon::now()->format('Y-m-d H:i:s'),
            'code' => 503,
            'status' => 'unexpected',
            'uptime_percent' => 99.21,
            'total_time_last_breakdown' => 42,
        ];
    }

    private function mockDomainInformationProject(User $user): object
    {
        return (object) [
            'user_id' => (int) $user->id,
            'domain' => 'demo-test.example.ru',
            'dns' => 'ns1.demo.ru, ns2.demo.ru',
            'domain_information' => __('Registration date') . ' 2020-01-15' . "\n"
                . __('Registration expires') . ' 2026-06-15',
        ];
    }

    private function mockBrokenLink(): object
    {
        return (object) [
            'site_donor' => 'donor-demo.example.ru',
            'link' => 'https://donor-demo.example.ru/page',
            'anchor' => 'demo-anchor',
        ];
    }

    private function mockBacklinkProject(User $user): ProjectTracking
    {
        $project = ProjectTracking::where('user_id', $user->id)->first();
        if ($project) {
            return $project;
        }

        $project = new ProjectTracking();
        $project->id = 0;
        $project->user_id = $user->id;
        $project->project_name = 'demo-test.example.ru';

        return $project;
    }

    private function clusterDoneMessage(?string $locale = null): string
    {
        $cabinetUrl = url(route('cluster', [], false));

        return $this->testPrefix($locale) . __('Users notify cluster done telegram body', [
            'url' => $cabinetUrl,
        ]);
    }
}
