<?php

namespace App;

use App\Support\DomainInformationDns;
use App\Support\DomainInformationDisplay;
use App\DomainInformationConfig;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Iodev\Whois\Exceptions\ConnectionException;
use Iodev\Whois\Exceptions\ServerMismatchException;
use Iodev\Whois\Exceptions\WhoisException;
use Iodev\Whois\Factory;

class DomainInformation extends Model
{
    protected $table = 'domain_information';

    protected $guarded = [];

    public function checkLogs()
    {
        return $this->hasMany(DomainInformationCheckLog::class, 'domain_information_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  DomainInformation  $project
     * @param  string  $source  cron|manual
     */
    public static function checkDomain($project, string $source = 'cron'): void
    {
        $oldState = $project->broken;
        $oldDNS = $project->dns;
        $whois = Factory::get()->createWhois();
        $project->last_check = Carbon::now();

        try {
            $info = $whois->loadDomainInfo($project->domain);
            if (isset($info)) {
                $project->broken = false;
                $newDns = DomainInformationDns::formatFromNameServers($info->nameServers ?? []);
                $registrationDate = __('Registration date') . ' ' . date('Y-m-d', $info->creationDate);
                $freeDate = date('Y-m-d', $info->expirationDate);
                $project->dns = $newDns;
                $project->domain_information = self::formatRegistrationSummary($registrationDate, $freeDate);
                DomainInformation::sendNotifications($project, $oldState, $oldDNS, $newDns, $freeDate);
            } else {
                $project->broken = true;
                $project->domain_information = __('This domain has been removed from delegation(is free) and it can be registered.');
                DomainInformation::sendNotifications($project, $oldState);
            }
        } catch (\Exception $exception) {
            $project->broken = true;
            $project->domain_information = __('This domain has been removed from delegation(is free) and it can be registered.');
            DomainInformation::sendNotifications($project, $oldState);
        }

        $project->save();

        self::recordCheckLog($project, $source, $oldDNS);
    }

    public static function recordCheckLog(DomainInformation $project, string $source, ?string $oldDns = null): void
    {
        $dnsChanged = DomainInformationDns::hasChanged($oldDns, $project->dns);

        DomainInformationCheckLog::create([
            'domain_information_id' => $project->id,
            'user_id' => $project->user_id,
            'broken' => (bool) $project->broken,
            'info_snapshot' => DomainInformationDisplay::dnsBlock($project) . "\n\n" . DomainInformationDisplay::registrationBlock($project),
            'dns_changed' => $dnsChanged,
            'source' => $source === 'manual' ? 'manual' : 'cron',
            'created_at' => Carbon::now(),
        ]);
    }

    public static function formatRegistrationSummary(string $registrationDate, string $freeDate): string
    {
        $date = new Carbon($freeDate);

        return $registrationDate . "\n"
            . __('Registration expires')
            . $freeDate
            . ' '
            . __('through')
            . ' '
            . $date->diffInDays(Carbon::now())
            . ' '
            . __('days');
    }

    /**
     * @param  DomainInformation  $project
     * @param  bool  $oldState
     * @param  string|null  $oldDNS
     * @param  string|null  $newDNS
     * @param  string|null  $freeDate
     */
    public static function sendNotifications($project, $oldState, $oldDNS = null, $newDNS = null, $freeDate = null)
    {
        $user = User::find($project->user_id);

        if (DomainInformationDns::hasChanged($oldDNS, $newDNS ?? $project->dns)) {
            if ($user->telegram_bot_active && $project->check_dns && DomainInformationConfig::telegramEnabled()) {
                TelegramBot::sendNotificationAboutChangeDNS($project, $user->chat_id, $oldDNS);
            }

            if ($project->check_dns_email && $user->canReceiveDomainInformationEmail() && DomainInformationConfig::emailEnabled()) {
                $user->sendNotificationAboutChangeDNS($project);
            }
        }

        if (isset($freeDate)) {
            $freeDate = new Carbon($freeDate);
            $diffInDays = $freeDate->diffInDays(Carbon::now());
            $alertDays = DomainInformationConfig::expirationAlertDays();

            if ($diffInDays < $alertDays) {
                if ($user->telegram_bot_active && $project->check_registration_date && DomainInformationConfig::telegramEnabled()) {
                    TelegramBot::sendNotificationAboutExpirationRegistrationPeriod($project, $user->chat_id, $diffInDays);
                }

                if ($project->check_registration_date_email && $user->canReceiveDomainInformationEmail() && DomainInformationConfig::emailEnabled()) {
                    $user->sendNotificationAboutExpirationRegistrationPeriod($project, $diffInDays);
                }
            }
        }
    }

    /**
     * @param $domain
     * @return bool
     */
    public static function isValidDomain($domain): bool
    {
        return (
            preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain) &&
            preg_match("/^.{1,253}$/", $domain) &&
            preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain)
        );
    }

    /**
     * @param $link
     * @return string
     */
    public static function getDomain($link): string
    {
        $information = parse_url($link);

        return $information['host'] ?? $link;
    }

    /**
     * Разовая WHOIS-проверка для демо на маркетинге (без сохранения в БД).
     *
     * @return array<string, mixed>
     */
    public static function probe(string $rawDomain): array
    {
        $domain = self::getDomain(trim($rawDomain));
        if (!self::isValidDomain($domain)) {
            return [
                'ok' => false,
                'domain' => $domain,
                'broken' => true,
                'status' => (string) __('Domain information status error'),
                'status_key' => 'invalid',
                'message' => (string) __('There is no such domain'),
                'dns' => '',
                'dns_servers' => [],
                'registered_at' => null,
                'expires_at' => null,
                'days_until_expiry' => null,
                'registration_summary' => '',
            ];
        }

        $whois = Factory::get()->createWhois();

        try {
            $info = $whois->loadDomainInfo($domain);
            if (!isset($info)) {
                $message = (string) __('This domain has been removed from delegation(is free) and it can be registered.');

                return [
                    'ok' => true,
                    'domain' => $domain,
                    'broken' => true,
                    'status' => (string) __('Domain information status error'),
                    'status_key' => 'free',
                    'message' => $message,
                    'dns' => '',
                    'dns_servers' => [],
                    'registered_at' => null,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'registration_summary' => $message,
                ];
            }

            $dns = DomainInformationDns::formatFromNameServers($info->nameServers ?? []);
            $dnsServers = DomainInformationDns::normalizeList($info->nameServers ?? []);
            // iodev/whois: creationDate/expirationDate — magic props; isset()/empty() дают false даже когда значение есть.
            $creationTs = $info->creationDate;
            $expirationTs = $info->expirationDate;
            $registeredAt = $creationTs ? date('Y-m-d', (int) $creationTs) : null;
            $expiresAt = $expirationTs ? date('Y-m-d', (int) $expirationTs) : null;
            if ($registeredAt === null || $expiresAt === null) {
                $extraDates = self::datesFromWhoisExtra($info);
                $registeredAt = $registeredAt ?: ($extraDates['registered_at'] ?? null);
                $expiresAt = $expiresAt ?: ($extraDates['expires_at'] ?? null);
            }
            $daysLeft = null;
            if ($expiresAt !== null) {
                $daysLeft = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($expiresAt)->startOfDay(), false);
                if ($daysLeft < 0) {
                    $daysLeft = 0;
                }
            }
            $registrationSummary = $registeredAt && $expiresAt
                ? self::formatRegistrationSummary(
                    __('Registration date') . ' ' . $registeredAt,
                    $expiresAt
                )
                : '';

            return [
                'ok' => true,
                'domain' => $domain,
                'broken' => false,
                'status' => (string) __('Domain information status ok'),
                'status_key' => 'ok',
                'message' => '',
                'dns' => $dns,
                'dns_servers' => $dnsServers,
                'registered_at' => $registeredAt,
                'expires_at' => $expiresAt,
                'days_until_expiry' => $daysLeft,
                'registration_summary' => $registrationSummary,
            ];
        } catch (\Exception $exception) {
            $message = (string) __('This domain has been removed from delegation(is free) and it can be registered.');

            return [
                'ok' => true,
                'domain' => $domain,
                'broken' => true,
                'status' => (string) __('Domain information status error'),
                'status_key' => 'error',
                'message' => $message,
                'dns' => '',
                'dns_servers' => [],
                'registered_at' => null,
                'expires_at' => null,
                'days_until_expiry' => null,
                'registration_summary' => $message,
            ];
        }
    }

    /**
     * Fallback дат из extra (created / paid-till), если magic props не отдались через isset.
     *
     * @return array{registered_at: ?string, expires_at: ?string}
     */
    private static function datesFromWhoisExtra($info): array
    {
        $out = ['registered_at' => null, 'expires_at' => null];
        try {
            $extra = method_exists($info, 'getExtra') ? $info->getExtra() : null;
            if (! is_array($extra)) {
                return $out;
            }
            $groups = $extra['groups'] ?? [];
            if (! is_array($groups) || $groups === []) {
                return $out;
            }
            $group = $groups[0] ?? [];
            if (! is_array($group)) {
                return $out;
            }
            if (! empty($group['created'])) {
                $ts = strtotime((string) $group['created']);
                if ($ts) {
                    $out['registered_at'] = date('Y-m-d', $ts);
                }
            }
            $paid = $group['paid-till'] ?? $group['paid_till'] ?? $group['free-date'] ?? null;
            if (! empty($paid)) {
                $ts = strtotime((string) $paid);
                if ($ts) {
                    $out['expires_at'] = date('Y-m-d', $ts);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }

}
