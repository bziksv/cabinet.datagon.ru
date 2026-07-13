<?php

namespace Tests\Unit;

use App\DomainMonitoring;
use Carbon\Carbon;
use Tests\TestCase;

class DomainMonitoringUptimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_uptime_uses_tracking_start_after_reset_not_project_creation(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');

        $project = new DomainMonitoring([
            'created_at' => '2021-11-04 11:00:14',
            'uptime_since' => '2026-07-13 00:00:00',
            'last_check' => '2026-07-13 11:50:00',
            'up_time' => 0,
            'broken' => false,
        ]);

        DomainMonitoring::calculateUpTime($project);

        $this->assertSame(600, (int) $project->up_time);
        $this->assertEqualsWithDelta(1.39, (float) $project->uptime_percent, 0.01);
    }

    public function test_uptime_stays_at_one_hundred_right_after_reset_window(): void
    {
        Carbon::setTestNow('2026-07-13 12:10:00');

        $project = new DomainMonitoring([
            'created_at' => '2021-11-04 11:00:14',
            'uptime_since' => '2026-07-13 12:00:00',
            'last_check' => '2026-07-13 12:00:00',
            'up_time' => 0,
            'broken' => false,
        ]);

        DomainMonitoring::calculateUpTime($project);

        $this->assertSame(600, (int) $project->up_time);
        $this->assertEqualsWithDelta(100.0, (float) $project->uptime_percent, 0.01);
    }

    public function test_uptime_does_not_grow_while_broken(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');

        $project = new DomainMonitoring([
            'created_at' => '2026-07-13 00:00:00',
            'uptime_since' => '2026-07-13 00:00:00',
            'last_check' => '2026-07-13 11:50:00',
            'up_time' => 1800,
            'broken' => true,
        ]);

        DomainMonitoring::calculateUpTime($project);

        $this->assertSame(1800, (int) $project->up_time);
        $this->assertEqualsWithDelta(4.17, (float) $project->uptime_percent, 0.01);
    }
}
