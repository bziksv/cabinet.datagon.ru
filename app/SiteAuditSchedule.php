<?php

namespace App;

use App\Support\SiteAuditLimits;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SiteAuditSchedule extends Model
{
    protected $table = 'site_audit_schedules';

    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_BIWEEKLY = 'biweekly';
    public const FREQ_TRIWEEKLY = 'triweekly';
    public const FREQ_MONTHLY = 'monthly';

    /** @var string[] */
    public const FREQUENCIES = [
        self::FREQ_WEEKLY,
        self::FREQ_BIWEEKLY,
        self::FREQ_TRIWEEKLY,
        self::FREQ_MONTHLY,
    ];

    protected $fillable = [
        'user_id',
        'project_id',
        'domain',
        'enabled',
        'frequency',
        'settings_json',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings_json' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(SiteAuditProject::class, 'project_id');
    }

    /**
     * Расписание доступно, если по тарифу лимит автоснятий > 0.
     */
    public static function allowedForUser(?User $user): bool
    {
        return $user && SiteAuditLimits::schedulesLimit($user) > 0;
    }

    /**
     * @return array<string,string> code => label
     */
    public static function frequencyLabels(): array
    {
        return [
            self::FREQ_WEEKLY => 'раз в неделю',
            self::FREQ_BIWEEKLY => 'раз в 2 недели',
            self::FREQ_TRIWEEKLY => 'раз в 3 недели',
            self::FREQ_MONTHLY => 'раз в месяц',
        ];
    }

    /**
     * ISO weekday 1=Пн … 7=Вс
     *
     * @return array<int,string>
     */
    public static function weekdayLabels(): array
    {
        return [
            1 => 'понедельник',
            2 => 'вторник',
            3 => 'среда',
            4 => 'четверг',
            5 => 'пятница',
            6 => 'суббота',
            7 => 'воскресенье',
        ];
    }

    public static function normalizeFrequency(?string $frequency): string
    {
        $frequency = (string) $frequency;
        if ($frequency === 'daily') {
            return self::FREQ_WEEKLY;
        }
        if (in_array($frequency, self::FREQUENCIES, true)) {
            return $frequency;
        }

        return self::FREQ_WEEKLY;
    }

    public static function normalizeWeekday($weekday, ?Carbon $fallbackFrom = null): int
    {
        $weekday = (int) $weekday;
        if ($weekday >= 1 && $weekday <= 7) {
            return $weekday;
        }
        $from = $fallbackFrom ?: Carbon::now();

        return (int) $from->dayOfWeekIso;
    }

    public static function normalizeHour($hour): int
    {
        $hour = (int) $hour;
        $hour = max(0, min(23, $hour));
        // пиковая нагрузка 11–14 МСК — автозапуск не ставим
        if (in_array($hour, [11, 12, 13, 14], true)) {
            return 4;
        }

        return $hour;
    }

    /**
     * Ближайший запуск: выбранный день недели + час, с шагом frequency.
     * Часовой пояс — серверный (обычно Europe/Moscow на prod).
     */
    public function computeNextRun(?Carbon $from = null): Carbon
    {
        $from = $from ? $from->copy() : Carbon::now();
        $settings = is_array($this->settings_json) ? $this->settings_json : [];
        $weekday = self::normalizeWeekday($settings['weekday'] ?? null, $from);
        $hour = self::normalizeHour($settings['hour'] ?? 4);
        $freq = self::normalizeFrequency($this->frequency);

        // минимальный интервал после $from
        $minGapDays = 0;
        switch ($freq) {
            case self::FREQ_BIWEEKLY:
                $minGapDays = 13;
                break;
            case self::FREQ_TRIWEEKLY:
                $minGapDays = 20;
                break;
            case self::FREQ_MONTHLY:
                $minGapDays = 27;
                break;
            case self::FREQ_WEEKLY:
            default:
                $minGapDays = 0;
                break;
        }

        $cursor = $from->copy()->addMinute();
        for ($guard = 0; $guard < 400; $guard++) {
            $cand = $cursor->copy()->startOfDay()->setTime($hour, 0, 0);
            // дойти до нужного weekday
            $delta = ($weekday - (int) $cand->dayOfWeekIso + 7) % 7;
            if ($delta > 0) {
                $cand->addDays($delta);
            }
            if ($cand->gt($from) && $cand->diffInDays($from) >= $minGapDays) {
                return $cand;
            }
            $cursor->addDay();
        }

        return $from->copy()->addWeek()->startOfDay()->setTime($hour, 0, 0);
    }

    public function weekday(): int
    {
        $settings = is_array($this->settings_json) ? $this->settings_json : [];

        return self::normalizeWeekday($settings['weekday'] ?? null);
    }

    public function hour(): int
    {
        $settings = is_array($this->settings_json) ? $this->settings_json : [];

        return self::normalizeHour($settings['hour'] ?? 4);
    }
}
